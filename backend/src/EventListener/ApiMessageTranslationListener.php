<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Traduction centralisée des messages de l'API dans la langue de la requête (Accept-Language).
 *
 * Les contrôleurs, ApiException et les gestionnaires de sécurité écrivent leurs messages en
 * français ; ce texte français sert de clé de traduction (translations/messages.en.yaml).
 * Seuls les champs `message` et `errors.*` du premier niveau du JSON sont traduits : les
 * données métier (titres, descriptions…) ne sont jamais modifiées.
 *
 * Les messages des contraintes Symfony Validator sont déjà traduits par le validateur
 * (domaine `validators`) : sans clé ici, ils traversent ce listener inchangés.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class ApiMessageTranslationListener
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $locale = $request->getLocale();

        // Français : les clés sont déjà le texte affiché, rien à faire.
        if ($locale === 'fr' || !$response instanceof JsonResponse || !str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $content = (string) $response->getContent();
        if (!str_contains($content, '"message"') && !str_contains($content, '"errors"')) {
            return;
        }

        // Décodage en objets (pas en tableaux) : « {} » et « [] » restent distincts à l'encodage.
        $body = json_decode($content, false);
        if (!$body instanceof \stdClass) {
            return;
        }

        $changed = false;
        if (isset($body->message) && \is_string($body->message)) {
            $body->message = $this->translate($body->message, $locale, $changed);
        }
        if (isset($body->errors) && $body->errors instanceof \stdClass) {
            foreach (get_object_vars($body->errors) as $field => $message) {
                if (\is_string($message)) {
                    $body->errors->{$field} = $this->translate($message, $locale, $changed);
                }
            }
        }

        if ($changed) {
            $response->setData($body);
        }
    }

    private function translate(string $message, string $locale, bool &$changed): string
    {
        $translated = $this->translator->trans($message, [], 'messages', $locale);
        $changed = $changed || $translated !== $message;

        return $translated;
    }
}
