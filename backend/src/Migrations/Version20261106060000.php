<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Réinstalle sp_reserver_seance depuis database/procedures/ : une réservation réactivée
 * remet reminder_sent_at à NULL (le rappel de 24 h doit être renvoyé).
 *
 * Même lecture du fichier que Version20261105150000 : le fichier .sql commence par
 * DROP PROCEDURE IF EXISTS, ses directives DELIMITER sont retirées avant l'exécution via PDO.
 */
final class Version20261106060000 extends AbstractMigration
{
    private const PROCEDURE = 'sp_reserver_seance';

    public function getDescription(): string
    {
        return 'sp_reserver_seance : remise à zéro du rappel lors de la réactivation d’une réservation.';
    }

    /** CREATE PROCEDURE valide implicitement toute transaction MySQL : on n'en ouvre pas. */
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Les procédures stockées nécessitent MySQL.',
        );

        foreach ($this->statementsOf(self::PROCEDURE) as $statement) {
            $this->addSql($statement);
        }
    }

    public function down(Schema $schema): void
    {
        // La version précédente n'est plus versionnée : la procédure actuelle reste en place
        // (la remise à zéro du rappel est sans effet sur les autres règles).
        $this->addSql('SELECT 1');
    }

    /**
     * Découpe un fichier de procédure en instructions exécutables par PDO.
     *
     * @return list<string>
     */
    private function statementsOf(string $name): array
    {
        $path = \dirname(__DIR__, 3) . '/database/procedures/' . $name . '.sql';
        $this->abortIf(!is_file($path), sprintf('Fichier de procédure introuvable : %s', $path));

        $sql = (string) file_get_contents($path);

        // Le corps des procédures est délimité par « $$ » ; le reste par « ; ».
        $statements = [];
        $delimiter = ';';
        $buffer = '';
        foreach (preg_split('/\R/', $sql) as $line) {
            if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $match)) {
                $delimiter = $match[1];
                continue;
            }
            // Les commentaires de ligne sont ignorés (documentation du fichier).
            if (str_starts_with(ltrim($line), '--')) {
                continue;
            }

            $buffer .= $line . "\n";
            if (str_ends_with(rtrim($line), $delimiter)) {
                $statement = trim(substr(rtrim($buffer), 0, -\strlen($delimiter)));
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
            }
        }

        $this->abortIf(trim($buffer) !== '', sprintf('Instruction non terminée dans %s.sql', $name));

        return $statements;
    }
}
