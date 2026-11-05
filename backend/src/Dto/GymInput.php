<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Gym;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Création / modification d'une salle (gestionnaire de la salle ou administrateur).
 */
final class GymInput
{
    use ReadsJsonFields;

    /** Jours acceptés dans « horaires ». */
    public const DAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    public string $nom = '';

    #[Assert\Length(max: 2000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    public ?string $description = null;

    #[Assert\NotBlank(message: 'L’adresse est obligatoire.')]
    #[Assert\Length(max: 255)]
    public string $adresse = '';

    #[Assert\NotBlank(message: 'La ville est obligatoire.')]
    #[Assert\Length(max: 100)]
    public string $ville = '';

    #[Assert\NotBlank(message: 'Le code postal est obligatoire.')]
    #[Assert\Regex(pattern: '/^\d{4,5}$/', message: 'Le code postal doit contenir 4 ou 5 chiffres.')]
    public string $codePostal = '';

    #[Assert\Range(notInRangeMessage: 'La latitude doit être comprise entre {{ min }} et {{ max }}.', min: -90, max: 90)]
    public ?string $latitude = null;

    #[Assert\Range(notInRangeMessage: 'La longitude doit être comprise entre {{ min }} et {{ max }}.', min: -180, max: 180)]
    public ?string $longitude = null;

    #[Assert\Regex(pattern: '/^\+?[0-9\s().-]{7,20}$/', message: 'Saisissez un numéro de téléphone valide.')]
    public ?string $telephone = null;

    #[Assert\Email(message: 'Saisissez une adresse e-mail valide.')]
    public ?string $email = null;

    /** @var array<string, string>|null */
    public ?array $horaires = null;

    /** @var list<string> */
    #[Assert\Count(max: 10, maxMessage: '{{ limit }} photos au maximum.')]
    #[Assert\All([new Assert\Url(message: 'Chaque photo doit être une adresse web valide.', requireTld: true)])]
    public array $photos = [];

    /** Réservé à l'administrateur : identifiant du gestionnaire de la salle. */
    #[Assert\Positive(message: 'Le gestionnaire est invalide.')]
    public ?int $gestionnaireId = null;

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $input = new self();
        $input->nom = self::text($data, 'nom');
        $input->description = self::nullableText($data, 'description');
        $input->adresse = self::text($data, 'adresse');
        $input->ville = self::text($data, 'ville');
        $input->codePostal = self::text($data, 'codePostal');
        $input->latitude = self::decimal($data, 'latitude');
        $input->longitude = self::decimal($data, 'longitude');
        $input->telephone = self::nullableText($data, 'telephone');
        $input->email = self::nullableText($data, 'email');
        $input->horaires = \is_array($data['horaires'] ?? null) ? $data['horaires'] : null;
        $input->photos = self::textList($data, 'photos');
        $input->gestionnaireId = self::int($data, 'gestionnaireId');

        return $input;
    }

    /** Horaires : { "lundi": "07:00-22:00", … }, jours connus et heure de fin après l'heure de début. */
    #[Assert\Callback]
    public function validateOpeningHours(ExecutionContextInterface $context): void
    {
        foreach ($this->horaires ?? [] as $day => $hours) {
            $valid = \in_array($day, self::DAYS, true)
                && \is_string($hours)
                && preg_match('/^([01]\d|2[0-3]):[0-5]\d-([01]\d|2[0-3]):[0-5]\d$/', $hours)
                && substr($hours, 6) > substr($hours, 0, 5);
            if (!$valid) {
                $context->buildViolation('Horaires attendus : { "lundi": "07:00-22:00", … }, fin après début.')
                    ->atPath('horaires')->addViolation();

                return;
            }
        }
    }

    public function applyTo(Gym $gym): Gym
    {
        return $gym
            ->setName($this->nom)
            ->setDescription($this->description)
            ->setAddress($this->adresse)
            ->setCity($this->ville)
            ->setPostalCode($this->codePostal)
            ->setLatitude($this->latitude)
            ->setLongitude($this->longitude)
            ->setPhone($this->telephone)
            ->setEmail($this->email)
            ->setOpeningHours($this->horaires)
            ->setPhotos($this->photos);
    }
}
