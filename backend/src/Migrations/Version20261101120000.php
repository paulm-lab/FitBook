<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align séance and reservation amounts with DECIMAL(10,2), and add reservation cancellation date.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE seance MODIFY prix NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE reservation CHANGE montant_total prix_total NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE reservation ADD date_annulation DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Amounts may contain cents and cancellation dates may have been recorded.');
    }
}