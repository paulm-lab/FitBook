<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align FitBook entities with the MCD while preserving legacy columns and data.';
    }

    public function up(Schema $schema): void
    {
        $this->assertMaxLength('utilisateur', 'nom', 50);
        $this->assertMaxLength('utilisateur', 'prenom', 50);
        $this->assertMaxLength('utilisateur', 'email', 100);
        $this->assertMaxLength('utilisateur', 'role', 20);
        $this->assertMaxLength('activite', 'nom', 50);
        $this->assertMaxLength('activite', 'description', 255);
        $this->assertMaxLength('coach', 'specialite', 50);
        $this->assertMaxLength('coach', $this->columnExists('coach', 'description') ? 'description' : 'biographie', 255);
        $this->assertMaxLength('salle', 'nom', 50);
        $this->assertMaxLength('salle', 'adresse', 100);
        $this->assertMaxLength('salle', 'description', 255);

        foreach (['activite', 'coach', 'salle', 'utilisateur'] as $table) {
            $this->addSql("UPDATE {$table} SET statut = CASE WHEN CAST(statut AS CHAR) IN ('1', 'actif') THEN 'actif' ELSE 'inactif' END");
        }

        $this->addColumnIfMissing('activite', 'image', 'ALTER TABLE activite ADD image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE activite CHANGE nom nom VARCHAR(50) NOT NULL, CHANGE description description VARCHAR(255) NOT NULL, CHANGE statut statut VARCHAR(20) NOT NULL');

        $this->addColumnIfMissing('avis', 'statut', "ALTER TABLE avis ADD statut VARCHAR(20) DEFAULT 'actif' NOT NULL");
        $this->addColumnIfMissing('avis', 'salle_id', 'ALTER TABLE avis ADD salle_id INT DEFAULT NULL');
        $this->addColumnIfMissing('avis', 'coach_id', 'ALTER TABLE avis ADD coach_id INT DEFAULT NULL');
        $this->addColumnIfMissing('avis', 'activite_id', 'ALTER TABLE avis ADD activite_id INT DEFAULT NULL');
        $this->addSql('UPDATE avis a INNER JOIN seance s ON s.id = a.seance_id SET a.activite_id = s.activite_id WHERE a.salle_id IS NULL AND a.coach_id IS NULL AND a.activite_id IS NULL');
        $this->addSql('ALTER TABLE avis CHANGE commentaire commentaire VARCHAR(255) NOT NULL, CHANGE date_avis date_avis DATE NOT NULL, CHANGE seance_id seance_id INT DEFAULT NULL, MODIFY statut VARCHAR(20) NOT NULL');
        $this->addForeignKeyIfMissing('avis', 'FK_8F91ABF0DC304035', 'ALTER TABLE avis ADD CONSTRAINT FK_8F91ABF0DC304035 FOREIGN KEY (salle_id) REFERENCES salle (id) ON DELETE RESTRICT');
        $this->addForeignKeyIfMissing('avis', 'FK_8F91ABF03C105691', 'ALTER TABLE avis ADD CONSTRAINT FK_8F91ABF03C105691 FOREIGN KEY (coach_id) REFERENCES coach (id) ON DELETE RESTRICT');
        $this->addForeignKeyIfMissing('avis', 'FK_8F91ABF09B0F88B1', 'ALTER TABLE avis ADD CONSTRAINT FK_8F91ABF09B0F88B1 FOREIGN KEY (activite_id) REFERENCES activite (id) ON DELETE RESTRICT');
        $this->addIndexIfMissing('avis', 'IDX_8F91ABF0DC304035', 'CREATE INDEX IDX_8F91ABF0DC304035 ON avis (salle_id)');
        $this->addIndexIfMissing('avis', 'IDX_8F91ABF03C105691', 'CREATE INDEX IDX_8F91ABF03C105691 ON avis (coach_id)');
        $this->addIndexIfMissing('avis', 'IDX_8F91ABF09B0F88B1', 'CREATE INDEX IDX_8F91ABF09B0F88B1 ON avis (activite_id)');
        $this->addCheckIfMissing('avis', 'chk_avis_exactly_one_target', 'ALTER TABLE avis ADD CONSTRAINT chk_avis_exactly_one_target CHECK ((salle_id IS NOT NULL) + (coach_id IS NOT NULL) + (activite_id IS NOT NULL) = 1)');

        $this->addColumnIfMissing('coach', 'experience', 'ALTER TABLE coach ADD experience VARCHAR(100) DEFAULT NULL');
        $this->addColumnIfMissing('coach', 'certification', 'ALTER TABLE coach ADD certification VARCHAR(100) DEFAULT NULL');
        $this->addColumnIfMissing('coach', 'tarif_horaire', 'ALTER TABLE coach ADD tarif_horaire NUMERIC(10, 2) DEFAULT NULL');
        $this->addColumnIfMissing('coach', 'photo', 'ALTER TABLE coach ADD photo VARCHAR(255) DEFAULT NULL');
        if ($this->columnExists('coach', 'description')) {
            $this->addSql('ALTER TABLE coach CHANGE description biographie VARCHAR(255) NOT NULL');
        }
        $this->addSql('ALTER TABLE coach CHANGE specialite specialite VARCHAR(50) NOT NULL, CHANGE statut statut VARCHAR(20) NOT NULL');

        $this->addColumnIfMissing('favori', 'date_ajout', 'ALTER TABLE favori ADD date_ajout DATE DEFAULT NULL');
        $this->addColumnIfMissing('favori', 'salle_id', 'ALTER TABLE favori ADD salle_id INT DEFAULT NULL');
        $this->addColumnIfMissing('favori', 'coach_id', 'ALTER TABLE favori ADD coach_id INT DEFAULT NULL');
        $this->addColumnIfMissing('favori', 'activite_id', 'ALTER TABLE favori ADD activite_id INT DEFAULT NULL');
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM favori WHERE date_ajout IS NULL AND STR_TO_DATE(LEFT(datetime_immutable, 10), '%Y-%m-%d') IS NULL") > 0, 'Legacy favorite dates need an explicit mapping before this migration can continue.');
        $this->addSql("UPDATE favori SET date_ajout = STR_TO_DATE(LEFT(datetime_immutable, 10), '%Y-%m-%d') WHERE date_ajout IS NULL");
        $this->addSql('UPDATE favori f INNER JOIN seance s ON s.id = f.seance_id SET f.activite_id = s.activite_id WHERE f.salle_id IS NULL AND f.coach_id IS NULL AND f.activite_id IS NULL');
        $this->addSql('ALTER TABLE favori MODIFY date_ajout DATE NOT NULL, CHANGE seance_id seance_id INT DEFAULT NULL');
        $this->addForeignKeyIfMissing('favori', 'FK_EF85A2CCDC304035', 'ALTER TABLE favori ADD CONSTRAINT FK_EF85A2CCDC304035 FOREIGN KEY (salle_id) REFERENCES salle (id) ON DELETE RESTRICT');
        $this->addForeignKeyIfMissing('favori', 'FK_EF85A2CC3C105691', 'ALTER TABLE favori ADD CONSTRAINT FK_EF85A2CC3C105691 FOREIGN KEY (coach_id) REFERENCES coach (id) ON DELETE RESTRICT');
        $this->addForeignKeyIfMissing('favori', 'FK_EF85A2CC9B0F88B1', 'ALTER TABLE favori ADD CONSTRAINT FK_EF85A2CC9B0F88B1 FOREIGN KEY (activite_id) REFERENCES activite (id) ON DELETE RESTRICT');
        $this->addIndexIfMissing('favori', 'IDX_EF85A2CCDC304035', 'CREATE INDEX IDX_EF85A2CCDC304035 ON favori (salle_id)');
        $this->addIndexIfMissing('favori', 'IDX_EF85A2CC3C105691', 'CREATE INDEX IDX_EF85A2CC3C105691 ON favori (coach_id)');
        $this->addIndexIfMissing('favori', 'IDX_EF85A2CC9B0F88B1', 'CREATE INDEX IDX_EF85A2CC9B0F88B1 ON favori (activite_id)');
        $this->addCheckIfMissing('favori', 'chk_favori_exactly_one_target', 'ALTER TABLE favori ADD CONSTRAINT chk_favori_exactly_one_target CHECK ((salle_id IS NOT NULL) + (coach_id IS NOT NULL) + (activite_id IS NOT NULL) = 1)');

        $this->addSql('ALTER TABLE reservation CHANGE date_reservation date_reservation DATE NOT NULL, CHANGE statut statut VARCHAR(20) NOT NULL');
        $this->addSql("UPDATE salle SET statut = CASE WHEN CAST(statut AS CHAR) IN ('1', 'actif') THEN 'actif' ELSE 'inactif' END");
        $this->addColumnIfMissing('salle', 'equipements', 'ALTER TABLE salle ADD equipements VARCHAR(255) DEFAULT NULL');
        $this->addColumnIfMissing('salle', 'image', 'ALTER TABLE salle ADD image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE salle CHANGE nom nom VARCHAR(50) NOT NULL, CHANGE adresse adresse VARCHAR(100) NOT NULL, CHANGE description description VARCHAR(255) NOT NULL, CHANGE statut statut VARCHAR(20) NOT NULL');

        $this->addColumnIfMissing('seance', 'date_seance', 'ALTER TABLE seance ADD date_seance DATE DEFAULT NULL');
        $this->addColumnIfMissing('seance', 'heure_debut', 'ALTER TABLE seance ADD heure_debut TIME DEFAULT NULL');
        $this->addColumnIfMissing('seance', 'heure_fin', 'ALTER TABLE seance ADD heure_fin TIME DEFAULT NULL');
        $this->addColumnIfMissing('seance', 'capacite_max', 'ALTER TABLE seance ADD capacite_max INT DEFAULT NULL');
        $this->addColumnIfMissing('seance', 'statut', "ALTER TABLE seance ADD statut VARCHAR(20) DEFAULT NULL");
        $this->addSql("UPDATE seance SET date_seance = COALESCE(date_seance, DATE(date_debut)), heure_debut = COALESCE(heure_debut, TIME(date_debut)), heure_fin = COALESCE(heure_fin, TIME(date_fin)), capacite_max = COALESCE(capacite_max, places_disponibles), statut = COALESCE(statut, 'actif')");
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM seance WHERE coach_id IS NULL OR salle_id IS NULL') > 0, 'Every existing session must have a coach and room before enforcing the MCD relations.');

        if ($this->foreignKeyExists('seance', 'FK_DF7DFD0E3C105691')) {
            $this->addSql('ALTER TABLE seance DROP FOREIGN KEY FK_DF7DFD0E3C105691');
        }
        if ($this->foreignKeyExists('seance', 'FK_DF7DFD0EDC304035')) {
            $this->addSql('ALTER TABLE seance DROP FOREIGN KEY FK_DF7DFD0EDC304035');
        }
        $this->addSql('ALTER TABLE seance MODIFY date_seance DATE NOT NULL, MODIFY heure_debut TIME NOT NULL, MODIFY heure_fin TIME NOT NULL, MODIFY capacite_max INT NOT NULL, MODIFY statut VARCHAR(20) NOT NULL, CHANGE coach_id coach_id INT NOT NULL, CHANGE salle_id salle_id INT NOT NULL');
        $this->addForeignKeyIfMissing('seance', 'FK_DF7DFD0E3C105691', 'ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0E3C105691 FOREIGN KEY (coach_id) REFERENCES coach (id)');
        $this->addForeignKeyIfMissing('seance', 'FK_DF7DFD0EDC304035', 'ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0EDC304035 FOREIGN KEY (salle_id) REFERENCES salle (id)');

        $this->addSql("UPDATE utilisateur SET statut = CASE WHEN CAST(statut AS CHAR) IN ('1', 'actif') THEN 'actif' ELSE 'inactif' END");
        $this->addSql('ALTER TABLE utilisateur CHANGE nom nom VARCHAR(50) NOT NULL, CHANGE prenom prenom VARCHAR(50) NOT NULL, CHANGE email email VARCHAR(100) NOT NULL, CHANGE role role VARCHAR(20) NOT NULL, CHANGE date_inscription date_inscription DATE NOT NULL, CHANGE statut statut VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('This migration changes status representations and adds required targets; reverting could lose information.');
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }

    private function addColumnIfMissing(string $table, string $column, string $sql): void
    {
        if (!$this->columnExists($table, $column)) {
            $this->addSql($sql);
        }
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $name, 'FOREIGN KEY']
        );
    }

    private function addForeignKeyIfMissing(string $table, string $name, string $sql): void
    {
        if (!$this->foreignKeyExists($table, $name)) {
            $this->addSql($sql);
        }
    }

    private function addIndexIfMissing(string $table, string $name, string $sql): void
    {
        if (!(bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $name]
        )) {
            $this->addSql($sql);
        }
    }

    private function addCheckIfMissing(string $table, string $name, string $sql): void
    {
        if (!(bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $name, 'CHECK']
        )) {
            $this->addSql($sql);
        }
    }

    private function assertMaxLength(string $table, string $column, int $maximum): void
    {
        $count = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM {$table} WHERE CHAR_LENGTH({$column}) > ?",
            [$maximum]
        );
        $this->abortIf($count > 0, sprintf('%s.%s contains values longer than %d characters.', $table, $column, $maximum));
    }
}