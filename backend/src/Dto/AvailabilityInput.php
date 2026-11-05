<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Availability;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Créneau de disponibilité (US-C03, US-G03) : récurrent (jour de la semaine, 1 = lundi)
 * ou ponctuel (date). Heures locales « HH:MM ».
 */
final class AvailabilityInput
{
    use ReadsJsonFields;

    public bool $recurrent = true;

    #[Assert\Range(notInRangeMessage: 'Le jour doit être compris entre {{ min }} (lundi) et {{ max }} (dimanche).', min: 1, max: 7)]
    public ?int $jourSemaine = null;

    public ?\DateTimeImmutable $date = null;

    #[Assert\NotBlank(message: 'L’heure de début est obligatoire.')]
    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'Heure attendue au format HH:MM.')]
    public string $heureDebut = '';

    #[Assert\NotBlank(message: 'L’heure de fin est obligatoire.')]
    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'Heure attendue au format HH:MM.')]
    public string $heureFin = '';

    /** Réservé au gestionnaire : créneau d'une de ses salles (sinon : créneau du coach connecté). */
    #[Assert\Positive(message: 'La salle est invalide.')]
    public ?int $salleId = null;

    private bool $invalidDate = false;

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $input = new self();
        $input->recurrent = self::bool($data, 'recurrent', true);
        $input->jourSemaine = self::int($data, 'jourSemaine');
        $date = self::text($data, 'date');
        if ($date !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            $input->date = $parsed && $parsed->format('Y-m-d') === $date ? $parsed : null;
            $input->invalidDate = $input->date === null;
        }
        $input->heureDebut = self::text($data, 'heureDebut');
        $input->heureFin = self::text($data, 'heureFin');
        $input->salleId = self::int($data, 'salleId');

        return $input;
    }

    #[Assert\Callback]
    public function validateSlot(ExecutionContextInterface $context): void
    {
        if ($this->recurrent && $this->jourSemaine === null) {
            $context->buildViolation('Choisissez le jour de la semaine.')->atPath('jourSemaine')->addViolation();
        }
        if (!$this->recurrent && $this->date === null) {
            $context->buildViolation($this->invalidDate ? 'Date attendue au format AAAA-MM-JJ.' : 'Choisissez la date du créneau.')->atPath('date')->addViolation();
        }
        if ($this->heureDebut !== '' && $this->heureFin !== '' && $this->heureFin <= $this->heureDebut) {
            $context->buildViolation('L’heure de fin doit suivre l’heure de début.')->atPath('heureFin')->addViolation();
        }
    }

    public function applyTo(Availability $availability): Availability
    {
        return $availability
            ->setIsRecurring($this->recurrent)
            ->setDayOfWeek($this->recurrent ? $this->jourSemaine : null)
            ->setDate($this->recurrent ? null : $this->date)
            ->setStartTime(new \DateTimeImmutable('1970-01-01 ' . $this->heureDebut))
            ->setEndTime(new \DateTimeImmutable('1970-01-01 ' . $this->heureFin));
    }
}
