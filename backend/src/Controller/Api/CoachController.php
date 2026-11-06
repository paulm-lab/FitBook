<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CoachProfileInput;
use App\Entity\CoachProfile;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\SessionStatus;
use App\Http\ApiException;
use App\Http\ApiRequest;
use App\Http\Pagination;
use App\Repository\AvailabilityRepository;
use App\Repository\CoachProfileRepository;
use App\Repository\ReservationRepository;
use App\Repository\ReviewRepository;
use App\Repository\SessionRepository;
use App\Service\CataloguePresenter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Coachs : liste et fiche publiques, et espace du coach connecté (/api/coach/…) :
 * profil professionnel, séances (calendrier), statistiques et avis reçus.
 */
#[OA\Tag(name: 'Coachs')]
final class CoachController extends AbstractController
{
    public function __construct(
        private readonly CoachProfileRepository $coaches,
        private readonly ReviewRepository $reviews,
        private readonly SessionRepository $sessions,
        private readonly CataloguePresenter $presenter,
    ) {
    }

    #[Security(name: null)]
    #[Route('/api/coaches', name: 'api_coaches_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $items = $this->coaches->published()->getQuery()->getResult();
        $ratings = $this->reviews->ratings(CoachProfile::class, array_map(static fn (CoachProfile $c): ?int => $c->getId(), $items));

        return $this->json(array_map(
            fn (CoachProfile $coach): array => $this->presenter->coach($coach) + ['noteMoyenne' => $ratings[$coach->getId()]['note'] ?? null, 'nbAvis' => $ratings[$coach->getId()]['nbAvis'] ?? 0],
            $items,
        ));
    }

    /** Fiche publique : profil, prochaines séances, disponibilités, note et avis. */
    #[Security(name: null)]
    #[Route('/api/coaches/{id}', name: 'api_coaches_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, AvailabilityRepository $availabilities): JsonResponse
    {
        $coach = $this->coaches->find($id);
        $user = $coach?->getUser();
        if (!$coach || !$user?->isVerified() || $user->isBlocked() || !$user->hasRole(User::ROLE_COACH)) {
            throw ApiException::notFound('Coach introuvable.');
        }

        return $this->json($this->detail($coach, $availabilities));
    }

    /** Profil professionnel du coach connecté (créé vide s'il n'existe pas encore). */
    #[IsGranted(User::ROLE_COACH)]
    #[Route('/api/coach/profil', name: 'api_coach_profile_show', methods: ['GET'])]
    public function profile(EntityManagerInterface $em, AvailabilityRepository $availabilities): JsonResponse
    {
        return $this->json($this->detail($this->ownProfile($em), $availabilities));
    }

    #[IsGranted(User::ROLE_COACH)]
    #[OA\RequestBody(content: new OA\JsonContent(example: ['biographie' => 'Coach diplômée…', 'specialites' => ['Yoga', 'Pilates'], 'experienceAnnees' => 6, 'tarifHoraire' => 45, 'ville' => 'Ixelles', 'certifications' => 'BPJEPS']))]
    #[Route('/api/coach/profil', name: 'api_coach_profile_update', methods: ['PUT'])]
    public function updateProfile(Request $request, ApiRequest $api, EntityManagerInterface $em, AvailabilityRepository $availabilities): JsonResponse
    {
        $profile = $this->ownProfile($em);
        $api->validate(CoachProfileInput::fromArray($api->json($request)))->applyTo($profile);
        $em->flush();

        return $this->json($this->detail($profile, $availabilities));
    }

    /**
     * Séances que le coach anime. ?periode=a_venir|passees, ou ?debut=…&fin=… (ISO 8601) pour
     * la fenêtre du calendrier. Paginé sauf pour le calendrier.
     */
    #[IsGranted(User::ROLE_COACH)]
    #[Route('/api/coach/seances', name: 'api_coach_sessions', methods: ['GET'])]
    public function sessions(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $profile = $this->ownProfile($em);

        return $this->json(self::sessionList($request, $this->sessions, $this->presenter, $this->sessions->managedBy(null, null, new \DateTimeImmutable())
            ->andWhere('s.coach = :coach')->setParameter('coach', $profile), $request->query->get('periode')));
    }

    /** Statistiques du coach (US-C06) : séances, réservations, note moyenne, évolution. */
    #[IsGranted(User::ROLE_COACH)]
    #[Route('/api/coach/stats', name: 'api_coach_stats', methods: ['GET'])]
    public function stats(EntityManagerInterface $em, ReservationRepository $reservations): JsonResponse
    {
        $profile = $this->ownProfile($em);
        $now = new \DateTimeImmutable();
        $upcoming = $this->sessions->managedBy(null, 'a_venir', $now)->andWhere('s.coach = :coach')->setParameter('coach', $profile)
            ->andWhere('s.status = :active')->setParameter('active', SessionStatus::Active)->getQuery()->getResult();
        $counts = $this->sessions->confirmedCounts($upcoming);
        $capacity = array_sum(array_map(static fn (Session $s): int => $s->getCapacity(), $upcoming));

        return $this->json([
            'nbSeancesAVenir' => \count($upcoming),
            'nbInscritsAVenir' => array_sum($counts),
            'tauxRemplissage' => $capacity > 0 ? round(100 * array_sum($counts) / $capacity, 1) : null,
            'note' => $this->presenter->rating($this->reviews->ratingOf($profile)),
            'evolution' => $reservations->weeklyCounts(null, $now, coach: $profile),
        ]);
    }

    /** Avis reçus par le coach ou ses séances (US-C06). */
    #[IsGranted(User::ROLE_COACH)]
    #[Route('/api/coach/avis', name: 'api_coach_reviews', methods: ['GET'])]
    public function reviews(Request $request, EntityManagerInterface $em): JsonResponse
    {
        return $this->json(Pagination::fromRequest($request)->paginate(
            $this->reviews->receivedBy(null, $this->ownProfile($em)),
            fn ($review): array => $this->presenter->review($review, withTarget: true),
        ));
    }

    /**
     * Liste de séances commune aux espaces coach et gestionnaire.
     *
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public static function sessionList(Request $request, SessionRepository $sessions, CataloguePresenter $presenter, QueryBuilder $query, mixed $period): array
    {
        $now = new \DateTimeImmutable();
        $from = self::isoDate($request->query->get('debut'));
        $to = self::isoDate($request->query->get('fin'));

        if ($period === 'a_venir') {
            $query->andWhere('s.startAt > :now')->setParameter('now', $now)->orderBy('s.startAt', 'ASC');
        } elseif ($period === 'passees') {
            $query->andWhere('s.startAt <= :now')->setParameter('now', $now)->orderBy('s.startAt', 'DESC');
        }

        // Fenêtre du calendrier : toutes les séances de la vue, sans pagination.
        if ($from !== null || $to !== null) {
            if ($from !== null) {
                $query->andWhere('s.endAt >= :from')->setParameter('from', $from);
            }
            if ($to !== null) {
                $query->andWhere('s.startAt < :to')->setParameter('to', $to);
            }
            $items = $query->setMaxResults(500)->getQuery()->getResult();
            $counts = $sessions->confirmedCounts($items);

            return array_map(static fn (Session $s): array => $presenter->session($s, $counts[$s->getId()] ?? 0), $items);
        }

        $page = Pagination::fromRequest($request)->paginate($query, static fn (Session $s): Session => $s);
        $counts = $sessions->confirmedCounts($page['items']);
        $page['items'] = array_map(static fn (Session $s): array => $presenter->session($s, $counts[$s->getId()] ?? 0), $page['items']);

        return $page;
    }

    private static function isoDate(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || $value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw ApiException::badRequest('Les dates du calendrier doivent être au format ISO 8601.');
        }
    }

    /** @return array<string, mixed> */
    private function detail(CoachProfile $coach, AvailabilityRepository $availabilities): array
    {
        $upcoming = $this->sessions->upcomingFor($coach, new \DateTimeImmutable());
        $counts = $this->sessions->confirmedCounts($upcoming);

        return $this->presenter->coach($coach) + [
            'note' => $this->presenter->rating($this->reviews->ratingOf($coach)),
            'avis' => array_map(fn ($r): array => $this->presenter->review($r), $this->reviews->visibleFor($coach)->setMaxResults(30)->getQuery()->getResult()),
            'prochainesSeances' => array_map(fn (Session $s): array => $this->presenter->session($s, $counts[$s->getId()] ?? 0), $upcoming),
            'disponibilites' => array_map(fn ($a): array => $this->presenter->availability($a), $availabilities->findBy(['coach' => $coach], ['dayOfWeek' => 'ASC', 'startTime' => 'ASC'])),
            'userId' => $coach->getUser()?->getId(),
        ];
    }

    private function ownProfile(EntityManagerInterface $em): CoachProfile
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new ApiException(401, 'Authentification requise.');
        }
        // Rôle stocké, pas la hiérarchie : un administrateur ne devient pas coach public
        // (un profil serait créé et publié à son nom).
        if (!$user->hasRole(User::ROLE_COACH)) {
            throw ApiException::forbidden('Espace réservé aux coachs.');
        }
        if ($user->getCoachProfile() === null) {
            $user->setCoachProfile(new CoachProfile());
            $em->persist($user->getCoachProfile());
            $em->flush();
        }

        return $user->getCoachProfile();
    }
}
