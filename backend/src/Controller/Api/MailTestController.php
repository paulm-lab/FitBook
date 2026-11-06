<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\EmailDeliveryStatus;
use App\Service\FitBookMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Envoi d'un e-mail de test (développement uniquement) pour vérifier la configuration MAILER_DSN.
 *
 * Protégé par un jeton (MAIL_TEST_TOKEN dans .env.local, en-tête X-FitBook-Test-Token).
 */
#[When(env: 'dev')]
#[Route('/api/dev/mail-test')]
final class MailTestController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(string:MAIL_TEST_TOKEN)%')]
        private readonly string $testToken,
        private readonly FitBookMailer $mailer,
    ) {
    }

    #[Route('', name: 'api_dev_mail_test', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        if ($this->testToken === '') {
            return $this->json(['message' => 'Configurez MAIL_TEST_TOKEN dans .env.local pour activer ce test.'], 503);
        }
        if (!hash_equals($this->testToken, (string) $request->headers->get('X-FitBook-Test-Token', ''))) {
            return $this->json(['message' => 'Jeton de test absent ou invalide.'], 403);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Le corps doit être un JSON valide.'], 400);
        }

        $email = trim((string) ($data['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Fournissez une adresse e-mail valide.'], 400);
        }

        try {
            $status = $this->mailer->sendTest($email);
        } catch (TransportExceptionInterface) {
            return $this->json([
                'message' => 'Envoi impossible. Vérifiez MAILER_DSN et que le serveur SMTP (Mailpit en développement) est démarré.',
                'status' => EmailDeliveryStatus::Failed->value,
            ], 502);
        }

        return $this->json([
            'message' => 'E-mail accepté par le serveur SMTP. En développement, il est visible dans Mailpit (http://127.0.0.1:8025).',
            'status' => $status->value,
        ]);
    }
}
