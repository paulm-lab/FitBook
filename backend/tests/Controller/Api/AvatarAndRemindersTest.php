<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Reservation;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Avatar (US-S07) et rappels 24 h avant la séance (US-S08, commande app:send-reminders).
 */
final class AvatarAndRemindersTest extends ApiTestCase
{
    /** PNG valide de 1 × 1 pixel. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==';

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->files);
        parent::tearDown();
    }

    public function testAvatarAcceptsOnlyRealImages(): void
    {
        $user = $this->user('a@example.test');
        $this->login($user);

        $fake = $this->tempFile('avatar.png', '<?php echo "pas une image";');
        $this->client->request('POST', '/api/me/avatar', files: ['avatar' => new UploadedFile($fake, 'avatar.png', 'image/png', test: true)]);
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('avatar', $this->json()['errors']);

        $png = $this->tempFile('photo.png', (string) base64_decode(self::PNG));
        $this->client->request('POST', '/api/me/avatar', files: ['avatar' => new UploadedFile($png, '../../photo.png', 'image/png', test: true)]);
        self::assertResponseIsSuccessful();
        $avatar = $this->json()['avatar'];
        // Nom aléatoire généré par le serveur : jamais le nom envoyé par le client.
        self::assertMatchesRegularExpression('#^/uploads/avatars/\d+-[0-9a-f]{16}\.png$#', $avatar);
        $stored = static::getContainer()->getParameter('kernel.project_dir') . '/public' . $avatar;
        $this->files[] = $stored;
        self::assertFileExists($stored);

        $this->client->request('DELETE', '/api/me/avatar');
        self::assertNull($this->json()['avatar']);
        self::assertFileDoesNotExist($stored);
    }

    public function testRemindersAreSentOnceForSessionsWithin24Hours(): void
    {
        $manager = $this->user('g@example.test', User::ROLE_GESTIONNAIRE);
        $gym = $this->gym($manager);
        $activity = $this->activity();
        $soon = $this->reservation($this->user('a@example.test'), $this->session($activity, $gym, null, '+20 hours'));
        $this->reservation($this->user('b@example.test'), $this->session($activity, $gym, null, '+3 days'));

        $tester = new CommandTester((new Application(static::$kernel))->find('app:send-reminders'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('1 rappel(s) envoyé(s)', $tester->getDisplay());

        $this->em()->clear();
        self::assertNotNull($this->em()->find(Reservation::class, $soon->getId())->getReminderSentAt());

        $tester->execute([]);
        self::assertStringContainsString('0 rappel(s) envoyé(s)', $tester->getDisplay());
    }

    private function tempFile(string $name, string $content): string
    {
        $path = sys_get_temp_dir() . '/fitbook-test-' . bin2hex(random_bytes(4)) . '-' . $name;
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $path;
    }
}
