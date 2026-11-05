<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée les 3 procédures stockées à partir des fichiers versionnés dans database/procedures/.
 *
 * Les fichiers .sql restent la seule source : ils s'exécutent tels quels dans le client
 * `mysql` (grâce à DELIMITER), et cette migration en retire les directives DELIMITER,
 * inutiles via PDO, avant d'exécuter chaque instruction.
 */
final class Version20261105150000 extends AbstractMigration
{
    /** Ordre d'installation des procédures. */
    private const PROCEDURES = [
        'sp_reserver_seance',
        'sp_annuler_reservation',
        'sp_stats_salle',
    ];

    public function getDescription(): string
    {
        return 'Procédures stockées : sp_reserver_seance, sp_annuler_reservation, sp_stats_salle.';
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

        foreach (self::PROCEDURES as $name) {
            foreach ($this->statementsOf($name) as $statement) {
                $this->addSql($statement);
            }
        }
    }

    public function down(Schema $schema): void
    {
        foreach (array_reverse(self::PROCEDURES) as $name) {
            $this->addSql(sprintf('DROP PROCEDURE IF EXISTS %s', $name));
        }
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
