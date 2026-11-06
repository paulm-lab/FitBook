-- =============================================================================
-- FitBook — création des bases et de l'utilisateur MySQL dédié `fitbook_user`
-- =============================================================================
-- L'application ne se connecte JAMAIS en root : elle utilise `fitbook_user`, dont les
-- droits sont limités à la base `fitbookdata` (et à sa base de tests `fitbookdata_test`).
--
-- Aucun mot de passe n'est écrit ici. Il est fourni au lancement, en tant que root :
--
--   mysql -u root -p -e "SET @fitbook_password = 'mot-de-passe-fort'; SOURCE database/scripts/create_user.sql;"
--
-- Hôte autorisé : 'localhost' par défaut (WAMP). Sous Docker, l'application se connecte
-- depuis le réseau Docker : le script d'initialisation fixe @fitbook_host = '%'.
--
-- Le mot de passe est ensuite reporté dans backend/.env.local (fichier non versionné) :
--   DATABASE_URL="mysql://fitbook_user:mot-de-passe-fort@127.0.0.1:3306/fitbookdata?serverVersion=8.0.32&charset=utf8mb4"
-- =============================================================================

SET @fitbook_host = COALESCE(NULLIF(@fitbook_host, ''), 'localhost');

-- Arrêt immédiat si le mot de passe n'a pas été fourni : CONCAT renverra NULL
-- et PREPARE échouera, sans rien créer.
SELECT IF(COALESCE(@fitbook_password, '') = '',
          'ERREUR : définissez @fitbook_password avant de lancer ce script.',
          CONCAT('Création de fitbook_user@', @fitbook_host)) AS verification;

CREATE DATABASE IF NOT EXISTS `fitbookdata` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `fitbookdata_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CREATE USER et GRANT n'acceptent pas de variable : les requêtes sont construites puis
-- préparées. QUOTE() échappe les valeurs (aucune injection possible via les variables).
SET @account = CONCAT('''fitbook_user''@', QUOTE(@fitbook_host));

SET @sql = CONCAT('CREATE USER IF NOT EXISTS ', @account, ' IDENTIFIED BY ', QUOTE(NULLIF(@fitbook_password, '')));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Relance du script : le mot de passe est mis à jour.
SET @sql = CONCAT('ALTER USER ', @account, ' IDENTIFIED BY ', QUOTE(NULLIF(@fitbook_password, '')));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Droits limités aux deux bases du projet :
--   - données : SELECT, INSERT, UPDATE, DELETE
--   - migrations Doctrine : CREATE, ALTER, DROP, INDEX, REFERENCES
--   - procédures stockées : CREATE ROUTINE, ALTER ROUTINE, EXECUTE
--   - sauvegardes mysqldump : LOCK TABLES, SHOW VIEW
-- Aucun droit global (pas de FILE, SUPER, PROCESS, GRANT OPTION…).
SET @privileges = 'SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES, CREATE TEMPORARY TABLES, CREATE ROUTINE, ALTER ROUTINE, EXECUTE, LOCK TABLES, SHOW VIEW';

SET @sql = CONCAT('GRANT ', @privileges, ' ON `fitbookdata`.* TO ', @account);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('GRANT ', @privileges, ' ON `fitbookdata_test`.* TO ', @account);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Le mot de passe ne reste pas en mémoire de session.
SET @sql = NULL;
SET @fitbook_password = NULL;

FLUSH PRIVILEGES;

SET @sql = CONCAT('SHOW GRANTS FOR ', @account);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
