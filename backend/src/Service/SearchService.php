<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\SearchCriteria;
use App\Entity\CoachProfile;
use App\Entity\Gym;
use App\Entity\Session;
use App\Http\Pagination;
use App\Repository\CoachProfileRepository;
use App\Repository\GymRepository;
use App\Repository\ReviewRepository;
use App\Repository\SessionRepository;

/**
 * Recherche avancée paginée : séances, salles ou coachs (EF-01, US-V01 à US-V03).
 *
 * Les filtres simples (texte, ville, activité, dates, prix, niveau) sont faits en SQL.
 * La note moyenne et la distance sont calculées : quand un de ces critères est demandé
 * (filtre ou tri), les résultats SQL sont complétés, filtrés et triés en PHP puis paginés
 * en mémoire. Les volumes restent faibles (quelques centaines de séances à venir).
 *
 * Note d'une séance : celle de son coach, à défaut celle de sa salle (une séance future
 * n'a pas encore d'avis propres).
 */
final class SearchService
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly GymRepository $gyms,
        private readonly CoachProfileRepository $coaches,
        private readonly ReviewRepository $reviews,
        private readonly CataloguePresenter $presenter,
    ) {
    }

    /** @return array<string, mixed> page de résultats { items, page, parPage, total, pages } */
    public function search(SearchCriteria $criteria, Pagination $pagination, \DateTimeImmutable $now): array
    {
        return match ($criteria->type) {
            'salles' => $this->searchGyms($criteria, $pagination),
            'coachs' => $this->searchCoaches($criteria, $pagination),
            default => $this->searchSessions($criteria, $pagination, $now),
        };
    }

    /** @return array<string, mixed> */
    private function searchSessions(SearchCriteria $criteria, Pagination $pagination, \DateTimeImmutable $now): array
    {
        /** @var list<Session> $items */
        $items = $this->sessions->search($criteria, $now)->getQuery()->getResult();
        $counts = $this->sessions->confirmedCounts($items);
        $coachRatings = $this->reviews->ratings(CoachProfile::class, array_map(static fn (Session $s): ?int => $s->getCoach()?->getId(), $items));
        $gymRatings = $this->reviews->ratings(Gym::class, array_map(static fn (Session $s): ?int => $s->getGym()?->getId(), $items));

        $rows = [];
        foreach ($items as $session) {
            $rating = $coachRatings[$session->getCoach()?->getId() ?? 0] ?? $gymRatings[$session->getGym()?->getId() ?? 0] ?? null;
            $row = $this->presenter->session($session, $counts[$session->getId()] ?? 0)
                + ['noteMoyenne' => $rating['note'] ?? null, 'nbAvis' => $rating['nbAvis'] ?? 0]
                + ['distanceKm' => $this->distance($criteria, $session->getGym())];
            $rows[] = $row;
        }

        return $pagination->slice($this->filterAndSort($rows, $criteria));
    }

    /** @return array<string, mixed> */
    private function searchGyms(SearchCriteria $criteria, Pagination $pagination): array
    {
        /** @var list<Gym> $items */
        $items = $this->gyms->search($criteria)->getQuery()->getResult();
        $ratings = $this->reviews->ratings(Gym::class, array_map(static fn (Gym $g): ?int => $g->getId(), $items));

        $rows = array_map(fn (Gym $gym): array => $this->presenter->gym($gym)
            + ['noteMoyenne' => $ratings[$gym->getId()]['note'] ?? null, 'nbAvis' => $ratings[$gym->getId()]['nbAvis'] ?? 0]
            + ['distanceKm' => $this->distance($criteria, $gym)], $items);

        return $pagination->slice($this->filterAndSort($rows, $criteria));
    }

    /** @return array<string, mixed> */
    private function searchCoaches(SearchCriteria $criteria, Pagination $pagination): array
    {
        /** @var list<CoachProfile> $items */
        $items = $this->coaches->search($criteria)->getQuery()->getResult();
        $ratings = $this->reviews->ratings(CoachProfile::class, array_map(static fn (CoachProfile $c): ?int => $c->getId(), $items));

        $rows = array_map(fn (CoachProfile $coach): array => $this->presenter->coach($coach)
            + ['noteMoyenne' => $ratings[$coach->getId()]['note'] ?? null, 'nbAvis' => $ratings[$coach->getId()]['nbAvis'] ?? 0]
            + ['distanceKm' => null], $items);

        return $pagination->slice($this->filterAndSort($rows, $criteria));
    }

    /**
     * Filtres calculés (note minimale, rayon) puis tri. Un tri stable conserve l'ordre SQL
     * en cas d'égalité.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function filterAndSort(array $rows, SearchCriteria $criteria): array
    {
        if ($criteria->noteMin !== null) {
            $rows = array_filter($rows, static fn (array $r): bool => $r['noteMoyenne'] !== null && $r['noteMoyenne'] >= $criteria->noteMin);
        }
        // Les coachs n'ont pas de position (distanceKm toujours nulle) : pas de filtre par rayon.
        if ($criteria->hasLocation() && $criteria->rayon !== null && $criteria->type !== 'coachs') {
            $rows = array_filter($rows, static fn (array $r): bool => $r['distanceKm'] !== null && $r['distanceKm'] <= $criteria->rayon);
        }
        $rows = array_values($rows);

        $compare = match ($criteria->tri) {
            // Les mieux notés d'abord ; sans note, en fin de liste.
            'note' => static fn (array $a, array $b): int => ($b['noteMoyenne'] ?? -1) <=> ($a['noteMoyenne'] ?? -1),
            'distance' => static fn (array $a, array $b): int => ($a['distanceKm'] ?? \PHP_INT_MAX) <=> ($b['distanceKm'] ?? \PHP_INT_MAX),
            'prix' => static fn (array $a, array $b): int => (float) ($a['prix'] ?? $a['tarifHoraire'] ?? 0) <=> (float) ($b['prix'] ?? $b['tarifHoraire'] ?? 0),
            default => null,
        };
        if ($compare !== null) {
            usort($rows, $compare);
        }

        return $rows;
    }

    /** Distance à vol d'oiseau (formule de haversine), en km arrondis au dixième. */
    private function distance(SearchCriteria $criteria, ?Gym $gym): ?float
    {
        if (!$criteria->hasLocation() || $gym?->getLatitude() === null || $gym->getLongitude() === null) {
            return null;
        }

        $lat1 = deg2rad((float) $criteria->lat);
        $lat2 = deg2rad((float) $gym->getLatitude());
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad((float) $gym->getLongitude() - (float) $criteria->lng);
        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return round(2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($h))), 1);
    }
}
