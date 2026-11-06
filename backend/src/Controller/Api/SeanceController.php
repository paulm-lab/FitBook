<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\SessionInput;
use App\Entity\Activity;
use App\Entity\CoachProfile;
use App\Entity\Gym;
use App\Entity\Reservation;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Enum\SessionStatus;
use App\Http\ApiException;
use App\Http\ApiRequest;
use App\Repository\ReservationRepository;
use App\Repository\ReviewRepository;
use App\Repository\SessionRepository;
use App\Security\Voter\GymVoter;
use App\Security\Voter\SessionVoter;
use App\Service\CataloguePresenter;
use App\Service\ReservationService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Séances : liste et fiche publiques, création / modification / annulation par le coach
 * qui l'anime, le gestionnaire de la salle ou l'administrateur (RG-09 à RG-12).
 */
#[OA\Tag(name: 'Séances')]
#[Route('/api/seances')]
final class SeanceController extends AbstractController
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly CataloguePresenter $presenter,
        private readonly ApiRequest $api,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** Séances actives à venir (filtres simples ; la recherche avancée est /api/recherche). */
    #[OA\Parameter(name: 'activite', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'lieu', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'date', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[Security(name: null)]
    #[Route('', name: 'api_seances_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $day = null;
        $date = $request->query->get('date');
        if ($date !== null && $date !== '') {
            // Jour local (Bruxelles) : SessionRepository en convertit les bornes en UTC.
            $day = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $date, new \DateTimeZone(CataloguePresenter::LOCAL_TIMEZONE));
            if (!$day || $day->format('Y-m-d') !== $date) {
                throw ApiException::badRequest('Le filtre date doit être au format YYYY-MM-DD.');
            }
        }

        $items = $this->sessions->findUpcoming(
            new \DateTimeImmutable(),
            trim((string) $request->query->get('activite', '')),
            trim((string) $request->query->get('lieu', '')),
            $day,
        );
        $counts = $this->sessions->confirmedCounts($items);

        return $this->json(array_map(
            fn (Session $s): array => $this->presenter->session($s, $counts[$s->getId()] ?? 0),
            $items,
        ));
    }

    /**
     * Fiche d'une séance active et à venir, avec la note du coach (ou de la salle) et les avis
     * de la séance. Les séances passées ou annulées (ou dont le coach est bloqué) restent
     * visibles par leurs organisateurs et par les sportifs qui les ont réservées (pour l'avis).
     *
     * « dejaReservee » : le sportif connecté a une réservation confirmée sur cette séance.
     */
    #[Security(name: null)]
    #[Route('/{id}', name: 'api_seances_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, ReviewRepository $reviews, ReservationRepository $reservations): JsonResponse
    {
        $item = $this->sessions->find($id);
        $user = $this->getUser();
        $reservation = $item && $user instanceof User ? $reservations->findOneBy(['user' => $user, 'session' => $item]) : null;
        $public = $item && $item->getStatus() === SessionStatus::Active && $item->getStartAt() > new \DateTimeImmutable() && $item->hasAvailableCoach();
        if (!$item || (!$public && $reservation === null && !$this->isGranted(SessionVoter::EDIT, $item))) {
            throw ApiException::notFound('Séance introuvable.');
        }

        $counts = $this->sessions->confirmedCounts([$item]);
        $owner = $item->getCoach() ?? $item->getGym();

        return $this->json($this->presenter->session($item, $counts[$item->getId()] ?? 0) + [
            'noteOrganisateur' => $this->presenter->rating($owner ? $reviews->ratingOf($owner) : null),
            'note' => $this->presenter->rating($reviews->ratingOf($item)),
            'avis' => array_map(
                fn ($review): array => $this->presenter->review($review),
                $reviews->visibleFor($item)->setMaxResults(20)->getQuery()->getResult(),
            ),
            'peutModifier' => $this->isGranted(SessionVoter::EDIT, $item),
            'dejaReservee' => $reservation?->getStatus() === ReservationStatus::Confirmee,
        ]);
    }

    /** Création par un coach (séance rattachée à son profil), un gestionnaire (dans une de ses salles) ou l'admin. */
    #[OA\RequestBody(content: new OA\JsonContent(example: ['titre' => 'Yoga du soir', 'activiteId' => 1, 'salleId' => 2, 'debut' => '2026-11-20T18:30:00+01:00', 'fin' => '2026-11-20T19:30:00+01:00', 'capacite' => 12, 'prix' => 12, 'niveau' => 'debutant']))]
    #[OA\Response(response: 201, description: 'Séance créée')]
    #[OA\Response(response: 422, description: 'Champs invalides')]
    #[Route('', name: 'api_seances_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        if (!$this->isGranted(User::ROLE_COACH) && !$this->isGranted(User::ROLE_GESTIONNAIRE)) {
            throw ApiException::forbidden('Seuls les coachs et les gestionnaires créent des séances.');
        }

        $input = $this->api->validate(SessionInput::fromArray($this->api->json($request)));
        $session = $this->apply(new Session(), $input, $user);
        $this->em->persist($session);
        $this->em->flush();

        return $this->json($this->presenter->session($session, 0), 201);
    }

    #[Route('/{id}', name: 'api_seances_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, Request $request, ReservationRepository $reservations): JsonResponse
    {
        $session = $this->find($id);
        $this->denyAccessUnlessGranted(SessionVoter::EDIT, $session);
        if ($session->getStatus() === SessionStatus::Annulee) {
            throw ApiException::conflict('Une séance annulée ne peut plus être modifiée.');
        }
        $input = $this->api->validate(SessionInput::fromArray($this->api->json($request)));

        // Séance verrouillée (même verrou que sp_reserver_seance) : aucune réservation ne peut
        // s'intercaler entre le comptage des inscrits et la baisse de capacité.
        $this->em->beginTransaction();
        try {
            $this->em->refresh($session, LockMode::PESSIMISTIC_WRITE);
            if ($session->getStatus() === SessionStatus::Annulee) {
                throw ApiException::conflict('Une séance annulée ne peut plus être modifiée.');
            }
            $taken = $this->sessions->confirmedCounts([$session])[$session->getId()] ?? 0;
            if ($input->capacite !== null && $input->capacite < $taken) {
                // Message à paramètre : traduit ici (le listener de traduction ne connaît pas {count}).
                throw ApiException::validation(['capacite' => $this->translator->trans(
                    '{count} sportifs sont déjà inscrits : la capacité ne peut pas être inférieure.',
                    ['{count}' => $taken],
                )]);
            }

            $previousStart = $session->getStartAt();
            $this->apply($session, $input, $this->currentUser());
            $this->em->flush();
            // Nouvel horaire : le rappel de 24 h doit repartir pour chaque inscrit.
            if ($previousStart != $session->getStartAt()) {
                $reservations->resetReminders($session);
            }
            $this->em->commit();
        } catch (\Throwable $exception) {
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }
            throw $exception;
        }

        return $this->json($this->presenter->session($session, $taken));
    }

    /**
     * Suppression définitive d'une séance à venir, sans réservation (hors annulées) ni avis :
     * sinon la suppression effacerait en cascade l'historique des sportifs. La séance doit
     * alors être annulée (les sportifs sont prévenus).
     */
    #[Route('/{id}', name: 'api_seances_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $session = $this->find($id);
        $this->denyAccessUnlessGranted(SessionVoter::DELETE, $session);
        if ($session->getStartAt() <= new \DateTimeImmutable()) {
            throw ApiException::conflict('Cette séance a déjà commencé : elle fait partie de l’historique et ne peut pas être supprimée.');
        }
        ['reservations' => $reserved, 'reviews' => $reviewed] = $this->sessions->historyCounts($session);
        if ($reserved > 0) {
            throw ApiException::conflict('Des sportifs ont réservé cette séance : annulez-la plutôt pour les prévenir.');
        }
        if ($reviewed > 0) {
            throw ApiException::conflict('Cette séance a reçu des avis : annulez-la plutôt que de la supprimer.');
        }

        $this->em->remove($session);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    /** Annulation (RG-12) : réservations confirmées annulées et sportifs prévenus par e-mail. */
    #[Route('/{id}/annuler', name: 'api_seances_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id, ReservationService $reservations): JsonResponse
    {
        $session = $this->find($id);
        $this->denyAccessUnlessGranted(SessionVoter::EDIT, $session);
        $notified = $reservations->cancelSession($session);

        return $this->json($this->presenter->session($session, 0) + ['sportifsPrevenus' => $notified]);
    }

    /** Liste des inscrits (US-C05, US-G04). */
    #[Route('/{id}/inscrits', name: 'api_seances_attendees', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function attendees(int $id, ReservationRepository $reservations): JsonResponse
    {
        $session = $this->find($id);
        $this->denyAccessUnlessGranted(SessionVoter::VIEW_RESERVATIONS, $session);
        $now = new \DateTimeImmutable();
        $count = $this->sessions->confirmedCounts([$session])[$session->getId()] ?? 0;

        $items = $reservations->createQueryBuilder('r')
            ->join('r.user', 'u')->addSelect('u')
            ->andWhere('r.session = :session')->setParameter('session', $session)
            ->orderBy('r.status', 'ASC')->addOrderBy('u.lastName', 'ASC')
            ->getQuery()->getResult();

        return $this->json([
            'seance' => $this->presenter->session($session, $count),
            'inscrits' => array_map(
                fn (Reservation $r): array => $this->presenter->reservation($r, $now, withUser: true, confirmedCount: $count),
                $items,
            ),
            'nbConfirmes' => \count(array_filter($items, static fn (Reservation $r): bool => $r->getStatus() !== ReservationStatus::Annulee)),
        ]);
    }

    /**
     * Applique les champs validés à la séance et contrôle les rattachements :
     * - coach : la séance est la sienne (son profil) ; il ne choisit pas la salle (création
     *   sans salle, salle inchangée en modification) : c'est le rôle du gestionnaire ;
     * - gestionnaire : la salle doit être une de ses salles ;
     * - administrateur : libre, mais une salle ou un coach (RG-10).
     */
    private function apply(Session $session, SessionInput $input, User $user): Session
    {
        $activity = $this->em->find(Activity::class, (int) $input->activiteId)
            ?? throw ApiException::validation(['activiteId' => 'Cette activité n’existe pas.']);
        $gym = $input->salleId !== null
            ? ($this->em->find(Gym::class, $input->salleId) ?? throw ApiException::validation(['salleId' => 'Cette salle n’existe pas.']))
            : null;
        $coach = $input->coachId !== null
            ? ($this->em->find(CoachProfile::class, $input->coachId) ?? throw ApiException::validation(['coachId' => 'Ce coach n’existe pas.']))
            : null;

        $isAdmin = $this->isGranted(User::ROLE_ADMIN);
        if (!$isAdmin && $user->hasRole(User::ROLE_COACH)) {
            // Un coach ne crée que ses propres séances (une séance existante garde son coach).
            $coach = $session->getId() !== null ? $session->getCoach() : $user->getCoachProfile();
            if ($coach === null || $coach->getUser() !== $user) {
                throw ApiException::forbidden('Complétez d’abord votre profil de coach.');
            }
            $currentGym = $session->getId() !== null ? $session->getGym() : null;
            if (!$user->hasRole(User::ROLE_GESTIONNAIRE) && $gym?->getId() !== $currentGym?->getId()) {
                throw ApiException::validation(['salleId' => $session->getId() === null
                    ? 'Un coach crée ses séances sans salle : seul le gestionnaire d’une salle y programme des séances.'
                    : 'Seul le gestionnaire de la salle peut changer la salle de cette séance.']);
            }
        }
        if (!$isAdmin && $user->hasRole(User::ROLE_GESTIONNAIRE)) {
            if ($gym === null) {
                throw ApiException::validation(['salleId' => 'Choisissez une de vos salles.']);
            }
            if (!$this->isGranted(GymVoter::EDIT, $gym)) {
                throw ApiException::forbidden('Vous ne gérez pas cette salle.');
            }
        }
        if ($gym === null && $coach === null) {
            throw ApiException::validation(['salleId' => 'Une séance a lieu dans une salle et/ou avec un coach.']);
        }

        return $session
            ->setTitle($input->titre)
            ->setDescription($input->description)
            ->setActivity($activity)
            ->setGym($gym)
            ->setCoach($coach)
            ->setStartAt($input->debut)
            ->setEndAt($input->fin)
            ->setCapacity((int) $input->capacite)
            ->setPrice(number_format((float) $input->prix, 2, '.', ''))
            ->setLevel($input->level());
    }

    private function find(int $id): Session
    {
        return $this->sessions->find($id) ?? throw ApiException::notFound('Séance introuvable.');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new ApiException(401, 'Authentification requise.');
        }

        return $user;
    }
}
