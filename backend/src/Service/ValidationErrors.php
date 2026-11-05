<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Format unique des erreurs de validation de l'API :
 * 422 { "message": "Certains champs sont invalides.", "errors": { "champ": "message" } }.
 *
 * Seul le premier message de chaque champ est renvoyé : c'est celui que le front affiche.
 */
final class ValidationErrors
{
    /** @return array<string, string> */
    public static function toArray(ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            // « [password] » (contrainte Collection) et « password » (DTO) donnent la même clé.
            $field = trim($violation->getPropertyPath(), '[]');
            $errors[$field] ??= (string) $violation->getMessage();
        }

        return $errors;
    }

    public static function response(ConstraintViolationListInterface $violations): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Certains champs sont invalides.',
            'errors' => self::toArray($violations),
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
