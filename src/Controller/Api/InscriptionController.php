<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\FitBookMailer;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/api')]
final class InscriptionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly FitBookMailer $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly string $frontendUrl,
    ) {
    }

    #[Route('/inscription', name: 'api_inscription', methods: ['POST'])]
    public function register(Request $request, UtilisateurRepository $users): JsonResponse
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
        $errors = [];

        if ($prenom === '' || mb_strlen($prenom) > 50) {
            $errors['prenom'] = $prenom === '' ? 'Le prénom est obligatoire.' : 'Le prénom ne peut pas dépasser 50 caractères.';
        }
        if ($nom === '' || mb_strlen($nom) > 50) {
            $errors['nom'] = $nom === '' ? 'Le nom est obligatoire.' : 'Le nom ne peut pas dépasser 50 caractères.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
            $errors['email'] = 'Saisissez une adresse e-mail valide de 100 caractères maximum.';
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
            return $this->json([
                'message' => 'Cette adresse e-mail est déjà utilisée.',
                'errors' => ['email' => 'Cette adresse e-mail est déjà utilisée.'],
            ], 409);
        }

        $user = (new Utilisateur())
            ->setNom($nom)
            ->setPrenom($prenom)
            ->setEmail($email)
            ->setTelephone($telephone)
            ->setRole('ROLE_USER')
            ->setDateInscription(new \DateTimeImmutable('today'))
            ->setStatut('inactif');
        $user->setMotDePasse($this->passwordHasher->hashPassword($user, $password));

        $token = bin2hex(random_bytes(32));
        $user->setVerificationTokenHash(hash('sha256', $token))
            ->setVerificationTokenExpiresAt(new \DateTimeImmutable('+24 hours'));
        $this->entityManager->persist($user);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->json([
                'message' => 'Cette adresse e-mail est déjà utilisée.',
                'errors' => ['email' => 'Cette adresse e-mail est déjà utilisée.'],
            ], 409);
        } catch (DbalException $exception) {
            $this->logger->error('Échec de persistance lors de la création d’un compte FitBook.', [
                'exception_class' => $exception::class,
            ]);
            return $this->json(['message' => 'Le compte n’a pas pu être enregistré. Réessayez plus tard.'], 503);
        }

        // Messenger routes verification email messages to its async transport.
        // Queue the message here without waiting for SMTP delivery.
        $verificationEmailQueued = true;
        try {
            $this->sendVerification($user, $token);
        } catch (\Throwable $exception) {
            $verificationEmailQueued = false;
            $this->logger->warning('Verification email could not be queued.', [
                'exception_class' => $exception::class,
            ]);
        }

        return $this->json([
            'message' => $verificationEmailQueued
                ? "Compte cr\u{e9}\u{e9}. Le lien de v\u{e9}rification a \u{e9}t\u{e9} ajout\u{e9} \u{e0} la file d'envoi."
                : "Compte cr\u{e9}\u{e9}, mais le lien de v\u{e9}rification n'a pas pu \u{ea}tre mis en file d'envoi. Vous pouvez en demander un nouveau.",
            'email' => $email,
            'verificationEmailQueued' => $verificationEmailQueued,
        ], 201);
    }

    #[Route('/auth/verify-email', name: 'api_auth_verify_email', methods: ['GET'])]
    public function verify(Request $request, UtilisateurRepository $users): RedirectResponse
    {
        $token = (string) $request->query->get('token', '');
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return $this->verificationResultRedirect('error');
        }

        $user = $users->findOneBy(['verificationTokenHash' => hash('sha256', $token)]);
        if (!$user) {
            return $this->verificationResultRedirect('error');
        }
        if (!$user->getVerificationTokenExpiresAt() || $user->getVerificationTokenExpiresAt() < new \DateTimeImmutable()) {
            return $this->verificationResultRedirect('error');
        }

        $user->setEmailVerifiedAt(new \DateTimeImmutable())
            ->setVerificationTokenHash(null)
            ->setVerificationTokenExpiresAt(null)
            ->setStatut('actif');
        $this->entityManager->flush();

        try {
            $this->mailer->sendRegistrationConfirmation($user);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->warning('Compte vérifié, mais échec de l’e-mail de confirmation.', [
                'exception_class' => $exception::class,
            ]);
        }

        return $this->verificationResultRedirect('success');
    }

    private function verificationResultRedirect(string $status): RedirectResponse
    {
        $url = rtrim($this->frontendUrl, '/') . '/verification-email?' . http_build_query(['status' => $status]);

        return $this->redirect($url);
    }
    #[Route('/auth/resend-verification', name: 'api_auth_resend_verification', methods: ['POST'])]
    public function resend(Request $request, UtilisateurRepository $users): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => "Si le compte doit \u{ea}tre v\u{e9}rifi\u{e9}, la demande de renvoi a \u{e9}t\u{e9} prise en compte."]);
        }

        $email = mb_strtolower($this->stringValue($data, 'email'));
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $users->findOneBy(['email' => $email]) : null;
        if ($user && $user->getStatut() !== 'actif') {
            $token = bin2hex(random_bytes(32));
            $user->setVerificationTokenHash(hash('sha256', $token))
                ->setVerificationTokenExpiresAt(new \DateTimeImmutable('+24 hours'))
                ->setStatut('inactif');
            $this->entityManager->flush();
            try {
                $this->sendVerification($user, $token);
            } catch (\Throwable $exception) {
                $this->logger->warning('Verification email resend could not be queued.', [
                    'exception_class' => $exception::class,
                ]);
                return $this->json(['message' => "Le lien de v\u{e9}rification n'a pas pu \u{ea}tre mis en file d'envoi."], 503);
            }
        }

            return $this->json(['message' => "Si le compte doit \u{ea}tre v\u{e9}rifi\u{e9}, la demande de renvoi a \u{e9}t\u{e9} prise en compte."]);
    }

    /** @param array<string, mixed> $data */
    private function stringValue(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? trim($data[$key]) : '';
    }

    private function sendVerification(Utilisateur $user, string $token): void
    {
        $url = $this->urlGenerator->generate(
            'api_auth_verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $this->mailer->sendVerification($user, $url);
    }
}