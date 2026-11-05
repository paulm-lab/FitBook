<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\UtilisateurRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: KernelEvents::TERMINATE)]
final class RegistrationVerificationListener
{
    public function __construct(
        private readonly UtilisateurRepository $users,
        private readonly FitBookMailer $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        $verification = $event->getRequest()->attributes->get('_fitbook_verification');
        if (!is_array($verification) || !is_int($verification['user_id'] ?? null) || !is_string($verification['token'] ?? null)) {
            return;
        }

        $user = $this->users->find($verification['user_id']);
        if (!$user || !hash_equals($user->getVerificationTokenHash() ?? '', hash('sha256', $verification['token']))) {
            return;
        }

        try {
            $url = $this->urlGenerator->generate(
                'api_auth_verify_email',
                ['token' => $verification['token']],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
            $this->mailer->sendVerification($user, $url);
        } catch (\Throwable $exception) {
            // Never log the request body, password, verification token, or SMTP DSN.
            $this->logger->error('Le lien de vérification FitBook n’a pas pu être envoyé.', [
                'exception_class' => $exception::class,
            ]);
        }
    }
}
