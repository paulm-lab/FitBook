<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Review;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Note (1 à 5) et commentaire d'un avis (RG-07). Le commentaire est stocké tel quel et affiché
 * en texte par le front (jamais interprété comme du HTML : protection XSS).
 */
final class ReviewInput
{
    use ReadsJsonFields;

    #[Assert\NotNull(message: 'La note est obligatoire.')]
    #[Assert\Range(notInRangeMessage: 'La note doit être comprise entre {{ min }} et {{ max }}.', min: Review::RATING_MIN, max: Review::RATING_MAX)]
    public ?int $note = null;

    #[Assert\Length(max: 1000, maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères.')]
    public ?string $commentaire = null;

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $input = new self();
        $input->note = self::int($data, 'note');
        $input->commentaire = self::nullableText($data, 'commentaire');

        return $input;
    }

    public function applyTo(Review $review): Review
    {
        return $review->setRating((int) $this->note)->setComment($this->commentaire);
    }
}
