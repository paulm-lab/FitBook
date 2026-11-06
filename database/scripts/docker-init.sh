#!/bin/sh
# =============================================================================
# FitBook — initialisation du conteneur MySQL (exécuté une seule fois, au premier démarrage)
# =============================================================================
# Crée fitbook_user avec les droits limités de create_user.sql. Sous Docker, l'application
# se connecte depuis le réseau Docker : l'hôte autorisé est donc '%' (et non 'localhost').
# Le mot de passe vient de la variable FITBOOK_DB_PASSWORD (fichier .env, non versionné).
# =============================================================================
set -eu

# Échappe le mot de passe pour la chaîne SQL : d'abord les barres obliques inverses (caractère
# d'échappement de MySQL, sinon « \n » deviendrait un saut de ligne), puis les apostrophes.
# Le document « here » ne réinterprète pas le contenu de la variable ($, `, \ restent intacts).
PASSWORD_SQL=$(printf '%s' "$FITBOOK_DB_PASSWORD" | sed -e 's/\\/\\\\/g' -e "s/'/''/g")

mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<SQL
SET @fitbook_host = '%';
SET @fitbook_password = '$PASSWORD_SQL';
SOURCE /fitbook/create_user.sql;
SQL
