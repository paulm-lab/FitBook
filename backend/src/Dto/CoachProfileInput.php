<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\CoachProfile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Profil professionnel du coach (US-C01) : affiché sur sa fiche publique.
 */
final class CoachProfileInput
{
    use ReadsJsonFields;

    #[Assert\Length(max: 3000, maxMessage: 'La bio ne peut pas dépasser {{ limit }} caractères.')]
    public ?string $biographie = null;

    /** @var list<string> */
    #[Assert\Count(max: 10, maxMessage: '{{ limit }} spécialités au maximum.')]
    #[Assert\All([new Assert\Length(max: 50, maxMessage: 'Une spécialité ne dépasse pas {{ limit }} caractères.')])]
    public array $specialites = [];

    #[Assert\Range(notInRangeMessage: 'L’expérience doit être comprise entre {{ min }} et {{ max }} ans.', min: 0, max: 60)]
    public ?int $experienceAnnees = null;

    #[Assert\Range(notInRangeMessage: 'Le tarif horaire doit être compris entre {{ min }} et {{ max }} €.', min: 0, max: 1000)]
    public ?string $tarifHoraire = null;

    #[Assert\Length(max: 100)]
    public ?string $ville = null;

    #[Assert\Length(max: 2000, maxMessage: 'Les certifications ne peuvent pas dépasser {{ limit }} caractères.')]
    public ?string $certifications = null;

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $input = new self();
        $input->biographie = self::nullableText($data, 'biographie');
        $input->specialites = array_values(array_unique(self::textList($data, 'specialites')));
        $input->experienceAnnees = self::int($data, 'experienceAnnees');
        $input->tarifHoraire = self::decimal($data, 'tarifHoraire');
        $input->ville = self::nullableText($data, 'ville');
        $input->certifications = self::nullableText($data, 'certifications');

        return $input;
    }

    public function applyTo(CoachProfile $profile): CoachProfile
    {
        return $profile
            ->setBio($this->biographie)
            ->setSpecialities($this->specialites)
            ->setExperienceYears($this->experienceAnnees)
            ->setHourlyRate($this->tarifHoraire === null ? null : number_format((float) $this->tarifHoraire, 2, '.', ''))
            ->setCity($this->ville)
            ->setCertifications($this->certifications);
    }
}
