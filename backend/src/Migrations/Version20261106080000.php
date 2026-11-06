<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Inscription directe (choix du porteur du projet) : les comptes créés pendant la période où la
 * confirmation d'adresse était exigée deviennent actifs.
 */
final class Version20261106080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Active les comptes en attente de confirmation d’adresse (inscription directe).';
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
