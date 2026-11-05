#!/usr/bin/env bash
# =============================================================================
# FitBook — sauvegarde de la base MySQL avec l'utilisateur dédié fitbook_user
# =============================================================================
# Usage (depuis la racine du dépôt) :
#   bash database/scripts/backup.sh              → database/backups/fitbook_AAAA-MM-JJ_HHMMSS.sql
#   bash database/scripts/backup.sh 02_milieu    → database/backups/02_milieu.sql
#
# Identifiants : lus dans DATABASE_URL de backend/.env.local (jamais écrits dans ce script).
# Le mot de passe est transmis à mysqldump par un fichier d'options temporaire (droits 600),
# jamais en argument : il n'apparaît ni dans la liste des processus ni dans l'historique.
#
# Variables facultatives :
#   MYSQLDUMP   chemin de mysqldump (par défaut : celui du PATH, sinon celui de WAMP)
#   ENV_FILE    fichier contenant DATABASE_URL (par défaut : backend/.env.local)
# =============================================================================
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_FILE="${ENV_FILE:-$ROOT_DIR/backend/.env.local}"
BACKUP_DIR="$ROOT_DIR/database/backups"
NAME="${1:-fitbook_$(date +%Y-%m-%d_%H%M%S)}"
OUTPUT="$BACKUP_DIR/$NAME.sql"

# --- Localisation de mysqldump --------------------------------------------------
if [[ -z "${MYSQLDUMP:-}" ]]; then
    if command -v mysqldump >/dev/null 2>&1; then
        MYSQLDUMP="mysqldump"
    else
        # Poste WAMP : version de MySQL la plus récente installée.
        MYSQLDUMP="$(ls -d /c/wamp64/bin/mysql/mysql*/bin/mysqldump.exe 2>/dev/null | sort -V | tail -1 || true)"
    fi
fi
if [[ -z "$MYSQLDUMP" ]]; then
    echo "ERREUR : mysqldump introuvable. Définissez la variable MYSQLDUMP." >&2
    exit 1
fi

# --- Lecture de DATABASE_URL ------------------------------------------------------
if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERREUR : $ENV_FILE introuvable (il doit contenir DATABASE_URL)." >&2
    exit 1
fi
URL="$(grep -E '^DATABASE_URL=' "$ENV_FILE" | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'")"

# Format attendu : mysql://utilisateur:motdepasse@hote:port/base?options
if [[ ! "$URL" =~ ^mysql://([^:]+):([^@]*)@([^:/]+):?([0-9]*)/([^?]+) ]]; then
    echo "ERREUR : DATABASE_URL n'est pas une URL MySQL valide." >&2
    exit 1
fi
DB_USER="${BASH_REMATCH[1]}"
DB_PASS="$(printf '%b' "${BASH_REMATCH[2]//%/\\x}")"   # décode l'encodage URL (%40 → @)
DB_HOST="${BASH_REMATCH[3]}"
DB_PORT="${BASH_REMATCH[4]:-3306}"
DB_NAME="${BASH_REMATCH[5]}"

if [[ "$DB_USER" == "root" ]]; then
    echo "ERREUR : la sauvegarde doit utiliser fitbook_user, jamais root." >&2
    exit 1
fi

# --- Fichier d'options temporaire (supprimé quoi qu'il arrive) ---------------------
OPTIONS_FILE="$(mktemp)"
trap 'rm -f "$OPTIONS_FILE"' EXIT
chmod 600 "$OPTIONS_FILE"
printf '[client]\nuser=%s\npassword="%s"\nhost=%s\nport=%s\n' "$DB_USER" "$DB_PASS" "$DB_HOST" "$DB_PORT" > "$OPTIONS_FILE"

# mysqldump sous Windows attend un chemin Windows.
if command -v cygpath >/dev/null 2>&1; then
    OPTIONS_ARG="$(cygpath -w "$OPTIONS_FILE")"
else
    OPTIONS_ARG="$OPTIONS_FILE"
fi

# --- Sauvegarde ---------------------------------------------------------------------
#   --single-transaction : instantané cohérent sans bloquer l'application (InnoDB)
#   --routines --triggers : inclut les procédures stockées
#   --no-tablespaces     : évite le privilège PROCESS, que fitbook_user n'a pas
mkdir -p "$BACKUP_DIR"
"$MYSQLDUMP" --defaults-extra-file="$OPTIONS_ARG" \
    --single-transaction --routines --triggers --no-tablespaces \
    --default-character-set=utf8mb4 --skip-dump-date \
    "$DB_NAME" > "$OUTPUT"

echo "Sauvegarde créée : ${OUTPUT#$ROOT_DIR/} ($(wc -c < "$OUTPUT") octets)"
