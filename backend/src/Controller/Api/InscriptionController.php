<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\RegistrationRequest;
use App\Entity\CoachProfile;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\FitBookMailer;
use App\Service\ValidationErrors;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Inscription avec choix du rôle.
 *
 * Le compte est actif dès sa création : l'utilisateur peut se connecter immédiatement,
 * sans confirmer son adresse e-mail (RG-02).
 */
#[Route('/api')]
final class InscriptionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly FitBookMailer $mailer,
        private readonly LoggerInterface $logger,
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
            ->setIsVerified(true)
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

        // E-mail de bienvenue accessoire : son échec n'empêche ni l'inscription ni la connexion.
        try {
            $this->mailer->sendRegistrationConfirmation($user);
        } catch (\Throwable $exception) {
            $this->logger->warning('Compte créé, mais échec de l’e-mail de bienvenue.', [
                'exception_class' => $exception::class,
            ]);
        }

        return $this->json([
            'message' => 'Compte créé avec succès. Vous pouvez maintenant vous connecter.',
            'email' => $user->getEmail(),
            'role' => $form->role,
        ], 201);
    }

    private function duplicateEmail(): JsonResponse
    {
        return $this->json([
            'message' => 'Cette adresse e-mail est déjà utilisée.',
            'errors' => ['email' => 'Cette adresse e-mail est déjà utilisée.'],
        ], 409);
    }
}
