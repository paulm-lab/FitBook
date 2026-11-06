<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\RegistrationRequest;
use App\Entity\CoachProfile;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailDeliveryStatus;
use App\Service\FitBookMailer;
use App\Service\ValidationErrors;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Inscription (avec choix du rôle) et confirmation de l'adresse e-mail.
 *
 * Le lien de confirmation est signé par VerifyEmailBundle et expire : rien n'est stocké en base.
 * Il est lié à l'adresse du compte, donc un changement d'adresse invalide les anciens liens.
 * Tant que l'adresse n'est pas confirmée, la connexion est refusée (UserChecker, RG-02).
 * Les e-mails sont rédigés dans la langue du compte (User::getLocale()).
 */
#[Route('/api')]
final class InscriptionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly FitBookMailer $mailer,
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly LoggerInterface $logger,
        private readonly string $frontendUrl,
    ) {
    }

    #[Route('/inscription', name: 'api_inscription', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $users,
        ValidatorInterface $validator,
        RateLimiterFactory $inscriptionLimiter,
    ): JsonResponse {
        // Limite par adresse IP : empêche la création de comptes en masse.
        if (!$inscriptionLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return $this->json(['message' => 'Trop de tentatives d’inscription. Réessayez plus tard.'], 429);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Le corps de la requête doit être un JSON valide.'], 400);
        }

        $form = RegistrationRequest::fromArray($data);
        $violations = $validator->validate($form);
        if (\count($violations) > 0) {
            return ValidationErrors::response($violations);
        }

        if ($users->findOneBy(['email' => $form->email])) {
            return $this->duplicateEmail();
        }

        $user = (new User())
            ->setLastName($form->nom)
            ->setFirstName($form->prenom)
            ->setEmail($form->email)
            ->setPhone($form->telephone)
            ->setRoles([$form->symfonyRole()])
            // Connexion refusée tant que l'adresse n'est pas confirmée par le lien signé (RG-02).
            ->setIsVerified(false)
            // Langue choisie dans le front (Accept-Language) : e-mails et notifications.
            ->setLocale($request->getLocale());
        $user->setPassword($this->passwordHasher->hashPassword($user, $form->password));

        // Un coach reçoit un profil professionnel vide, qu'il complète depuis son espace (US-C01).
        if ($user->hasRole(User::ROLE_COACH)) {
            $this->entityManager->persist((new CoachProfile())->setUser($user));
        }
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
                EmailDeliveryStatus::Sent => 'Compte créé. L’e-mail de vérification a été envoyé.',
                EmailDeliveryStatus::Queued => 'Compte créé. L’e-mail de vérification est en file d’attente d’envoi : il n’est pas encore parti.',
                EmailDeliveryStatus::Failed => 'Compte créé, mais l’e-mail de vérification n’a pas pu être envoyé. Vous pouvez demander un nouveau lien.',
            },
            'email' => $user->getEmail(),
            'role' => $form->role,
            'verificationEmailStatus' => $status->value,
            // Conservé pour les clients existants : vrai si l'e-mail est parti ou en file.
            'verificationEmailQueued' => $status !== EmailDeliveryStatus::Failed,
        ], 201);
    }

    #[Route('/auth/verify-email', name: 'api_auth_verify_email', methods: ['GET'])]
    public function verify(Request $request, UserRepository $users): RedirectResponse
    {
        $user = $users->find((int) $request->query->get('id'));
        if (!$user) {
            return $this->verificationResultRedirect('error');
        }

        // Signature invalide, lien modifié, expiré ou émis pour une autre adresse : refus.
        try {
            $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), (string) $user->getEmail());
        } catch (VerifyEmailExceptionInterface) {
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
    public function resend(Request $request, UserRepository $users, RateLimiterFactory $emailRequestLimiter): JsonResponse
    {
        // Réponse identique que le compte existe ou non : pas d'énumération des adresses.
        $neutral = ['message' => 'Si le compte doit être vérifié, la demande de renvoi a été prise en compte.'];

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json($neutral);
        }
        $email = mb_strtolower(\is_string($data['email'] ?? null) ? trim($data['email']) : '');

        // Limite par adresse e-mail et par IP : empêche d'inonder une boîte de liens.
        if (!$emailRequestLimiter->create($email . '|' . $request->getClientIp())->consume()->isAccepted()) {
            return $this->json(['message' => 'Trop de demandes. Réessayez dans quelques minutes.'], 429);
        }

        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $users->findOneBy(['email' => $email]) : null;
        if ($user && !$user->isVerified() && $this->trySendVerification($user) === EmailDeliveryStatus::Failed) {
            return $this->json(['message' => 'Le lien de vérification n’a pas pu être envoyé. Réessayez plus tard.'], 503);
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

    /** Lien signé (VerifyEmailBundle) envoyé dans la langue du compte. */
    private function trySendVerification(User $user): EmailDeliveryStatus
    {
        $signature = $this->verifyEmailHelper->generateSignature(
            'api_auth_verify_email',
            (string) $user->getId(),
            (string) $user->getEmail(),
            ['id' => $user->getId()],
        );

        try {
            return $this->mailer->sendVerification($user, $signature->getSignedUrl());
        } catch (\Throwable $exception) {
            // Jamais le DSN ni le lien : la classe d'exception suffit à diagnostiquer.
            $this->logger->warning('L’e-mail de vérification FitBook n’a pas pu être envoyé.', [
                'exception_class' => $exception::class,
            ]);

            return EmailDeliveryStatus::Failed;
        }
    }
}
