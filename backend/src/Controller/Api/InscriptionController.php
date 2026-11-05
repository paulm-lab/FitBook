<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailDeliveryStatus;
use App\Service\FitBookMailer;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Inscription et confirmation de l'adresse e-mail.
 *
 * Le lien de confirmation est **signé** (UriSigner) et expire après 24 h : rien n'est stocké
 * en base. Il contient l'empreinte de l'adresse, donc un changement d'adresse l'invalide.
 */
#[Route('/api')]
final class InscriptionController extends AbstractController
{
    private const LINK_LIFETIME = '+24 hours';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly FitBookMailer $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UriSigner $uriSigner,
        private readonly LoggerInterface $logger,
        private readonly string $frontendUrl,
    ) {
    }

    #[Route('/inscription', name: 'api_inscription', methods: ['POST'])]
    public function register(Request $request, UserRepository $users): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Le corps de la requête doit être un JSON valide.'], 400);
        }

        $nom = $this->stringValue($data, 'nom');
        $prenom = $this->stringValue($data, 'prenom');
        $email = mb_strtolower($this->stringValue($data, 'email'));
        $telephone = $this->stringValue($data, 'telephone');
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';

        // Validation de chaque champ (remplacée par Symfony Validator en Phase 3).
        $errors = [];
        if ($prenom === '' || mb_strlen($prenom) > 50) {
            $errors['prenom'] = $prenom === '' ? 'Le prénom est obligatoire.' : 'Le prénom ne peut pas dépasser 50 caractères.';
        }
        if ($nom === '' || mb_strlen($nom) > 50) {
            $errors['nom'] = $nom === '' ? 'Le nom est obligatoire.' : 'Le nom ne peut pas dépasser 50 caractères.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            $errors['email'] = 'Saisissez une adresse e-mail valide.';
        }
        if ($telephone === '' || mb_strlen($telephone) > 20 || !preg_match('/^\+?[0-9\s().-]{7,20}$/', $telephone)) {
            $errors['telephone'] = 'Saisissez un numéro de téléphone valide (7 à 20 caractères).';
        }
        if (strlen($password) < 8 || strlen($password) > 4096) {
            $errors['password'] = 'Le mot de passe doit contenir entre 8 et 4096 caractères.';
        }
        if ($errors !== []) {
            return $this->json(['message' => 'Certains champs sont invalides.', 'errors' => $errors], 400);
        }

        if ($users->findOneBy(['email' => $email])) {
            return $this->duplicateEmail();
        }

        // Inscription publique : toujours un compte sportif (choix du rôle en Phase 3).
        $user = (new User())
            ->setLastName($nom)
            ->setFirstName($prenom)
            ->setEmail($email)
            ->setPhone($telephone)
            ->setRoles([User::ROLE_SPORTIF])
            ->setIsVerified(false);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->persist($user);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateEmail();
        } catch (DbalException $exception) {
            $this->logger->error('Échec de persistance lors de la création d’un compte FitBook.', [
                'exception_class' => $exception::class,
            ]);

            return $this->json(['message' => 'Le compte n’a pas pu être enregistré. Réessayez plus tard.'], 503);
        }

        // Le compte existe déjà : un échec d'envoi ne bloque pas l'inscription, un nouveau lien peut être demandé.
        $status = $this->trySendVerification($user);

        return $this->json([
            'message' => match ($status) {
                EmailDeliveryStatus::Sent => "Compte cr\u{e9}\u{e9}. L'e-mail de v\u{e9}rification a \u{e9}t\u{e9} envoy\u{e9}.",
                EmailDeliveryStatus::Queued => "Compte cr\u{e9}\u{e9}. L'e-mail de v\u{e9}rification est en file d'attente d'envoi : il n'est pas encore parti.",
                EmailDeliveryStatus::Failed => "Compte cr\u{e9}\u{e9}, mais l'e-mail de v\u{e9}rification n'a pas pu \u{ea}tre envoy\u{e9}. Vous pouvez demander un nouveau lien.",
            },
            'email' => $email,
            'verificationEmailStatus' => $status->value,
            // Conservé pour les clients existants : vrai si l'e-mail est parti ou en file.
            'verificationEmailQueued' => $status !== EmailDeliveryStatus::Failed,
        ], 201);
    }

    #[Route('/auth/verify-email', name: 'api_auth_verify_email', methods: ['GET'])]
    public function verify(Request $request, UserRepository $users): RedirectResponse
    {
        // Signature invalide, lien modifié ou expiré : refus sans rien révéler.
        if (!$this->uriSigner->checkRequest($request)) {
            return $this->verificationResultRedirect('error');
        }

        $user = $users->find((int) $request->query->get('id'));
        if (!$user || !hash_equals($this->emailFingerprint($user), (string) $request->query->get('email'))) {
            return $this->verificationResultRedirect('error');
        }

        if (!$user->isVerified()) {
            $user->setIsVerified(true);
            $this->entityManager->flush();

            try {
                $this->mailer->sendRegistrationConfirmation($user);
            } catch (\Throwable $exception) {
                // Le compte est déjà activé : l'e-mail de bienvenue est accessoire.
                $this->logger->warning('Compte vérifié, mais échec de l’e-mail de confirmation.', [
                    'exception_class' => $exception::class,
                ]);
            }
        }

        return $this->verificationResultRedirect('success');
    }

    #[Route('/auth/resend-verification', name: 'api_auth_resend_verification', methods: ['POST'])]
    public function resend(Request $request, UserRepository $users): JsonResponse
    {
        // Réponse identique que le compte existe ou non : pas d'énumération des adresses.
        $neutral = ['message' => "Si le compte doit \u{ea}tre v\u{e9}rifi\u{e9}, la demande de renvoi a \u{e9}t\u{e9} prise en compte."];

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json($neutral);
        }

        $email = mb_strtolower($this->stringValue($data, 'email'));
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $users->findOneBy(['email' => $email]) : null;

        if ($user && !$user->isVerified() && $this->trySendVerification($user) === EmailDeliveryStatus::Failed) {
            return $this->json(['message' => "Le lien de v\u{e9}rification n'a pas pu \u{ea}tre envoy\u{e9}. R\u{e9}essayez plus tard."], 503);
        }

        return $this->json($neutral);
    }

    private function duplicateEmail(): JsonResponse
    {
        return $this->json([
            'message' => 'Cette adresse e-mail est déjà utilisée.',
            'errors' => ['email' => 'Cette adresse e-mail est déjà utilisée.'],
        ], 409);
    }

    private function verificationResultRedirect(string $status): RedirectResponse
    {
        $url = rtrim($this->frontendUrl, '/') . '/verification-email?' . http_build_query(['status' => $status]);

        return $this->redirect($url);
    }

    /** Empreinte de l'adresse incluse dans le lien : changer d'adresse invalide les anciens liens. */
    private function emailFingerprint(User $user): string
    {
        return hash('sha256', (string) $user->getEmail());
    }

    private function trySendVerification(User $user): EmailDeliveryStatus
    {
        $url = $this->urlGenerator->generate(
            'api_auth_verify_email',
            ['id' => $user->getId(), 'email' => $this->emailFingerprint($user)],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $signedUrl = $this->uriSigner->sign($url, new \DateTimeImmutable(self::LINK_LIFETIME));

        try {
            return $this->mailer->sendVerification($user, $signedUrl);
        } catch (\Throwable $exception) {
            // Jamais le DSN ni le lien : la classe d'exception suffit à diagnostiquer.
            $this->logger->warning('L’e-mail de vérification FitBook n’a pas pu être envoyé.', [
                'exception_class' => $exception::class,
            ]);

            return EmailDeliveryStatus::Failed;
        }
    }

    /** @param array<string, mixed> $data */
    private function stringValue(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? trim($data[$key]) : '';
    }
}
