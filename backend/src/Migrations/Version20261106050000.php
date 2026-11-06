<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fin de la confirmation d'adresse obligatoire : les comptes en attente deviennent actifs.
 */
final class Version20261106050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Active les comptes créés avant la suppression de la vérification par e-mail.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE `user` SET is_verified = 1 WHERE is_verified = 0');
    }

    public function down(Schema $schema): void
    {
        // Irréversible : on ne sait plus quels comptes n'avaient pas confirmé leur adresse.
    }
}
