# FitBook

Plateforme web de réservation de **salles de sport**, de **cours** et de **coachs sportifs**.
Projet de formation **UAA MI08 — Produire un projet Web**.

> **Version 1.0.0** — les dix phases du plan sont livrées. Les actions restant à faire sur le
> poste de développement sont listées en fin de document ([À faire](#à-faire-sur-le-poste-de-développement)).

## Fonctionnalités

- **Visiteur** : recherche de séances, salles et coachs (texte, ville, activité, dates, prix,
  niveau, note, distance), vue carte, fiches détaillées avec avis, inscription avec choix du rôle.
- **Sportif** : réservation (contrôle de capacité en base), annulation jusqu'à 2 h avant, QR code,
  calendrier personnel, favoris, avis, notifications, profil et avatar, rappel 24 h avant.
- **Coach** : profil professionnel, séances (création, modification, annulation avec e-mail aux
  inscrits), liste des inscrits (export CSV), disponibilités, calendrier, statistiques, avis reçus.
- **Gestionnaire** : salles (horaires, photos, coordonnées), cours, disponibilités, réservations,
  tableau de bord (`sp_stats_salle`, graphique Chart.js).
- **Administrateur** : vue d'ensemble, comptes (rôle, blocage), modération des avis, réservations,
  séances, salles, activités.
- Mode sombre, responsive mobile, accessibilité clavier, toasts, états de chargement et vides.

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
| [API](docs/api.md) | Routes REST, codes d'erreur, recherche, commandes planifiées (Swagger : `/api/doc`) |
| [Sécurité](docs/securite.md) | Protections face à l'OWASP Top 10, secrets, points à surveiller |
| [Liste des tests](docs/liste-des-tests.md) | Synthèse, couverture des exigences, recette des parcours, inventaire |
| [Tests de charge](docs/tests-de-charge/README.md) | Courbes de stress de l'API (débit, temps de réponse, erreurs) |
| [Énoncé et grille](docs/consignes/enonce-et-grille-UAA-MI08.pdf) | Consignes des formateurs et grille d'évaluation UAA MI08 |
| [Conformité](docs/conformite-enonce.md) | Contrôle de chaque exigence de l'énoncé et de la grille, écarts et actions |
| [Déploiement](docs/deploiement.md) | Mise en production, tâches planifiées, mise à jour |
| [Postman](postman/README.md) | Collection, environnement et rapport Newman |

## Stack

| Couche | Technologies |
|--------|--------------|
| Frontend | Angular 21 (standalone, signals, zoneless, lazy loading), FullCalendar 6, Chart.js 4, Leaflet 1.9, angularx-qrcode |
| Backend | PHP 8.3, Symfony 7.4 (API REST JSON), Doctrine ORM, LexikJWT, Mailer + Messenger, NelmioApiDoc, opis/json-schema |
| Base de données | MySQL 8+ (InnoDB), utilisateur dédié `fitbook_user`, 3 procédures stockées |
| E-mails (dev) | Mailpit |
| Tests et qualité | PHPUnit (93 tests), Vitest (50 tests), Postman + Newman (60 requêtes), ESLint + Prettier, PHPStan 6 et PHP-CS-Fixer (configurés) |

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

## Démarrage rapide (Windows)

Une fois l'installation ci-dessous faite et WampServer lancé, **double-cliquer sur `demarrer.cmd`**
(ou `.\demarrer.ps1` dans PowerShell) démarre tout :

| Service | Adresse |
|---------|---------|
| Site (Angular, fenêtre dédiée) | http://localhost:4200 |
| API Symfony + worker d'e-mails (`backend/.symfony.local.yaml`) | http://127.0.0.1:8000 — Swagger : `/api/doc` |
| Mailpit (e-mails de test) | http://127.0.0.1:8025 |

Le script vérifie MySQL, ne relance pas ce qui tourne déjà et remplace un ancien serveur HTTPS
sur le port 8000 (le proxy du front exige HTTP). Options : `-SansFront`, `-SansNavigateur`.
Arrêt : `arreter.cmd` (`-GarderMailpit` pour garder les e-mails consultables).
Mailpit est attendu dans `%LOCALAPPDATA%\Programs\mailpit\mailpit.exe`
([téléchargement](https://github.com/axllent/mailpit/releases), archive `mailpit-windows-amd64.zip`).

## Installation

### Backend

Prérequis : PHP 8.3, Composer, [Symfony CLI](https://symfony.com/download), MySQL 8+
(WAMP ou Docker) et [Mailpit](https://mailpit.axllent.org/) pour les e-mails.

#### 1. Base de données et utilisateur `fitbook_user`

**Avec WAMP** — en tant que root, en choisissant un mot de passe fort :

```bash
mysql -u root -p -e "SET @fitbook_password = 'mot-de-passe-fort'; SOURCE database/scripts/create_user.sql;"
```

**Avec Docker** — copier `.env.example` en `.env`, y choisir les mots de passe, puis :

```bash
docker compose up -d      # MySQL sur 127.0.0.1:3307, phpMyAdmin sur :8080, Mailpit sur :8025
```

Le script crée les bases `fitbookdata` et `fitbookdata_test` et l'utilisateur `fitbook_user`, dont les droits
sont limités à ces deux bases. L'application ne se connecte jamais en `root`.

#### 2. Configuration locale (`backend/.env.local`, jamais versionné)

```dotenv
DATABASE_URL="mysql://fitbook_user:mot-de-passe-fort@127.0.0.1:3306/fitbookdata?serverVersion=8.0.32&charset=utf8mb4"
MAILER_DSN=smtp://127.0.0.1:1025
# Passphrase de la clé privée JWT : une longue chaîne aléatoire.
JWT_PASSPHRASE=une-longue-phrase-secrete-aleatoire
```

(Avec Docker, utiliser le port `3307`.)

#### 3. Dépendances, clés JWT, schéma et données de démonstration

```bash
cd backend
symfony composer install
symfony console lexik:jwt:generate-keypair      # clés RSA dans config/jwt/ (jamais versionnées)
symfony console doctrine:migrations:migrate     # tables, contraintes CHECK, procédures stockées
symfony console doctrine:fixtures:load          # jeu de démonstration (Bruxelles)
symfony serve                                   # API sur http://127.0.0.1:8000 (HTTP, voir .symfony.local.yaml)
# Le worker d'e-mails (messenger:consume async) démarre avec le serveur.
```

> **Windows** : si `lexik:jwt:generate-keypair` échoue avec `error:80000003:system library`,
> indiquer à OpenSSL le fichier livré avec PHP, puis relancer la commande :
> `set OPENSSL_CONF=C:\wamp64\bin\php\php8.3.14\extras\ssl\openssl.cnf`

### Frontend

Prérequis : Node.js 22+ et npm.

```bash
cd frontend
npm install
npm start            # http://localhost:4200 — /api et /uploads relayés vers http://127.0.0.1:8000 (proxy.conf.json)
```

| Commande | Rôle |
|----------|------|
| `npm run build` | Build de production dans `dist/fitbook-front/browser` |
| `npm test -- --watch=false` | Tests Vitest |
| `npm run lint` | ESLint (TypeScript, Angular, accessibilité des templates) |
| `npm run format` / `npm run format:check` | Prettier |

Le front et l'API passent par la **même origine** (proxy) : le cookie HttpOnly du jeton JWT est
envoyé automatiquement. Documentation interactive de l'API : http://127.0.0.1:8000/api/doc.

### Tâches planifiées

```bash
php bin/console app:reservations:terminer   # séances finies → réservations « terminee » (ouvre les avis)
php bin/console app:send-reminders          # rappel unique 24 h avant chaque séance
```

À lancer toutes les heures (Planificateur de tâches Windows ou cron), voir [`docs/deploiement.md`](docs/deploiement.md).

## Authentification

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

- **Vérification de l'adresse** (obligatoire) : l'inscription crée un compte non vérifié et envoie,
  dans la langue de l'utilisateur, un lien signé (VerifyEmailBundle, valable 24 h, lié à l'adresse).
  La réponse `201` indique l'état réel de l'envoi (`verificationEmailStatus` : `sent`, `queued` ou
  `failed`). Le lien active le compte puis redirige vers `/verification-email?status=success|error`
  du front. Tant que l'adresse n'est pas confirmée, `/api/login` répond `403`
  `{ "message": "Confirmez votre adresse e-mail avant de vous connecter.", "code": "email_non_verifie" }`.
  En développement, les e-mails partent vers **Mailpit** (http://127.0.0.1:8025) par le worker
  Messenger : `demarrer.ps1` (ou `demarrer.cmd`) doit être lancé pour qu'ils soient envoyés.

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
symfony php bin/phpunit --exclude-group mysql   # 85 tests (SQLite en mémoire)
symfony php bin/phpunit --group mysql   # procédures stockées et contraintes CHECK (MySQL)
```

Les tests fonctionnels utilisent SQLite en mémoire et des clés JWT de test générées
automatiquement dans `backend/var/jwt-test/`. Les tests d'intégration du groupe `mysql`
reconstruisent la base `fitbookdata_test` à partir des migrations ; ils nécessitent, dans
`backend/.env.test.local` (non versionné) :

```dotenv
MYSQL_TEST_DATABASE_URL="mysql://fitbook_user:mot-de-passe-fort@127.0.0.1:3306/fitbookdata_test?serverVersion=8.0.32&charset=utf8mb4"
```

Frontend : `cd frontend && npm test -- --watch=false` (50 tests).
API de bout en bout : collection Postman / Newman, voir [`postman/README.md`](postman/README.md).
Détail et recette des parcours : [`docs/liste-des-tests.md`](docs/liste-des-tests.md).

## Sauvegardes

```bash
bash database/scripts/backup.sh              # database/backups/fitbook_AAAA-MM-JJ_HHMMSS.sql
bash database/scripts/backup.sh 02_milieu    # dump jalon nommé
```

Le script utilise `fitbook_user` (jamais `root`) et lit ses identifiants dans `backend/.env.local`.
Restauration : `mysql -u fitbook_user -p fitbook < database/backups/03_fin.sql`.

Dumps jalons fournis : `01_debut.sql` (base initiale, Phase 2), `02_milieu.sql` (fixtures complètes,
fin de la Phase 9), `03_fin.sql` (version 1.0, après le parcours Newman sur MySQL).

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
| 4 | API métier, recherche, réservations, e-mails | ✅ Terminée |
| 5 | Socle frontend, design system | ✅ Terminée |
| 6 | Pages publiques et espace sportif | ✅ Terminée |
| 7 | Espaces coach, gestionnaire, admin | ✅ Terminée |
| 8 | Bonus : carte, distance, QR code, rappels, notifications | ✅ Terminée |
| 9 | Tests et qualité | ✅ Terminée (voir « À faire ») |
| 10 | Finalisation et release `v1.0.0` | ✅ Terminée |

## À faire sur le poste de développement

Ces points n'ont pas pu être réalisés depuis l'environnement de développement de la session
de finalisation ; ils ne demandent aucun changement de code.

1. **Installer les outils d'analyse PHP** (configurations fournies : `phpstan.dist.neon`,
   `.php-cs-fixer.dist.php`) :
   Le fichier `C:\Windows\System32\drivers\etc\hosts` contient `8.8.8.8 codeload.github.com`,
   qui empêche Composer de télécharger les archives : retirer cette ligne d'abord, ou ajouter
   `--prefer-source` (clonage Git depuis github.com, plus lent).
   `composer require --dev phpstan/phpstan phpstan/phpstan-symfony phpstan/phpstan-doctrine phpstan/extension-installer friendsofphp/php-cs-fixer`,
   puis `vendor/bin/phpstan analyse` et `vendor/bin/php-cs-fixer fix --dry-run --diff`.
2. **Pousser** les branches et le tag : `git push origin main develop --tags`.
