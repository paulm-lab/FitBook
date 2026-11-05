<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\CoachProfile;
use App\Entity\Reservation;
use App\Entity\Session;
use App\Entity\User;
use App\Http\ApiException;
use App\Http\ApiRequest;
use App\Http\Pagination;
use App\Repository\ActivityRepository;
use App\Repository\GymRepository;
use App\Repository\ReservationRepository;
use App\Repository\ReviewRepository;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use App\Service\CataloguePresenter;
use App\Service\UserPresenter;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration de la plateforme (/api/admin/…) : tableau de bord global, comptes
 * (rôle, blocage), réservations et séances (US-A01 à US-A03). La modération des avis
 * est dans ReviewController ; salles et activités ont leurs propres routes (admin autorisé).
 */
#[OA\Tag(name: 'Administration')]
#[IsGranted(User::ROLE_ADMIN)]
#[Route('/api/admin')]
final class AdminController extends AbstractController
{
    /** Rôles attribuables par l'administrateur (codes de l'API => rôles Symfony). */
    private const ROLES = [
        'sportif' => User::ROLE_SPORTIF,
        'coach' => User::ROLE_COACH,
        'gestionnaire' => User::ROLE_GESTIONNAIRE,
        'admin' => User::ROLE_ADMIN,
    ];

    public function __construct(
        private readonly UserPresenter $users,
        private readonly CataloguePresenter $presenter,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** Vue d'ensemble (US-A01). */
    #[Route('/stats', name: 'api_admin_stats', methods: ['GET'])]
    public function stats(UserRepository $users, GymRepository $gyms, SessionRepository $sessions, ReservationRepository $reservations, ReviewRepository $reviews, ActivityRepository $activities): JsonResponse
    {
        $byRole = array_fill_keys(array_keys(self::ROLES), 0);
        foreach ($users->findAll() as $user) {
            ++$byRole[$this->roleCode($user)];
        }

        return $this->json([
            'utilisateursParRole' => $byRole,
            'nbUtilisateurs' => array_sum($byRole),
            'nbSalles' => $gyms->count([]),
            'nbActivites' => $activities->count([]),
            'nbSeances' => $sessions->count([]),
            'nbSeancesAVenir' => (int) $sessions->createQueryBuilder('s')->select('COUNT(s.id)')
                ->andWhere('s.startAt > :now')->setParameter('now', new \DateTimeImmutable())
                ->getQuery()->getSingleScalarResult(),
            'reservationsParStatut' => $reservations->countByStatus(),
            'avisEnAttente' => $reviews->countPending(),
            'evolution' => $reservations->weeklyCounts(null, new \DateTimeImmutable()),
        ]);
    }

    /** Comptes : ?q= (nom, prénom, e-mail), ?role=sportif|coach|gestionnaire|admin, paginé. */
    #[Route('/utilisateurs', name: 'api_admin_users', methods: ['GET'])]
    public function usersList(Request $request, UserRepository $users): JsonResponse
    {
        $query = $users->createQueryBuilder('u')->orderBy('u.lastName', 'ASC')->addOrderBy('u.firstName', 'ASC');
        $search = trim((string) $request->query->get('q', ''));
        if ($search !== '') {
            $query->andWhere('LOWER(u.lastName) LIKE :q OR LOWER(u.firstName) LIKE :q OR LOWER(u.email) LIKE :q')
                ->setParameter('q', SessionRepository::like($search));
        }
        $role = self::ROLES[(string) $request->query->get('role', '')] ?? null;
        if ($role !== null) {
            // Les rôles sont stockés en JSON (une seule valeur par compte) : recherche textuelle.
            $query->andWhere('u.roles LIKE :role')->setParameter('role', '%"' . $role . '"%');
        }

        return $this->json(Pagination::fromRequest($request)->paginate($query, fn (User $u): array => $this->presentUser($u)));
    }

    /**
     * Modification d'un compte (US-A02) : { "role": "coach", "estBloque": true }.
     * L'administrateur ne peut ni se bloquer ni retirer son propre rôle administrateur.
     */
    #[OA\RequestBody(content: new OA\JsonContent(example: ['role' => 'gestionnaire', 'estBloque' => false]))]
    #[Route('/utilisateurs/{id}', name: 'api_admin_users_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function updateUser(int $id, Request $request, ApiRequest $api): JsonResponse
    {
        $user = $this->em->find(User::class, $id) ?? throw ApiException::notFound('Utilisateur introuvable.');
        $data = $api->json($request);
        $self = $user === $this->getUser();

        if (\array_key_exists('role', $data)) {
            $role = self::ROLES[\is_string($data['role']) ? $data['role'] : ''] ?? throw ApiException::validation(['role' => 'Rôle attendu : sportif, coach, gestionnaire ou admin.']);
            if ($self && $role !== User::ROLE_ADMIN) {
                throw ApiException::conflict('Vous ne pouvez pas retirer votre propre rôle administrateur.');
            }
            $user->setRoles([$role]);
            // Un nouveau coach reçoit un profil vide, à compléter depuis son espace.
            if ($role === User::ROLE_COACH && $user->getCoachProfile() === null) {
                $user->setCoachProfile(new CoachProfile());
                $this->em->persist($user->getCoachProfile());
            }
        }
        if (\array_key_exists('estBloque', $data)) {
            if (!\is_bool($data['estBloque'])) {
                throw ApiException::validation(['estBloque' => 'Valeur attendue : true ou false.']);
            }
            if ($self && $data['estBloque']) {
                throw ApiException::conflict('Vous ne pouvez pas bloquer votre propre compte.');
            }
            $user->setIsBlocked($data['estBloque']);
        }
        if (\array_key_exists('estVerifie', $data) && $data['estVerifie'] === true) {
            $user->setIsVerified(true);
        }
        $this->em->flush();

        return $this->json($this->presentUser($user));
    }

    /**
     * Suppression d'un compte : refusée s'il gère des salles ou anime des séances
     * (à réattribuer d'abord) ; ses réservations, favoris et avis sont supprimés avec lui.
     */
    #[Route('/utilisateurs/{id}', name: 'api_admin_users_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteUser(int $id): JsonResponse
    {
        $user = $this->em->find(User::class, $id) ?? throw ApiException::notFound('Utilisateur introuvable.');
        if ($user === $this->getUser()) {
            throw ApiException::conflict('Vous ne pouvez pas supprimer votre propre compte.');
        }
        if ($user->getManagedGyms()->count() > 0) {
            throw ApiException::conflict('Ce compte gère des salles : confiez-les d’abord à un autre gestionnaire.');
        }
        if ($user->getCoachProfile()?->getSessions()->count() > 0) {
            throw ApiException::conflict('Ce coach anime des séances : bloquez plutôt son compte.');
        }

        $this->em->remove($user);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    /** Toutes les réservations, ?statut=confirmee|annulee|terminee, ?periode=a_venir. */
    #[Route('/reservations', name: 'api_admin_reservations', methods: ['GET'])]
    public function reservations(Request $request, ReservationRepository $reservations): JsonResponse
    {
        $now = new \DateTimeImmutable();

        return $this->json(Pagination::fromRequest($request)->paginate(
            $reservations->forOrganizer(upcomingOnly: $request->query->get('periode') === 'a_venir', now: $now, status: $request->query->get('statut')),
            fn (Reservation $r): array => $this->presenter->reservation($r, $now, withUser: true),
        ));
    }

    /** Toutes les séances : ?periode=a_venir|passees, paginé (ou ?debut=&fin= pour le calendrier). */
    #[Route('/seances', name: 'api_admin_sessions', methods: ['GET'])]
    public function sessions(Request $request, SessionRepository $sessions): JsonResponse
    {
        return $this->json(CoachController::sessionList($request, $sessions, $this->presenter, $sessions->managedBy(null, null, new \DateTimeImmutable()), $request->query->get('periode')));
    }

    /** @return array<string, mixed> */
    private function presentUser(User $user): array
    {
        return $this->users->present($user) + [
            'role' => $this->roleCode($user),
            'estVerifie' => $user->isVerified(),
            'estBloque' => $user->isBlocked(),
            'dateInscription' => $user->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    private function roleCode(User $user): string
    {
        foreach (['admin', 'gestionnaire', 'coach'] as $code) {
            if ($user->hasRole(self::ROLES[$code])) {
                return $code;
            }
        }

        return 'sportif';
    }
}
