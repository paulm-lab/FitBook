<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\FitBookMailer;
use App\Service\ValidationErrors;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Mot de passe oublié (US-V07), avec SymfonyCasts ResetPasswordBundle.
 *
 * 1. POST /api/auth/forgot-password {email} : envoie un lien vers le front, qui contient le jeton.
 * 2. POST /api/auth/reset-password {token, password} : enregistre le nouveau mot de passe.
 *
 * Le jeton est à usage unique, expire au bout d'une heure, et seul son hash est stocké.
 */
#[Route('/api/auth')]
final class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly LoggerInterface $logger,
        private readonly string $frontendUrl,
    ) {
    }

    #[Route('/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function request(Request $request, UserRepository $users, FitBookMailer $mailer, RateLimiterFactory $emailRequestLimiter): JsonResponse
    {
        // Réponse identique que le compte existe ou non : pas d'énumération des adresses.
        $neutral = $this->json(['message' => "Si un compte correspond \u{e0} cette adresse, un lien de r\u{e9}initialisation va lui \u{ea}tre envoy\u{e9}."]);

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Le corps de la requête doit être un JSON valide.'], 400);
        }
        $email = mb_strtolower(\is_string($data['email'] ?? null) ? trim($data['email']) : '');

        if (!$emailRequestLimiter->create('reset|' . $email . '|' . $request->getClientIp())->consume()->isAccepted()) {
            return $this->json(['message' => 'Trop de demandes. Réessayez dans quelques minutes.'], 429);
        }

        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $users->findOneBy(['email' => $email]) : null;
        if (!$user instanceof User || $user->isBlocked()) {
            return $neutral;
        }

        try {
            $token = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface) {
            // Demande trop rapprochée de la précédente : on ne le révèle pas.
            return $neutral;
        }

        $url = rtrim($this->frontendUrl, '/') . '/reinitialiser-mot-de-passe?' . http_build_query(['token' => $token->getToken()]);
        try {
            $mailer->sendPasswordReset($user, $url);
        } catch (\Throwable $exception) {
            $this->logger->warning('L’e-mail de réinitialisation FitBook n’a pas pu être envoyé.', [
                'exception_class' => $exception::class,
            ]);
        }

        return $neutral;
    }

    #[Route('/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function reset(Request $request, ValidatorInterface $validator, UserPasswordHasherInterface $hasher, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Le corps de la requête doit être un JSON valide.'], 400);
        }
        $token = \is_string($data['token'] ?? null) ? $data['token'] : '';
        $password = \is_string($data['password'] ?? null) ? $data['password'] : '';

        $violations = $validator->validate(['password' => $password], new Assert\Collection([
            'password' => [
                new Assert\NotBlank(message: 'Le mot de passe est obligatoire.'),
                new Assert\Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
            ],
        ]));
        if (\count($violations) > 0) {
            return ValidationErrors::response($violations);
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface) {
            return $this->json(['message' => 'Ce lien de réinitialisation est invalide ou a expiré. Faites une nouvelle demande.'], 400);
        }

        // Usage unique : la demande est supprimée avant d'enregistrer le nouveau mot de passe.
        $this->resetPasswordHelper->removeResetRequest($token);
        $user->setPassword($hasher->hashPassword($user, $password));
        $entityManager->flush();

        return $this->json(['message' => 'Votre mot de passe a été modifié. Vous pouvez vous connecter.']);
    }
}
