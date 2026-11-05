<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\SessionLevel;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Création / modification d'une séance (coach, gestionnaire ou administrateur).
 *
 * Dates ISO 8601 avec fuseau, ex. « 2026-11-20T18:30:00+01:00 » (stockées en UTC).
 */
final class SessionInput
{
    use ReadsJsonFields;

    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    public string $titre = '';

    #[Assert\Length(max: 2000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    public ?string $description = null;

    #[Assert\NotNull(message: 'L’activité est obligatoire.')]
    #[Assert\Positive(message: 'L’activité est invalide.')]
    public ?int $activiteId = null;

    #[Assert\Positive(message: 'La salle est invalide.')]
    public ?int $salleId = null;

    #[Assert\Positive(message: 'Le coach est invalide.')]
    public ?int $coachId = null;

    #[Assert\NotNull(message: 'La date de début est obligatoire (format ISO 8601).')]
    #[Assert\GreaterThan('now', message: 'La séance doit commencer dans le futur.')]
    public ?\DateTimeImmutable $debut = null;

    #[Assert\NotNull(message: 'La date de fin est obligatoire (format ISO 8601).')]
    public ?\DateTimeImmutable $fin = null;

    #[Assert\NotNull(message: 'La capacité est obligatoire.')]
    #[Assert\Range(notInRangeMessage: 'La capacité doit être comprise entre {{ min }} et {{ max }}.', min: 1, max: 500)]
    public ?int $capacite = null;

    #[Assert\NotNull(message: 'Le prix est obligatoire (0 pour une séance gratuite).')]
    #[Assert\Range(notInRangeMessage: 'Le prix doit être compris entre {{ min }} et {{ max }} €.', min: 0, max: 1000)]
    public ?string $prix = null;

    #[Assert\Choice(callback: [self::class, 'levels'], message: 'Niveau attendu : debutant, intermediaire ou avance.')]
    public string $niveau = 'debutant';

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $input = new self();
        $input->titre = self::text($data, 'titre');
        $input->description = self::nullableText($data, 'description');
        $input->activiteId = self::int($data, 'activiteId');
        $input->salleId = self::int($data, 'salleId');
        $input->coachId = self::int($data, 'coachId');
        $input->debut = self::dateTime($data, 'debut');
        $input->fin = self::dateTime($data, 'fin');
        $input->capacite = self::int($data, 'capacite');
        $input->prix = self::decimal($data, 'prix');
        $input->niveau = self::text($data, 'niveau') ?: 'debutant';

        return $input;
    }

    /** @return list<string> */
    public static function levels(): array
    {
        return array_map(static fn (SessionLevel $l): string => $l->value, SessionLevel::cases());
    }

    /** La fin doit suivre le début (RG-09), et une séance dure au plus 12 heures. */
    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if ($this->debut === null || $this->fin === null) {
            return;
        }
        if ($this->fin <= $this->debut) {
            $context->buildViolation('La fin doit être postérieure au début.')->atPath('fin')->addViolation();
        } elseif ($this->fin > $this->debut->modify('+12 hours')) {
            $context->buildViolation('Une séance ne peut pas durer plus de 12 heures.')->atPath('fin')->addViolation();
        }
    }

    public function level(): SessionLevel
    {
        return SessionLevel::from($this->niveau);
    }
}
