<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Availability;
use App\Entity\CoachProfile;
use App\Entity\Gym;
use App\Entity\Reservation;
use App\Entity\Review;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Enum\SessionLevel;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Représentation JSON du catalogue (activités, coachs, salles, séances).
 *
 * Les clés restent celles attendues par le front Angular actuel (nom, titre, placesDisponibles…)
 * jusqu'à sa refonte (Phase 5). Les anciens champs qui n'existent plus dans le modèle sont
 * **calculés** à partir des séances : durée d'une activité, niveaux proposés, capacité d'une salle.
 */
final class CataloguePresenter
{
    /** Fuseau des salles (Bruxelles) pour les champs « jour » et « heure » affichables tels quels. */
    public const LOCAL_TIMEZONE = 'Europe/Brussels';

    private const LEVEL_LABELS = [
        'debutant' => 'Débutant',
        'intermediaire' => 'Intermédiaire',
        'avance' => 'Avancé',
    ];

    /** Libellés calculés (niveaux, expérience) traduits dans la langue de la requête. */
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /** @return array<string, mixed> */
    public function activity(Activity $activity): array
    {
        $sessions = $activity->getSessions()->toArray();

        return [
            'id' => $activity->getId(),
            'nom' => $activity->getName(),
            'description' => $activity->getDescription(),
            'categorie' => $activity->getCategory(),
            // Durée moyenne des séances, en minutes (null si aucune séance).
            'duree' => $this->averageDuration($sessions),
            // Niveaux réellement proposés, ex. « Débutant, Avancé ».
            'niveau' => $this->levelsLabel($sessions),
            'image' => $activity->getIcon(),
        ];
    }

    /** @return array<string, mixed> */
    public function coach(CoachProfile $coach): array
    {
        $user = $coach->getUser();

        return [
            'id' => $coach->getId(),
            'nom' => $user?->getLastName(),
            'prenom' => $user?->getFirstName(),
            'specialite' => implode(', ', $coach->getSpecialities()),
            'specialites' => $coach->getSpecialities(),
            'description' => $coach->getBio(),
            'biographie' => $coach->getBio(),
            'experience' => $coach->getExperienceYears() !== null ? $this->translator->trans('{count} ans', ['{count}' => $coach->getExperienceYears(), '%count%' => $coach->getExperienceYears()]) : null,
            'experienceAnnees' => $coach->getExperienceYears(),
            'certification' => $coach->getCertifications(),
            'tarifHoraire' => $coach->getHourlyRate(),
            'ville' => $coach->getCity(),
            'photo' => $user?->getAvatar(),
        ];
    }

    /** @return array<string, mixed> */
    public function gym(Gym $gym): array
    {
        $capacities = array_map(static fn (Session $s): int => $s->getCapacity(), $gym->getSessions()->toArray());

        return [
            'id' => $gym->getId(),
            'nom' => $gym->getName(),
            'adresse' => sprintf('%s, %s %s', $gym->getAddress(), $gym->getPostalCode(), $gym->getCity()),
            'ville' => $gym->getCity(),
            'codePostal' => $gym->getPostalCode(),
            'latitude' => $gym->getLatitude(),
            'longitude' => $gym->getLongitude(),
            'telephone' => $gym->getPhone(),
            'email' => $gym->getEmail(),
            'horaires' => $gym->getOpeningHours(),
            // Capacité d'accueil : la plus grande séance proposée dans la salle.
            'capacite' => $capacities === [] ? null : max($capacities),
            'description' => $gym->getDescription(),
            'equipements' => null,
            'image' => $gym->getPhotos()[0] ?? null,
            'photos' => $gym->getPhotos(),
        ];
    }

    /**
     * @param int $confirmedCount places déjà prises (compté en une requête par l'appelant)
     *
     * @return array<string, mixed>
     */
    public function session(Session $session, int $confirmedCount): array
    {
        $activity = $session->getActivity();
        $coach = $session->getCoach();
        $gym = $session->getGym();

        // Dates stockées en UTC : ISO 8601 avec décalage, converties par le navigateur.
        // Jour et heures « locales » calculés à l'heure de Bruxelles, où se trouvent les salles.
        $localStart = $session->getStartAt()?->setTimezone(new \DateTimeZone(self::LOCAL_TIMEZONE));
        $localEnd = $session->getEndAt()?->setTimezone(new \DateTimeZone(self::LOCAL_TIMEZONE));

        return [
            'id' => $session->getId(),
            'titre' => $session->getTitle(),
            'description' => $session->getDescription(),
            'dateDebut' => $session->getStartAt()?->format(\DATE_ATOM),
            'dateFin' => $session->getEndAt()?->format(\DATE_ATOM),
            'dateSeance' => $localStart?->format('Y-m-d'),
            'heureDebut' => $localStart?->format('H:i'),
            'heureFin' => $localEnd?->format('H:i'),
            'capaciteMax' => $session->getCapacity(),
            'placesDisponibles' => max(0, $session->getCapacity() - $confirmedCount),
            'prix' => $session->getPrice(),
            'niveau' => $session->getLevel()->value,
            'statut' => $session->getStatus()->value,
            'image' => $activity?->getIcon(),
            'activite' => $activity ? ['id' => $activity->getId(), 'nom' => $activity->getName(), 'categorie' => $activity->getCategory()] : null,
            'coach' => $coach ? ['id' => $coach->getId(), 'nom' => $coach->getUser()?->getLastName(), 'prenom' => $coach->getUser()?->getFirstName(), 'photo' => $coach->getUser()?->getAvatar()] : null,
            'salle' => $gym ? ['id' => $gym->getId(), 'nom' => $gym->getName(), 'adresse' => $gym->getAddress() . ', ' . $gym->getCity(), 'ville' => $gym->getCity(), 'latitude' => $gym->getLatitude(), 'longitude' => $gym->getLongitude()] : null,
        ];
    }

    /**
     * Note moyenne des avis visibles : { "note": 4.3, "nbAvis": 12 } (note null sans avis).
     *
     * @param array{note: float|null, nbAvis: int}|null $rating
     *
     * @return array{note: float|null, nbAvis: int}
     */
    public function rating(?array $rating): array
    {
        return ['note' => $rating['note'] ?? null, 'nbAvis' => $rating['nbAvis'] ?? 0];
    }

    /**
     * Avis publié. Le commentaire est renvoyé brut : le front l'affiche en texte, jamais en HTML.
     *
     * @return array<string, mixed>
     */
    public function review(Review $review, bool $withTarget = false): array
    {
        $author = $review->getAuthor();
        $data = [
            'id' => $review->getId(),
            'note' => $review->getRating(),
            'commentaire' => $review->getComment(),
            'date' => $review->getCreatedAt()->format(\DATE_ATOM),
            // Prénom et initiale du nom seulement : l'auteur reste discret.
            'auteur' => $author ? trim($author->getFirstName() . ' ' . mb_substr((string) $author->getLastName(), 0, 1) . '.') : null,
            'auteurId' => $author?->getId(),
            'estModere' => $review->isModerated(),
            'estVisible' => $review->isVisible(),
        ];

        if ($withTarget) {
            $data['cible'] = $this->target($review->getTarget());
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function availability(Availability $availability): array
    {
        return [
            'id' => $availability->getId(),
            'coachId' => $availability->getCoach()?->getId(),
            'salleId' => $availability->getGym()?->getId(),
            'recurrent' => $availability->isRecurring(),
            'jourSemaine' => $availability->getDayOfWeek(),
            'date' => $availability->getDate()?->format('Y-m-d'),
            'heureDebut' => $availability->getStartTime()?->format('H:i'),
            'heureFin' => $availability->getEndTime()?->format('H:i'),
        ];
    }

    /**
     * Réservation vue par le sportif (ou par l'organisateur, avec $withUser).
     *
     * Le jeton du QR code est un secret du sportif : il n'est renvoyé qu'à lui ($viewer),
     * jamais au coach, au gestionnaire ni à l'administrateur.
     *
     * @return array<string, mixed>
     */
    public function reservation(Reservation $reservation, \DateTimeImmutable $now, bool $withUser = false, ?int $confirmedCount = null, ?User $viewer = null): array
    {
        $session = $reservation->getSession();
        $isOwner = $viewer !== null && $viewer->getId() !== null && $reservation->getUser()?->getId() === $viewer->getId();
        $data = [
            'id' => $reservation->getId(),
            'statut' => $reservation->getStatus()->value,
            'dateReservation' => $reservation->getCreatedAt()->format(\DATE_ATOM),
            'dateAnnulation' => $reservation->getCancelledAt()?->format(\DATE_ATOM),
            'annulable' => $reservation->isCancellable($now),
            'qrToken' => $isOwner && $reservation->getStatus() === ReservationStatus::Confirmee ? $reservation->getQrToken() : null,
            'seance' => $session ? $this->session($session, $confirmedCount ?? $session->getConfirmedCount()) : null,
        ];

        if ($withUser) {
            $user = $reservation->getUser();
            $data['sportif'] = $user ? [
                'id' => $user->getId(),
                'nom' => $user->getLastName(),
                'prenom' => $user->getFirstName(),
                'email' => $user->getEmail(),
                'telephone' => $user->getPhone(),
            ] : null;
        }

        return $data;
    }

    /**
     * Cible d'un favori ou d'un avis : { "type": "salle", "id": 3, "nom": "…" }.
     *
     * @return array<string, mixed>|null
     */
    public function target(Gym|CoachProfile|Session|null $target): ?array
    {
        return match (true) {
            $target instanceof Gym => ['type' => 'salle', 'id' => $target->getId(), 'nom' => $target->getName(), 'image' => $target->getPhotos()[0] ?? null],
            $target instanceof CoachProfile => ['type' => 'coach', 'id' => $target->getId(), 'nom' => $target->getUser()?->getFullName(), 'image' => $target->getUser()?->getAvatar()],
            $target instanceof Session => ['type' => 'seance', 'id' => $target->getId(), 'nom' => $target->getTitle(), 'image' => $target->getActivity()?->getIcon(), 'dateDebut' => $target->getStartAt()?->format(\DATE_ATOM)],
            default => null,
        };
    }

    /** @param list<Session> $sessions */
    private function averageDuration(array $sessions): ?int
    {
        $durations = array_filter(array_map(
            static fn (Session $s): ?int => $s->getStartAt() && $s->getEndAt()
                ? intdiv($s->getEndAt()->getTimestamp() - $s->getStartAt()->getTimestamp(), 60)
                : null,
            $sessions,
        ));

        return $durations === [] ? null : (int) round(array_sum($durations) / \count($durations));
    }

    /** @param list<Session> $sessions */
    private function levelsLabel(array $sessions): string
    {
        $levels = array_unique(array_map(static fn (Session $s): string => $s->getLevel()->value, $sessions));
        // Ordre naturel : débutant, intermédiaire, avancé.
        $ordered = array_values(array_filter(
            array_map(static fn (SessionLevel $l): string => $l->value, SessionLevel::cases()),
            static fn (string $l): bool => \in_array($l, $levels, true),
        ));

        return $ordered === []
            ? $this->translator->trans('Tous niveaux')
            : implode(', ', array_map(fn (string $l): string => $this->translator->trans(self::LEVEL_LABELS[$l]), $ordered));
    }
}
