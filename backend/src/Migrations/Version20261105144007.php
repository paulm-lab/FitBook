<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma initial de FitBook v1 (docs/mld.md) : tables, clés, index et contraintes CHECK.
 *
 * Remplace les 21 migrations de la version précédente (modèle en français), conservées dans
 * l'historique Git. La base est reconstruite à partir de ce schéma et des fixtures (décision D1).
 * Les CHECK, que Doctrine ne génère pas, sont ajoutées à la fin de up().
 */
final class Version20261105144007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial FitBook v1 : 11 tables, clés étrangères, index et contraintes CHECK.';
    }

    /** CREATE / ALTER TABLE valident implicitement toute transaction MySQL : on n'en ouvre pas. */
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // --- Tables (générées par doctrine:migrations:diff à partir des entités) ---
        $this->addSql('CREATE TABLE activity (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, description LONGTEXT DEFAULT NULL, icon VARCHAR(255) DEFAULT NULL, category VARCHAR(50) NOT NULL, UNIQUE INDEX UNIQ_AC74095A5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE availability (id INT AUTO_INCREMENT NOT NULL, day_of_week SMALLINT DEFAULT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, is_recurring TINYINT NOT NULL, date DATE DEFAULT NULL, coach_id INT DEFAULT NULL, gym_id INT DEFAULT NULL, INDEX IDX_3FB7A2BF3C105691 (coach_id), INDEX IDX_3FB7A2BFBD2F03 (gym_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE coach_profile (id INT AUTO_INCREMENT NOT NULL, bio LONGTEXT DEFAULT NULL, specialities JSON NOT NULL, experience_years SMALLINT DEFAULT NULL, hourly_rate NUMERIC(8, 2) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, certifications LONGTEXT DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_3A874247A76ED395 (user_id), INDEX idx_coach_profile_city (city), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE favorite (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, gym_id INT DEFAULT NULL, coach_id INT DEFAULT NULL, session_id INT DEFAULT NULL, UNIQUE INDEX uq_favorite_user_gym (user_id, gym_id), UNIQUE INDEX uq_favorite_user_coach (user_id, coach_id), UNIQUE INDEX uq_favorite_user_session (user_id, session_id), INDEX IDX_68C58ED9A76ED395 (user_id), INDEX IDX_68C58ED9BD2F03 (gym_id), INDEX IDX_68C58ED93C105691 (coach_id), INDEX IDX_68C58ED9613FECDF (session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE gym (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, address VARCHAR(255) NOT NULL, city VARCHAR(100) NOT NULL, postal_code VARCHAR(10) NOT NULL, latitude NUMERIC(9, 6) DEFAULT NULL, longitude NUMERIC(9, 6) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, opening_hours JSON DEFAULT NULL, photos JSON NOT NULL, created_at DATETIME NOT NULL, manager_id INT NOT NULL, INDEX idx_gym_city (city), INDEX IDX_7F27DBED783E3463 (manager_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, message VARCHAR(255) NOT NULL, is_read TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_BF5476CAA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, cancelled_at DATETIME DEFAULT NULL, qr_token VARCHAR(64) DEFAULT NULL, reminder_sent_at DATETIME DEFAULT NULL, user_id INT NOT NULL, session_id INT NOT NULL, UNIQUE INDEX UNIQ_42C849551AE26361 (qr_token), INDEX idx_reservation_session_status (session_id, status), UNIQUE INDEX uq_reservation_user_session (user_id, session_id), INDEX IDX_42C84955A76ED395 (user_id), INDEX IDX_42C84955613FECDF (session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE review (id INT AUTO_INCREMENT NOT NULL, rating SMALLINT NOT NULL, comment LONGTEXT DEFAULT NULL, is_moderated TINYINT DEFAULT 0 NOT NULL, is_visible TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, author_id INT NOT NULL, gym_id INT DEFAULT NULL, coach_id INT DEFAULT NULL, session_id INT DEFAULT NULL, UNIQUE INDEX uq_review_author_gym (author_id, gym_id), UNIQUE INDEX uq_review_author_coach (author_id, coach_id), UNIQUE INDEX uq_review_author_session (author_id, session_id), INDEX IDX_794381C6F675F31B (author_id), INDEX IDX_794381C6BD2F03 (gym_id), INDEX IDX_794381C63C105691 (coach_id), INDEX IDX_794381C6613FECDF (session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `session` (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, start_at DATETIME NOT NULL, end_at DATETIME NOT NULL, capacity SMALLINT NOT NULL, price NUMERIC(8, 2) DEFAULT \'0.00\' NOT NULL, level VARCHAR(20) NOT NULL, status VARCHAR(20) DEFAULT \'active\' NOT NULL, activity_id INT NOT NULL, gym_id INT DEFAULT NULL, coach_id INT DEFAULT NULL, INDEX idx_session_status_start (status, start_at), INDEX IDX_D044D5D481C06096 (activity_id), INDEX IDX_D044D5D4BD2F03 (gym_id), INDEX IDX_D044D5D43C105691 (coach_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(50) NOT NULL, last_name VARCHAR(50) NOT NULL, phone VARCHAR(20) DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, is_verified TINYINT DEFAULT 0 NOT NULL, is_blocked TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE availability ADD CONSTRAINT FK_3FB7A2BF3C105691 FOREIGN KEY (coach_id) REFERENCES coach_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE availability ADD CONSTRAINT FK_3FB7A2BFBD2F03 FOREIGN KEY (gym_id) REFERENCES gym (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE coach_profile ADD CONSTRAINT FK_3A874247A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED9A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED9BD2F03 FOREIGN KEY (gym_id) REFERENCES gym (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED93C105691 FOREIGN KEY (coach_id) REFERENCES coach_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED9613FECDF FOREIGN KEY (session_id) REFERENCES `session` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gym ADD CONSTRAINT FK_7F27DBED783E3463 FOREIGN KEY (manager_id) REFERENCES `user` (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955613FECDF FOREIGN KEY (session_id) REFERENCES `session` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6BD2F03 FOREIGN KEY (gym_id) REFERENCES gym (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C63C105691 FOREIGN KEY (coach_id) REFERENCES coach_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6613FECDF FOREIGN KEY (session_id) REFERENCES `session` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `session` ADD CONSTRAINT FK_D044D5D481C06096 FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE `session` ADD CONSTRAINT FK_D044D5D4BD2F03 FOREIGN KEY (gym_id) REFERENCES gym (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE `session` ADD CONSTRAINT FK_D044D5D43C105691 FOREIGN KEY (coach_id) REFERENCES coach_profile (id) ON DELETE RESTRICT');

        // La table de suivi des migrations est créée par Doctrine avant cette migration,
        // avec le moteur par défaut du serveur (MyISAM sous WAMP) : on l'aligne sur InnoDB.
        $this->addSql('ALTER TABLE doctrine_migration_versions ENGINE = InnoDB');

        // --- Contraintes CHECK : dernière ligne de défense, même si l'application les vérifie déjà ---

        // Séance : dates cohérentes, capacité et prix positifs, valeurs d'énumération, salle ou coach (RG-09, RG-10).
        $this->addSql('ALTER TABLE `session` ADD CONSTRAINT chk_session_dates CHECK (end_at > start_at)');
        $this->addSql('ALTER TABLE `session` ADD CONSTRAINT chk_session_capacity CHECK (capacity >= 1)');
        $this->addSql('ALTER TABLE `session` ADD CONSTRAINT chk_session_price CHECK (price >= 0)');
        $this->addSql("ALTER TABLE `session` ADD CONSTRAINT chk_session_level CHECK (level IN ('debutant', 'intermediaire', 'avance'))");
        $this->addSql("ALTER TABLE `session` ADD CONSTRAINT chk_session_status CHECK (status IN ('active', 'annulee'))");
        $this->addSql('ALTER TABLE `session` ADD CONSTRAINT chk_session_host CHECK (gym_id IS NOT NULL OR coach_id IS NOT NULL)');

        // Réservation : statut connu.
        $this->addSql("ALTER TABLE reservation ADD CONSTRAINT chk_reservation_status CHECK (status IN ('confirmee', 'annulee', 'terminee'))");

        // Disponibilité : exactement un propriétaire, horaires cohérents, récurrente ou ponctuelle.
        $this->addSql('ALTER TABLE availability ADD CONSTRAINT chk_availability_owner CHECK ((coach_id IS NOT NULL) + (gym_id IS NOT NULL) = 1)');
        $this->addSql('ALTER TABLE availability ADD CONSTRAINT chk_availability_times CHECK (end_time > start_time)');
        $this->addSql('ALTER TABLE availability ADD CONSTRAINT chk_availability_kind CHECK ((is_recurring = 1 AND day_of_week BETWEEN 1 AND 7) OR (is_recurring = 0 AND `date` IS NOT NULL))');

        // Favori et avis : exactement une cible ; type de favori cohérent avec la cible remplie.
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT chk_favorite_target CHECK ((gym_id IS NOT NULL) + (coach_id IS NOT NULL) + (session_id IS NOT NULL) = 1)');
        $this->addSql("ALTER TABLE favorite ADD CONSTRAINT chk_favorite_type CHECK ((type = 'gym' AND gym_id IS NOT NULL) OR (type = 'coach' AND coach_id IS NOT NULL) OR (type = 'session' AND session_id IS NOT NULL))");
        $this->addSql('ALTER TABLE review ADD CONSTRAINT chk_review_target CHECK ((gym_id IS NOT NULL) + (coach_id IS NOT NULL) + (session_id IS NOT NULL) = 1)');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5)');
    }

    public function down(Schema $schema): void
    {
        // Les CHECK disparaissent avec leurs tables.
        $this->addSql('ALTER TABLE availability DROP FOREIGN KEY FK_3FB7A2BF3C105691');
        $this->addSql('ALTER TABLE availability DROP FOREIGN KEY FK_3FB7A2BFBD2F03');
        $this->addSql('ALTER TABLE coach_profile DROP FOREIGN KEY FK_3A874247A76ED395');
        $this->addSql('ALTER TABLE favorite DROP FOREIGN KEY FK_68C58ED9A76ED395');
        $this->addSql('ALTER TABLE favorite DROP FOREIGN KEY FK_68C58ED9BD2F03');
        $this->addSql('ALTER TABLE favorite DROP FOREIGN KEY FK_68C58ED93C105691');
        $this->addSql('ALTER TABLE favorite DROP FOREIGN KEY FK_68C58ED9613FECDF');
        $this->addSql('ALTER TABLE gym DROP FOREIGN KEY FK_7F27DBED783E3463');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955A76ED395');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955613FECDF');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C6F675F31B');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C6BD2F03');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C63C105691');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C6613FECDF');
        $this->addSql('ALTER TABLE `session` DROP FOREIGN KEY FK_D044D5D481C06096');
        $this->addSql('ALTER TABLE `session` DROP FOREIGN KEY FK_D044D5D4BD2F03');
        $this->addSql('ALTER TABLE `session` DROP FOREIGN KEY FK_D044D5D43C105691');
        $this->addSql('DROP TABLE activity');
        $this->addSql('DROP TABLE availability');
        $this->addSql('DROP TABLE coach_profile');
        $this->addSql('DROP TABLE favorite');
        $this->addSql('DROP TABLE gym');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE review');
        $this->addSql('DROP TABLE `session`');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
