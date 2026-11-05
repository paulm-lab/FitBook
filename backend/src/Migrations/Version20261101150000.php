<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable email verification data and a unique user email index.';
    }

    public function up(Schema $schema): void
    {
        $duplicates = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM (SELECT email FROM utilisateur GROUP BY email HAVING COUNT(*) > 1) duplicate_emails');
        $this->abortIf($duplicates > 0, 'Duplicate user email addresses must be resolved before enabling unique email addresses.');
        $this->addSql('ALTER TABLE utilisateur ADD email_verified_at DATETIME DEFAULT NULL, ADD verification_token_hash VARCHAR(64) DEFAULT NULL, ADD verification_token_expires_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1D1C63B3E7927C74 ON utilisateur (email)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Email verification fields are part of the account security flow.');
    }
}