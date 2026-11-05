<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Erreur métier de l'API, convertie en réponse JSON par ApiExceptionListener :
 * { "message": "…" } ou { "message": "…", "errors": { "champ": "message" } }.
 *
 * Permet aux contrôleurs et services d'interrompre un traitement sans construire eux-mêmes
 * la réponse d'erreur (voir la convention des codes dans docs/use-cases.md).
 */
final class ApiException extends \RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(
        private readonly int $statusCode,
        string $message,
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function badRequest(string $message): self
    {
        return new self(400, $message);
    }

    public static function forbidden(string $message = 'Accès refusé.'): self
    {
        return new self(403, $message);
    }

    public static function notFound(string $message): self
    {
        return new self(404, $message);
    }

    public static function conflict(string $message): self
    {
        return new self(409, $message);
    }

    /** @param array<string, string> $errors */
    public static function validation(array $errors, string $message = 'Certains champs sont invalides.'): self
    {
        return new self(422, $message, $errors);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, string> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
