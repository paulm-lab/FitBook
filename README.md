# FitBook

Plateforme web de réservation de **salles de sport**, de **cours** et de **coachs sportifs**.
Projet de formation **UAA MI08 — Produire un projet Web**.

> 🚧 **Phase 3 — Authentification, rôles et voters** terminée.
> Le guide d'installation complet (frontend compris) sera finalisé en Phase 10.

---

## Documentation

| Document | Contenu |
|----------|---------|
| [Cahier des charges](docs/cahier-des-charges.md) | Contexte, objectifs, périmètre, règles de gestion, charte graphique, planning |
| [Analyse de l'existant](docs/analyse-existant.md) | Version précédente, écarts avec l'énoncé, décisions D1 à D6 |
| [User stories](docs/user-stories.md) | 32 stories par rôle, avec critères d'acceptation |
| [MCD](docs/mcd.md) | Modèle conceptuel (à reproduire dans JMerise) |
| [MLD](docs/mld.md) | Modèle logique MySQL, contraintes, procédures stockées, choix techniques |
| [Cas d'utilisation](docs/use-cases.md) | Acteurs, diagramme et scénarios détaillés (à reproduire dans StarUML) |
| [Diagramme de classes](docs/diagramme-classes.md) | Domaine et architecture backend (à reproduire dans StarUML) |

## Stack

| Couche | Technologies |
|--------|--------------|
| Frontend | Angular 21 (standalone, signals, lazy loading) |
| Backend | PHP 8.3, Symfony 7.4 (API REST JSON), Doctrine ORM, Symfony Mailer + Messenger |
| Base de données | MySQL 8+ (InnoDB), utilisateur dédié `fitbook_user`, 3 procédures stockées |
| E-mails (dev) | Mailpit |
| Tests | PHPUnit (unitaires, fonctionnels, intégration MySQL), tests Angular, Postman + Newman |

## Arborescence

```text
fitbook-mi08/
├── backend/            # API Symfony
├── frontend/           # Application Angular
├── database/
│   ├── procedures/     # sp_reserver_seance, sp_annuler_reservation, sp_stats_salle
│   ├── backups/        # Dumps jalons : 01_debut.sql, 02_milieu.sql, 03_fin.sql
│   └── scripts/        # create_user.sql, backup.sh, docker-init.sh
├── postman/            # Collection, environnement, rapport Newman
├── docs/               # Documentation d'analyse et technique
├── docker-compose.yml  # MySQL 8 + phpMyAdmin + Mailpit
└── README.md
```

## Installation du backend (état Phase 3)

Prérequis : PHP 8.3, Composer, [Symfony CLI](https://symfony.com/download), MySQL 8+
(WAMP ou Docker) et [Mailpit](https://mailpit.axllent.org/) pour les e-mails.

### 1. Base de données et utilisateur `fitbook_user`

**Avec WAMP** — en tant que root, en choisissant un mot de passe fort :

```bash
mysql -u root -p -e "SET @fitbook_password = 'mot-de-passe-fort'; SOURCE database/scripts/create_user.sql;"
```

**Avec Docker** — copier `.env.example` en `.env`, y choisir les mots de passe, puis :

```bash
docker compose up -d      # MySQL sur 127.0.0.1:3307, phpMyAdmin sur :8080, Mailpit sur :8025
```

Le script crée les bases `fitbook` et `fitbook_test` et l'utilisateur `fitbook_user`, dont les droits
sont limités à ces deux bases. L'application ne se connecte jamais en `root`.

### 2. Configuration locale (`backend/.env.local`, jamais versionné)

```dotenv
DATABASE_URL="mysql://fitbook_user:mot-de-passe-fort@127.0.0.1:3306/fitbook?serverVersion=8.0.32&charset=utf8mb4"
MAILER_DSN=smtp://127.0.0.1:1025
# Passphrase de la clé privée JWT : une longue chaîne aléatoire.
JWT_PASSPHRASE=une-longue-phrase-secrete-aleatoire
```

(Avec Docker, utiliser le port `3307`.)

### 3. Dépendances, clés JWT, schéma et données de démonstration

```bash
cd backend
symfony composer install
symfony console lexik:jwt:generate-keypair      # clés RSA dans config/jwt/ (jamais versionnées)
symfony console doctrine:migrations:migrate     # tables, contraintes CHECK, procédures stockées
symfony console doctrine:fixtures:load          # jeu de démonstration (Bruxelles)
symfony serve --port=8000 --no-tls              # API sur http://127.0.0.1:8000
symfony console messenger:consume async -vv     # dans un 2e terminal : envoi des e-mails
```

> **Windows** : si `lexik:jwt:generate-keypair` échoue avec `error:80000003:system library`,
> indiquer à OpenSSL le fichier livré avec PHP, puis relancer la commande :
> `set OPENSSL_CONF=C:\wamp64\bin\php\php8.3.14\extras\ssl\openssl.cnf`

## Authentification (Phase 3)

| Route | Méthode | Rôle |
|-------|---------|------|
| `/api/inscription` | POST | Inscription : `prenom`, `nom`, `email`, `telephone`, `password`, `role` (`sportif` par défaut, `coach` ou `gestionnaire` ; jamais `admin`) |
| `/api/auth/verify-email` | GET | Lien signé reçu par e-mail (VerifyEmailBundle) : active le compte |
| `/api/auth/resend-verification` | POST | Renvoie le lien de confirmation (`email`) |
| `/api/login` | POST | `email` + `password` → `{ "token": "…", profil }` + cookie `BEARER` HttpOnly |
| `/api/me` | GET | Utilisateur connecté |
| `/api/logout` | POST | Supprime le cookie |
| `/api/auth/forgot-password` | POST | Envoie un lien de réinitialisation (`email`) |
| `/api/auth/reset-password` | POST | `token` + `password` : nouveau mot de passe (lien à usage unique, valable 1 h) |

- **JWT** (LexikJWTAuthenticationBundle), valable 1 h, accepté dans `Authorization: Bearer <jeton>`
  ou dans le cookie `BEARER` (HttpOnly, SameSite=Lax). L'API est sans état : aucune session PHP.
- **Rôles** : `ROLE_SPORTIF`, `ROLE_COACH`, `ROLE_GESTIONNAIRE`, `ROLE_ADMIN` (hérite de tous).
  **Voters** : un coach ne modifie que ses séances, un gestionnaire que ses salles et leurs
  séances, un sportif que ses réservations et ses avis (`src/Security/Voter/`).
- **Protections** : 5 échecs de connexion en 15 min → 429 ; 5 inscriptions par IP et par heure ;
  3 demandes d'e-mail (renvoi, mot de passe oublié) par adresse en 15 min ; réponses identiques
  que l'adresse existe ou non ; en-têtes `Host` inconnus refusés (`TRUSTED_HOSTS`).
- **Erreurs** : `400` JSON mal formé · `401` non authentifié · `403` compte non vérifié, bloqué ou
  droit refusé · `409` adresse déjà utilisée · `422` `{ "errors": { "champ": "message" } }` · `429` trop de tentatives.

Exemple avec `curl` :

```bash
curl -X POST http://127.0.0.1:8000/api/login -H "Content-Type: application/json" \
     -d '{"email":"sportif1@fitbook.test","password":"Sportif123!"}'
curl http://127.0.0.1:8000/api/me -H "Authorization: Bearer <jeton>"
```

## Comptes de démonstration

Créés par les fixtures. **Mots de passe de démonstration uniquement**, à ne jamais réutiliser.

| Rôle | E-mail | Mot de passe |
|------|--------|--------------|
| Administrateur | `admin@fitbook.test` | `Admin123!` |
| Gestionnaire (5) | `gestionnaire1@fitbook.test` … `gestionnaire5@fitbook.test` | `Gestion123!` |
| Coach (15) | `coach1@fitbook.test` … `coach15@fitbook.test` | `Coach123!` |
| Sportif (40) | `sportif1@fitbook.test` … `sportif38@fitbook.test` | `Sportif123!` |

États particuliers pour les tests : `sportif39@fitbook.test` n'a **pas confirmé** son adresse
(connexion refusée) et `sportif40@fitbook.test` est **bloqué**.

Contenu : 10 salles à Bruxelles (coordonnées approximatives), 10 activités, 80 séances sur les
4 prochaines semaines (dont 4 annulées et 2 complètes) et 20 séances passées, environ 500
réservations, des favoris et des avis (dont 2 masqués par la modération).

## Tests

```bash
cd backend
symfony php bin/phpunit                 # toute la suite
symfony php bin/phpunit --group mysql   # procédures stockées et contraintes CHECK (MySQL)
```

Les tests fonctionnels utilisent SQLite en mémoire et des clés JWT de test générées
automatiquement dans `backend/var/jwt-test/`. Les tests d'intégration du groupe `mysql`
reconstruisent la base `fitbook_test` à partir des migrations ; ils nécessitent, dans
`backend/.env.test.local` (non versionné) :

```dotenv
MYSQL_TEST_DATABASE_URL="mysql://fitbook_user:mot-de-passe-fort@127.0.0.1:3306/fitbook_test?serverVersion=8.0.32&charset=utf8mb4"
```

## Sauvegardes

```bash
bash database/scripts/backup.sh              # database/backups/fitbook_AAAA-MM-JJ_HHMMSS.sql
bash database/scripts/backup.sh 02_milieu    # dump jalon nommé
```

Le script utilise `fitbook_user` (jamais `root`) et lit ses identifiants dans `backend/.env.local`.
Restauration : `mysql -u fitbook_user -p fitbook < database/backups/01_debut.sql`.

## Workflow Git (GitFlow)

| Branche | Rôle |
|---------|------|
| `main` | Versions livrées uniquement (tags `vX.Y.Z`) |
| `develop` | Intégration continue des fonctionnalités |
| `feature/<nom>` | Une fonctionnalité, créée depuis `develop`, fusionnée dans `develop` (`--no-ff`) |
| `release/<version>` | Préparation d'une livraison, fusionnée dans `main` et `develop` |
| `hotfix/<nom>` | Correctif urgent depuis `main`, fusionné dans `main` et `develop` |

Messages de commit en français, au format `type(portée): description` —
types : `feat`, `fix`, `docs`, `test`, `refactor`, `chore`.

## Avancement

| Phase | Contenu | État |
|-------|---------|------|
| 1 | Analyse : GitFlow, arborescence, documentation | ✅ Terminée |
| 2 | Base de données et socle backend | ✅ Terminée |
| 3 | Authentification, rôles, voters | ✅ Terminée |
| 4 | API métier, recherche, réservations, e-mails | ⏳ |
| 5 | Socle frontend, design system | ⏳ |
| 6 | Pages publiques et espace sportif | ⏳ |
| 7 | Espaces coach, gestionnaire, admin | ⏳ |
| 8 | Bonus : carte, distance, QR code, rappels | ⏳ |
| 9 | Tests et qualité | ⏳ |
| 10 | Finalisation et release `v1.0.0` | ⏳ |
