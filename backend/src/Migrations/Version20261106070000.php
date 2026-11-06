<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * API bilingue : langue de chaque compte (fr ou en), pour ses e-mails et notifications.
 * Les comptes existants restent en français, langue par défaut.
 */
final class Version20261106070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute user.locale (fr par défaut) : langue des e-mails et notifications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` ADD locale VARCHAR(2) DEFAULT 'fr' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP locale');
    }
}
