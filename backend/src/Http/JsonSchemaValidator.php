<?php

declare(strict_types=1);

namespace App\Http;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Validation d'un corps de requête par un JSON Schema (opis/json-schema).
 *
 * Les schémas sont versionnés dans config/schemas/<nom>.schema.json. En cas d'échec :
 * 422 { "errors": { "champ": "message" } }, dans la langue de la requête (clés françaises).
 */
final class JsonSchemaValidator
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/config/schemas')]
        private readonly string $schemaDirectory,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $data corps de la requête déjà décodé
     *
     * @throws ApiException 422 si le corps ne respecte pas le schéma
     */
    public function validate(array $data, string $schemaName): void
    {
        $path = sprintf('%s/%s.schema.json', $this->schemaDirectory, $schemaName);
        if (!is_file($path)) {
            throw new \LogicException(sprintf('Schéma JSON introuvable : %s', $path));
        }

        // Opis attend des objets PHP (stdClass), comme json_decode sans « assoc ».
        $document = json_decode(json_encode($data === [] ? new \stdClass() : $data, \JSON_THROW_ON_ERROR), false, flags: \JSON_THROW_ON_ERROR);
        // Toutes les erreurs sont collectées (pas seulement la première) : un message par champ.
        $validator = (new Validator())->setMaxErrors(20)->setStopAtFirstError(false);
        $result = $validator->validate($document, (string) file_get_contents($path));

        if (!$result->isValid()) {
            throw ApiException::validation($this->messages($result->error()));
        }
    }

    /**
     * Traduit les erreurs d'Opis en messages lisibles, un par champ.
     *
     * @return array<string, string>
     */
    private function messages(ValidationError $error): array
    {
        $errors = [];
        foreach ([$error, ...$this->flatten($error)] as $item) {
            $field = implode('.', $item->data()->fullPath()) ?: 'corps';
            $message = match ($item->keyword()) {
                'required' => null,
                // Messages à paramètre : traduits ici (le listener de traduction ne connaît pas {type}).
                'type' => $this->translator->trans('Ce champ doit être de type {type}.', ['{type}' => $this->typeName($item->args()['expected'] ?? '')]),
                'minimum' => $this->translator->trans('Ce champ doit être supérieur ou égal à {min}.', ['{min}' => $item->args()['min'] ?? '']),
                'const' => $this->translator->trans('Ce champ doit valoir {value}.', ['{value}' => json_encode($item->args()['const'] ?? null)]),
                'additionalProperties' => null,
                default => null,
            };

            if ($item->keyword() === 'required') {
                foreach ($item->args()['missing'] ?? [] as $missing) {
                    $errors[$missing] ??= 'Ce champ est obligatoire.';
                }
            } elseif ($item->keyword() === 'additionalProperties') {
                foreach ($item->args()['properties'] ?? [] as $extra) {
                    $errors[$extra] ??= 'Ce champ n’est pas accepté.';
                }
            } elseif ($message !== null) {
                $errors[$field] ??= $message;
            }
        }

        return $errors ?: ['corps' => 'Le corps de la requête ne respecte pas le format attendu.'];
    }

    /** @return list<ValidationError> */
    private function flatten(ValidationError $error): array
    {
        $all = [];
        foreach ($error->subErrors() as $sub) {
            $all = [...$all, $sub, ...$this->flatten($sub)];
        }

        return $all;
    }

    private function typeName(mixed $expected): string
    {
        $names = ['integer' => 'nombre entier', 'number' => 'nombre', 'string' => 'texte', 'boolean' => 'booléen', 'object' => 'objet', 'array' => 'liste'];
        $types = \is_array($expected) ? $expected : [$expected];

        return implode(' ' . $this->translator->trans('ou') . ' ', array_map(
            fn ($t): string => isset($names[$t]) ? $this->translator->trans($names[$t]) : (string) $t,
            $types,
        ));
    }
}
