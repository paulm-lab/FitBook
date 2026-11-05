<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Données du formulaire d'inscription (POST /api/inscription), validées par Symfony Validator.
 *
 * Les règles reflètent celles du formulaire Angular. Le rôle `admin` n'est jamais accepté :
 * l'administrateur est créé par les fixtures (US-V05).
 */
final class RegistrationRequest
{
    /** Rôles proposés à l'inscription => rôle Symfony attribué. */
    public const ROLES = [
        'sportif' => User::ROLE_SPORTIF,
        'coach' => User::ROLE_COACH,
        'gestionnaire' => User::ROLE_GESTIONNAIRE,
    ];

    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(max: 50, maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.')]
    public string $prenom = '';

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 50, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    public string $nom = '';

    #[Assert\NotBlank(message: 'L’adresse e-mail est obligatoire.')]
    #[Assert\Email(message: 'Saisissez une adresse e-mail valide.')]
    #[Assert\Length(max: 180, maxMessage: 'L’adresse e-mail ne peut pas dépasser {{ limit }} caractères.')]
    public string $email = '';

    #[Assert\NotBlank(message: 'Le téléphone est obligatoire.')]
    #[Assert\Regex(pattern: '/^\+?[0-9\s().-]{7,20}$/', message: 'Saisissez un numéro de téléphone valide (7 à 20 caractères).')]
    public string $telephone = '';

    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.')]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')]
    public string $password = '';

    #[Assert\Choice(choices: ['sportif', 'coach', 'gestionnaire'], message: 'Choisissez un rôle : sportif, coach ou gestionnaire.')]
    public string $role = 'sportif';

    /**
     * Construit le DTO depuis le JSON reçu : champs normalisés (espaces, casse de l'e-mail).
     * Une valeur qui n'est pas une chaîne devient vide, donc refusée par NotBlank.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $text = static fn (string $key): string => \is_string($data[$key] ?? null) ? trim($data[$key]) : '';

        $request = new self();
        $request->prenom = $text('prenom');
        $request->nom = $text('nom');
        $request->email = mb_strtolower($text('email'));
        $request->telephone = $text('telephone');
        $request->password = \is_string($data['password'] ?? null) ? $data['password'] : '';
        $request->role = \array_key_exists('role', $data) ? $text('role') : 'sportif';

        return $request;
    }

    public function symfonyRole(): string
    {
        return self::ROLES[$this->role];
    }
}
