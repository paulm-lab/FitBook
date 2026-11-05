<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Critères de la recherche avancée (GET /api/recherche), lus dans la query string.
 *
 *   type      seances (défaut) | salles | coachs
 *   q         texte libre (titre, activité, nom de salle ou de coach, spécialité)
 *   ville     commune ou code postal
 *   activite  nom (partiel) de l'activité — activiteId pour un identifiant exact
 *   du, au    dates AAAA-MM-JJ (séances)
 *   prixMax   prix maximum de la séance ou tarif horaire maximum du coach
 *   niveau    debutant | intermediaire | avance (séances)
 *   noteMin   note moyenne minimale (1 à 5)
 *   lat, lng, rayon   recherche autour d'un point, rayon en km (salles et séances)
 *   tri       date | prix | note | distance | nom
 */
final class SearchCriteria
{
    public const TYPES = ['seances', 'salles', 'coachs'];
    public const SORTS = ['date', 'prix', 'note', 'distance', 'nom'];

    #[Assert\Choice(choices: self::TYPES, message: 'Type attendu : seances, salles ou coachs.')]
    public string $type = 'seances';

    #[Assert\Length(max: 100)]
    public string $q = '';

    #[Assert\Length(max: 100)]
    public string $ville = '';

    #[Assert\Length(max: 50)]
    public string $activite = '';

    #[Assert\Positive(message: 'L’activité est invalide.')]
    public ?int $activiteId = null;

    public ?\DateTimeImmutable $du = null;

    public ?\DateTimeImmutable $au = null;

    #[Assert\PositiveOrZero(message: 'Le prix maximum doit être positif.')]
    public ?float $prixMax = null;

    #[Assert\Choice(choices: ['debutant', 'intermediaire', 'avance'], message: 'Niveau attendu : debutant, intermediaire ou avance.')]
    public ?string $niveau = null;

    #[Assert\Range(notInRangeMessage: 'La note minimale doit être comprise entre {{ min }} et {{ max }}.', min: 1, max: 5)]
    public ?float $noteMin = null;

    #[Assert\Range(notInRangeMessage: 'La latitude doit être comprise entre {{ min }} et {{ max }}.', min: -90, max: 90)]
    public ?float $lat = null;

    #[Assert\Range(notInRangeMessage: 'La longitude doit être comprise entre {{ min }} et {{ max }}.', min: -180, max: 180)]
    public ?float $lng = null;

    #[Assert\Range(notInRangeMessage: 'Le rayon doit être compris entre {{ min }} et {{ max }} km.', min: 1, max: 200)]
    public ?float $rayon = null;

    #[Assert\Choice(choices: self::SORTS, message: 'Tri attendu : date, prix, note, distance ou nom.')]
    public string $tri = 'date';

    /** Dates reçues mais illisibles : signalées par la validation. */
    private bool $invalidDate = false;

    public static function fromRequest(Request $request): self
    {
        $query = $request->query;
        $text = static fn (string $key): string => trim((string) $query->get($key, ''));
        $number = static fn (string $key): ?float => is_numeric($query->get($key)) ? (float) $query->get($key) : null;

        $criteria = new self();
        $criteria->type = $text('type') ?: 'seances';
        $criteria->q = $text('q');
        $criteria->ville = $text('ville');
        $criteria->activite = $text('activite');
        $criteria->activiteId = ctype_digit($text('activiteId')) ? (int) $text('activiteId') : null;
        $criteria->du = $criteria->date($text('du'));
        $criteria->au = $criteria->date($text('au'));
        $criteria->prixMax = $number('prixMax');
        $criteria->niveau = $text('niveau') ?: null;
        $criteria->noteMin = $number('noteMin');
        $criteria->lat = $number('lat');
        $criteria->lng = $number('lng');
        $criteria->rayon = $number('rayon');
        // Sans point de départ, le tri par distance n'a pas de sens : tri par défaut du type.
        $criteria->tri = $text('tri') ?: ($criteria->type === 'seances' ? 'date' : 'nom');

        return $criteria;
    }

    public function hasLocation(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    #[Assert\Callback]
    public function validateConsistency(ExecutionContextInterface $context): void
    {
        if ($this->invalidDate) {
            $context->buildViolation('Les dates doivent être au format AAAA-MM-JJ.')->atPath('du')->addViolation();
        }
        if ($this->du !== null && $this->au !== null && $this->au < $this->du) {
            $context->buildViolation('La date de fin doit suivre la date de début.')->atPath('au')->addViolation();
        }
        if (($this->lat === null) !== ($this->lng === null)) {
            $context->buildViolation('La latitude et la longitude vont ensemble.')->atPath('lat')->addViolation();
        }
        if ($this->tri === 'distance' && !$this->hasLocation()) {
            $context->buildViolation('Le tri par distance demande une position (lat, lng).')->atPath('tri')->addViolation();
        }
    }

    private function date(string $value): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('Europe/Brussels'));
        if (!$date || $date->format('Y-m-d') !== $value) {
            $this->invalidDate = true;

            return null;
        }

        return $date;
    }
}
