<?php

declare(strict_types=1);

namespace App\Http;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pagination commune à toutes les listes : ?page=1&parPage=20 (50 au maximum).
 *
 * Réponse : { "items": [...], "page": 1, "parPage": 20, "total": 134, "pages": 7 }
 */
final class Pagination
{
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 50;

    private function __construct(
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public static function fromRequest(Request $request, int $defaultPerPage = self::DEFAULT_PER_PAGE): self
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, $request->query->getInt('parPage', $defaultPerPage)));

        return new self($page, $perPage);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Pagine une requête Doctrine en base (COUNT + LIMIT/OFFSET).
     *
     * @param callable(mixed): array<string, mixed> $present transforme chaque résultat en tableau JSON
     *
     * @return array<string, mixed>
     */
    public function paginate(QueryBuilder $query, callable $present): array
    {
        $query->setFirstResult($this->offset())->setMaxResults($this->perPage);
        $paginator = new Paginator($query, fetchJoinCollection: true);

        return $this->format(array_map($present, iterator_to_array($paginator)), \count($paginator));
    }

    /**
     * Pagine une liste déjà chargée en mémoire (ex. après un filtre par distance).
     *
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    public function slice(array $items): array
    {
        return $this->format(\array_slice($items, $this->offset(), $this->perPage), \count($items));
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private function format(array $items, int $total): array
    {
        return [
            'items' => array_values($items),
            'page' => $this->page,
            'parPage' => $this->perPage,
            'total' => $total,
            'pages' => (int) ceil($total / $this->perPage),
        ];
    }
}
