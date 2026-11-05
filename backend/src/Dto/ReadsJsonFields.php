<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Conversion des champs d'un corps JSON en valeurs PHP typées, pour les DTO d'entrée.
 *
 * Une valeur absente ou du mauvais type devient vide / null : ce sont ensuite les contraintes
 * Symfony Validator du DTO qui la refusent avec un message clair (jamais d'erreur PHP).
 */
trait ReadsJsonFields
{
    /** @param array<string, mixed> $data */
    private static function text(array $data, string $key): string
    {
        return \is_string($data[$key] ?? null) ? trim($data[$key]) : '';
    }

    /** @param array<string, mixed> $data */
    private static function nullableText(array $data, string $key): ?string
    {
        $value = self::text($data, $key);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $data */
    private static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if (\is_int($value)) {
            return $value;
        }

        return \is_string($value) && preg_match('/^-?\d+$/', trim($value)) ? (int) $value : null;
    }

    /** @param array<string, mixed> $data */
    private static function decimal(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        return \is_string($value) && is_numeric(trim($value)) ? trim($value) : null;
    }

    /** @param array<string, mixed> $data */
    private static function bool(array $data, string $key, bool $default = false): bool
    {
        return \is_bool($data[$key] ?? null) ? $data[$key] : $default;
    }

    /**
     * Date et heure ISO 8601 (ex. « 2026-11-20T18:30:00+01:00 »), convertie en UTC pour le stockage.
     *
     * @param array<string, mixed> $data
     */
    private static function dateTime(array $data, string $key): ?\DateTimeImmutable
    {
        $value = self::text($data, $key);
        if ($value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private static function textList(array $data, string $key): array
    {
        $value = $data[$key] ?? [];
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($v): string => \is_string($v) ? trim($v) : '', $value), static fn (string $v): bool => $v !== ''));
    }
}
