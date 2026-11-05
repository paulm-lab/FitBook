<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Http\ApiException;
use App\Service\UserPresenter;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Avatar de l'utilisateur connecté (US-S07) : envoi multipart/form-data, champ « avatar ».
 *
 * Seules les images JPEG, PNG ou WebP de 2 Mo au plus sont acceptées : le type est vérifié
 * sur le contenu du fichier (pas sur son nom), et le fichier est renommé avec un nom aléatoire
 * pour qu'aucun nom choisi par l'utilisateur n'arrive sur le disque.
 */
#[OA\Tag(name: 'Profil')]
#[Route('/api/me/avatar')]
final class AvatarController extends AbstractController
{
    private const PUBLIC_PATH = '/uploads/avatars';
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDirectory,
        private readonly EntityManagerInterface $em,
        private readonly UserPresenter $presenter,
    ) {
    }

    #[OA\RequestBody(content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(properties: [
        new OA\Property(property: 'avatar', type: 'string', format: 'binary'),
    ])))]
    #[OA\Response(response: 200, description: 'Profil mis à jour avec l’adresse du nouvel avatar')]
    #[OA\Response(response: 422, description: 'Fichier absent, trop lourd ou qui n’est pas une image autorisée')]
    #[Route('', name: 'api_me_avatar_upload', methods: ['POST'])]
    public function upload(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $user = $this->currentUser();
        $file = $request->files->get('avatar');
        if (!$file instanceof UploadedFile) {
            throw ApiException::validation(['avatar' => 'Choisissez une image (JPEG, PNG ou WebP).']);
        }

        $violations = $validator->validate($file, [
            new Assert\File(
                maxSize: '2M',
                mimeTypes: array_keys(self::EXTENSIONS),
                maxSizeMessage: 'L’image ne doit pas dépasser 2 Mo.',
                mimeTypesMessage: 'Formats acceptés : JPEG, PNG ou WebP.',
            ),
            new Assert\Image(maxWidth: 4000, maxHeight: 4000, maxWidthMessage: 'L’image est trop grande (4000 px au maximum).', maxHeightMessage: 'L’image est trop grande (4000 px au maximum).'),
        ]);
        if (\count($violations) > 0) {
            throw ApiException::validation(['avatar' => (string) $violations[0]->getMessage()]);
        }

        $name = sprintf('%d-%s.%s', $user->getId(), bin2hex(random_bytes(8)), self::EXTENSIONS[$file->getMimeType()]);
        $file->move($this->publicDirectory . self::PUBLIC_PATH, $name);

        $this->removeLocalAvatar($user);
        $user->setAvatar(self::PUBLIC_PATH . '/' . $name);
        $this->em->flush();

        return $this->json($this->presenter->present($user));
    }

    #[Route('', name: 'api_me_avatar_delete', methods: ['DELETE'])]
    public function delete(): JsonResponse
    {
        $user = $this->currentUser();
        $this->removeLocalAvatar($user);
        $user->setAvatar(null);
        $this->em->flush();

        return $this->json($this->presenter->present($user));
    }

    /** Supprime l'ancien fichier s'il a été envoyé sur FitBook (pas une photo externe des fixtures). */
    private function removeLocalAvatar(User $user): void
    {
        $avatar = $user->getAvatar();
        if ($avatar !== null && str_starts_with($avatar, self::PUBLIC_PATH . '/') && !str_contains($avatar, '..')) {
            (new Filesystem())->remove($this->publicDirectory . $avatar);
        }
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new ApiException(401, 'Authentification requise.');
        }

        return $user;
    }
}
