<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Activity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Création / modification d'une activité (administrateur).
 */
final class ActivityInput
{
    use ReadsJsonFields;

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 50, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    public string $nom = '';

    #[Assert\NotBlank(message: 'La catégorie est obligatoire.')]
    #[Assert\Length(max: 50, maxMessage: 'La catégorie ne peut pas dépasser {{ limit }} caractères.')]
    public string $categorie = '';

    #[Assert\Length(max: 2000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    public ?string $description = null;

    #[Assert\Url(message: 'L’image doit être une adresse web valide.', requireTld: true)]
    #[Assert\Length(max: 255)]
    public ?string $image = null;

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $input = new self();
        $input->nom = self::text($data, 'nom');
        $input->categorie = self::text($data, 'categorie');
        $input->description = self::nullableText($data, 'description');
        $input->image = self::nullableText($data, 'image');

        return $input;
    }

    public function applyTo(Activity $activity): Activity
    {
        return $activity
            ->setName($this->nom)
            ->setCategory($this->categorie)
            ->setDescription($this->description)
            ->setIcon($this->image);
    }
}
