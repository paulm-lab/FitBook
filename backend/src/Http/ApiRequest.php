<?php

declare(strict_types=1);

namespace App\Http;

use App\Service\ValidationErrors;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Lecture et validation du corps JSON des requêtes de l'API.
 *
 *   $data = $this->api->json($request);                       // 400 si JSON invalide
 *   $input = $this->api->validate(GymInput::fromArray($data)); // 422 si un champ est invalide
 */
final class ApiRequest
{
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    /** @return array<string, mixed> */
    public function json(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\Throwable) {
            throw ApiException::badRequest('Le corps de la requête doit être un objet JSON valide.');
        }
    }

    /**
     * Valide un objet (DTO ou entité) avec ses contraintes Symfony Validator.
     *
     * @template T of object
     *
     * @param T             $object
     * @param list<string>  $groups
     *
     * @return T
     */
    public function validate(object $object, array $groups = []): object
    {
        $violations = $this->validator->validate($object, null, $groups ?: null);
        if (\count($violations) > 0) {
            throw ApiException::validation(ValidationErrors::toArray($violations));
        }

        return $object;
    }
}
