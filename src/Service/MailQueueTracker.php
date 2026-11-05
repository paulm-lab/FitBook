<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;

/**
 * Signale si le dernier e-mail a été confié à un transport Messenger (file d'attente)
 * plutôt qu'envoyé directement : MailerInterface::send() ne le dit pas.
 */
#[AsEventListener]
final class MailQueueTracker
{
    private bool $queued = false;

    public function __invoke(SendMessageToTransportsEvent $event): void
    {
        if ($event->getEnvelope()->getMessage() instanceof SendEmailMessage) {
            $this->queued = true;
        }
    }

    public function reset(): void
    {
        $this->queued = false;
    }

    public function wasQueued(): bool
    {
        return $this->queued;
    }
}
