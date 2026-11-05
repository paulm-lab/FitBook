<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\CoachProfile;
use App\Entity\Gym;
use App\Entity\Session;
use App\Enum\SessionLevel;

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
            'experience' => $coach->getExperienceYears() !== null ? $coach->getExperienceYears() . ' ans' : null,
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
            'activite' => $activity ? ['id' => $activity->getId(), 'nom' => $activity->getName()] : null,
            'coach' => $coach ? ['id' => $coach->getId(), 'nom' => $coach->getUser()?->getLastName(), 'prenom' => $coach->getUser()?->getFirstName()] : null,
            'salle' => $gym ? ['id' => $gym->getId(), 'nom' => $gym->getName(), 'adresse' => $gym->getAddress() . ', ' . $gym->getCity()] : null,
        ];
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

        return $ordered === [] ? 'Tous niveaux' : implode(', ', array_map(static fn (string $l): string => self::LEVEL_LABELS[$l], $ordered));
    }
}
