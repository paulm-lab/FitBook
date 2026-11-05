<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Tests d'intégration des 3 procédures stockées et des contraintes CHECK, sur une vraie base MySQL.
 *
 * Base utilisée : MYSQL_TEST_DATABASE_URL (dans .env.test.local, non versionné), normalement
 * `fitbook_test` avec l'utilisateur `fitbook_user`. Le schéma y est reconstruit par les migrations
 * avant les tests ; sans cette variable, les tests sont ignorés (les autres tests tournent sur SQLite).
 */
#[Group('mysql')]
final class StoredProceduresTest extends TestCase
{
    private static ?Connection $db = null;

    public static function setUpBeforeClass(): void
    {
        $url = $_SERVER['MYSQL_TEST_DATABASE_URL'] ?? $_ENV['MYSQL_TEST_DATABASE_URL'] ?? '';
        if ($url === '') {
            return;
        }

        $params = (new DsnParser(['mysql' => 'pdo_mysql']))->parse($url);
        self::$db = DriverManager::getConnection($params);

        // Repart d'une base vide, puis applique les migrations dans un processus séparé
        // (la variable d'environnement réelle DATABASE_URL prime sur les fichiers .env).
        self::$db->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::$db->createSchemaManager()->listTableNames() as $table) {
            self::$db->executeStatement(sprintf('DROP TABLE `%s`', $table));
        }
        foreach (['sp_reserver_seance', 'sp_annuler_reservation', 'sp_stats_salle'] as $procedure) {
            self::$db->executeStatement("DROP PROCEDURE IF EXISTS {$procedure}");
        }
        self::$db->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $process = new Process(
            [\PHP_BINARY, 'bin/console', 'doctrine:migrations:migrate', '--no-interaction', '--env=dev'],
            \dirname(__DIR__, 2),
            ['DATABASE_URL' => $url, 'APP_ENV' => 'dev'],
        );
        $process->setTimeout(300)->mustRun();
    }

    protected function setUp(): void
    {
        if (self::$db === null) {
            self::markTestSkipped('MYSQL_TEST_DATABASE_URL non défini (voir .env.test.local) : tests MySQL ignorés.');
        }

        // Tables vidées entre chaque test.
        self::$db->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['review', 'favorite', 'reservation', 'availability', '`session`', 'activity', 'coach_profile', 'gym', 'notification', '`user`'] as $table) {
            self::$db->executeStatement("TRUNCATE TABLE {$table}");
        }
        self::$db->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    // -------------------------------------------------------------------------
    // sp_reserver_seance
    // -------------------------------------------------------------------------

    public function testReservationSucceedsThenRefusesADuplicate(): void
    {
        $user = $this->user();
        $session = $this->session('+1 day', 5);

        [$code, $id] = $this->reserve($user, $session);
        self::assertSame(0, $code);
        self::assertSame('confirmee', $this->db()->fetchOne('SELECT status FROM reservation WHERE id = ?', [$id]));

        [$code] = $this->reserve($user, $session);
        self::assertSame(3, $code, 'Le même sportif ne réserve pas deux fois (RG-04).');
    }

    public function testFullSessionIsRefused(): void
    {
        $session = $this->session('+1 day', 1);

        self::assertSame(0, $this->reserve($this->user('a@test.local'), $session)[0]);
        self::assertSame(4, $this->reserve($this->user('b@test.local'), $session)[0], 'Capacité atteinte (RG-03).');
        self::assertSame(1, (int) $this->db()->fetchOne("SELECT COUNT(*) FROM reservation WHERE session_id = ? AND status = 'confirmee'", [$session]));
    }

    public function testUnknownCancelledOrPastSessionsAreRefused(): void
    {
        $user = $this->user();

        self::assertSame(1, $this->reserve($user, 999)[0]);
        self::assertSame(2, $this->reserve($user, $this->session('+1 day', 5, 'annulee'))[0]);
        self::assertSame(2, $this->reserve($user, $this->session('-1 hour', 5))[0]);
    }

    public function testCancelledReservationIsReactivated(): void
    {
        $user = $this->user();
        $session = $this->session('+1 day', 5);
        [, $id] = $this->reserve($user, $session);
        self::assertSame(0, $this->cancel($id, $user));

        [$code, $newId] = $this->reserve($user, $session);

        self::assertSame(0, $code);
        self::assertSame($id, $newId, 'Une seule ligne par couple (sportif, séance).');
        self::assertNull($this->db()->fetchOne('SELECT cancelled_at FROM reservation WHERE id = ?', [$id]));
    }

    // -------------------------------------------------------------------------
    // sp_annuler_reservation
    // -------------------------------------------------------------------------

    public function testCancellationRules(): void
    {
        $owner = $this->user('owner@test.local');
        $other = $this->user('other@test.local');
        [, $id] = $this->reserve($owner, $this->session('+1 day', 5));

        self::assertSame(1, $this->cancel(999, $owner), 'Réservation introuvable.');
        self::assertSame(2, $this->cancel($id, $other), 'Seul le propriétaire annule.');
        self::assertSame(0, $this->cancel($id, $owner));
        self::assertSame('annulee', $this->db()->fetchOne('SELECT status FROM reservation WHERE id = ?', [$id]));
        self::assertSame(4, $this->cancel($id, $owner), 'Déjà annulée.');
    }

    public function testCancellationIsRefusedLessThanTwoHoursBeforeTheSession(): void
    {
        $user = $this->user();
        [, $soon] = $this->reserve($user, $this->session('+90 minutes', 5));
        [, $later] = $this->reserve($user, $this->session('+150 minutes', 5));

        self::assertSame(3, $this->cancel($soon, $user), 'Moins de 2 h avant : refus (RG-05).');
        self::assertSame(0, $this->cancel($later, $user));
    }

    // -------------------------------------------------------------------------
    // sp_stats_salle
    // -------------------------------------------------------------------------

    public function testGymStatistics(): void
    {
        $gym = $this->gym();
        $first = $this->session('+1 day', 10, gym: $gym);
        $second = $this->session('+2 days', 10, gym: $gym);
        $this->session('+3 days', 10, 'annulee', gym: $gym); // ignorée
        for ($i = 1; $i <= 3; ++$i) {
            $this->reserve($this->user("u{$i}@test.local"), $first);
        }
        $this->reserve($this->user('u4@test.local'), $second);
        $this->reserve($this->user('u5@test.local'), $second);
        $author = $this->user('author@test.local');
        $this->review($author, $gym, 5);
        $this->review($this->user('author2@test.local'), $gym, 4);
        $this->review($this->user('author3@test.local'), $gym, 1, visible: false); // masqué : ignoré

        $stats = $this->db()->fetchAssociative('CALL sp_stats_salle(?)', [$gym]);

        self::assertSame(2, (int) $stats['nb_seances']);
        self::assertSame(20, (int) $stats['capacite_totale']);
        self::assertSame(5, (int) $stats['nb_reservations']);
        self::assertSame('25.0', (string) $stats['taux_remplissage']);
        self::assertSame('4.50', (string) $stats['note_moyenne']);
        self::assertSame(2, (int) $stats['nb_avis']);
    }

    // -------------------------------------------------------------------------
    // Contraintes CHECK (intégrité garantie par la base, même sans l'application)
    // -------------------------------------------------------------------------

    public function testCheckConstraintsRejectInvalidRows(): void
    {
        $gym = $this->gym();
        $activity = $this->activity();
        $user = $this->user();

        $this->assertRejected("INSERT INTO `session` (activity_id, gym_id, title, start_at, end_at, capacity, price, level, status) VALUES ({$activity}, {$gym}, 'x', '2030-01-01 10:00', '2030-01-01 09:00', 5, 0, 'debutant', 'active')", 'fin avant début');
        $this->assertRejected("INSERT INTO `session` (activity_id, gym_id, title, start_at, end_at, capacity, price, level, status) VALUES ({$activity}, {$gym}, 'x', '2030-01-01 09:00', '2030-01-01 10:00', 0, 0, 'debutant', 'active')", 'capacité nulle');
        $this->assertRejected("INSERT INTO `session` (activity_id, title, start_at, end_at, capacity, price, level, status) VALUES ({$activity}, 'x', '2030-01-01 09:00', '2030-01-01 10:00', 5, 0, 'debutant', 'active')", 'ni salle ni coach');
        $this->assertRejected("INSERT INTO review (author_id, gym_id, rating, is_moderated, is_visible, created_at) VALUES ({$user}, {$gym}, 6, 0, 1, UTC_TIMESTAMP())", 'note hors 1..5');
        $session = $this->session('+1 day', 5, gym: $gym);
        $this->assertRejected("INSERT INTO favorite (user_id, gym_id, session_id, type, created_at) VALUES ({$user}, {$gym}, {$session}, 'gym', UTC_TIMESTAMP())", 'deux cibles');
        $this->assertRejected("INSERT INTO favorite (user_id, gym_id, type, created_at) VALUES ({$user}, {$gym}, 'coach', UTC_TIMESTAMP())", 'type incohérent');
    }

    // -------------------------------------------------------------------------
    // Outils
    // -------------------------------------------------------------------------

    private function db(): Connection
    {
        return self::$db;
    }

    /** @return array{0: int, 1: int|null} code et identifiant de réservation */
    private function reserve(int $user, int $session): array
    {
        $this->db()->executeStatement('CALL sp_reserver_seance(?, ?, @code, @id)', [$user, $session]);
        $row = $this->db()->fetchAssociative('SELECT @code AS code, @id AS id');

        return [(int) $row['code'], $row['id'] === null ? null : (int) $row['id']];
    }

    private function cancel(int $reservation, int $user): int
    {
        $this->db()->executeStatement('CALL sp_annuler_reservation(?, ?, @code)', [$reservation, $user]);

        return (int) $this->db()->fetchOne('SELECT @code');
    }

    private function user(string $email = 'sportif@test.local'): int
    {
        $this->db()->insert('`user`', [
            'email' => $email, 'password' => 'x', 'first_name' => 'Test', 'last_name' => 'Test',
            'roles' => '["ROLE_SPORTIF"]', 'is_verified' => 1, 'is_blocked' => 0, 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $this->db()->lastInsertId();
    }

    private function gym(): int
    {
        $this->db()->insert('gym', [
            'manager_id' => $this->user('manager' . uniqid() . '@test.local'), 'name' => 'Salle', 'address' => 'Rue 1',
            'city' => 'Bruxelles', 'postal_code' => '1000', 'photos' => '[]', 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $this->db()->lastInsertId();
    }

    private function activity(): int
    {
        $this->db()->insert('activity', ['name' => 'Yoga' . uniqid(), 'category' => 'Bien-être']);

        return (int) $this->db()->lastInsertId();
    }

    /** Séance dont l'heure de début est relative à maintenant (UTC). */
    private function session(string $start, int $capacity, string $status = 'active', ?int $gym = null): int
    {
        $startAt = new \DateTimeImmutable($start, new \DateTimeZone('UTC'));
        $this->db()->insert('`session`', [
            'activity_id' => $this->activity(), 'gym_id' => $gym ?? $this->gym(), 'title' => 'Séance test',
            'start_at' => $startAt->format('Y-m-d H:i:s'), 'end_at' => $startAt->modify('+1 hour')->format('Y-m-d H:i:s'),
            'capacity' => $capacity, 'price' => '10.00', 'level' => 'debutant', 'status' => $status,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    private function review(int $author, int $gym, int $rating, bool $visible = true): void
    {
        $this->db()->insert('review', [
            'author_id' => $author, 'gym_id' => $gym, 'rating' => $rating,
            'is_moderated' => 0, 'is_visible' => $visible ? 1 : 0, 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function assertRejected(string $sql, string $reason): void
    {
        try {
            $this->db()->executeStatement($sql);
        } catch (DbalException) {
            $this->addToAssertionCount(1);

            return;
        }
        self::fail("La base aurait dû refuser la ligne ({$reason}).");
    }
}
