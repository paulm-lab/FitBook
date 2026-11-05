<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101130100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore mandatory session foreign keys after enforcing non-null MCD relations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0E3C105691 FOREIGN KEY (coach_id) REFERENCES coach (id)');
        $this->addSql('ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0EDC304035 FOREIGN KEY (salle_id) REFERENCES salle (id)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The session relations are required by the FitBook MCD.');
    }
}
