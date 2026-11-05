# Sécurité — FitBook

> Phase 9 — protections en place, référencées sur l'**OWASP Top 10 (2021)**, et points à surveiller.
> Chaque mesure renvoie au code ou à la configuration qui la met en œuvre.

## 1. Vue d'ensemble

| Couche | Mesures principales |
|--------|---------------------|
| Navigateur (Angular) | Échappement automatique des templates, aucun `innerHTML`, jeton dans un cookie HttpOnly + mémoire (jamais `localStorage`), guards par rôle |
| API (Symfony) | JWT sans état, rôles + Voters, Validator + JSON Schema, limitation des tentatives, erreurs JSON sans détail technique, CORS restreint, `TRUSTED_HOSTS` |
| Base (MySQL) | Utilisateur dédié `fitbook_user` limité à `fitbook` et `fitbook_test`, requêtes paramétrées, contraintes `CHECK`, clés étrangères, procédures stockées transactionnelles |
| Exploitation | Secrets hors dépôt (`.env.local`, clés JWT), sauvegardes `mysqldump` par `fitbook_user`, journaux sans données sensibles |

## 2. OWASP Top 10

### A01 — Contrôle d'accès défaillant

- **Pare-feu sans état** (`config/packages/security.yaml`) : chaque requête est authentifiée par
  son JWT. `access_control` réserve `/api/me`, `/api/reservations`, `/api/favoris` aux comptes
  connectés, `/api/coach/` à `ROLE_COACH`, `/api/gestionnaire/` à `ROLE_GESTIONNAIRE`,
  `/api/admin` à `ROLE_ADMIN` ; toute écriture du catalogue exige une connexion.
- **Propriété des ressources (RG-11)** par des Voters (`src/Security/Voter/`) : un coach ne
  modifie que ses séances, un gestionnaire que ses salles et leurs séances, un sportif que ses
  réservations et ses avis ; la modération est réservée à l'administrateur.
- **Pas d'élévation à l'inscription** : le rôle `admin` est refusé (422) ; l'administrateur ne
  peut ni retirer son propre rôle ni se bloquer (409).
- **Pas de divulgation** : le favori d'un autre utilisateur répond 404, une séance passée ou
  annulée n'est visible que par ses organisateurs.
- Les guards Angular (`core/auth/guards.ts`) ne font que masquer : la décision réelle est
  toujours prise par l'API (testé par PHPUnit et Postman : 403 attendus).

### A02 — Défaillances cryptographiques

- Mots de passe hachés par l'algorithme `auto` de Symfony (bcrypt ou argon2id), avec
  ré-hachage automatique à la connexion (`UserRepository::upgradePassword`).
- JWT signés en **RS256** (clés RSA, la clé privée chiffrée par `JWT_PASSPHRASE`), durée de vie 1 h.
- Cookie `BEARER` **HttpOnly**, `SameSite=Lax`, `Secure` en production (HTTPS obligatoire).
- Liens de vérification d'e-mail signés (VerifyEmailBundle, 24 h) ; jetons de réinitialisation
  du mot de passe à usage unique, stockés hachés, valables 1 h (ResetPasswordBundle).
- Jeton de QR code aléatoire (`random_bytes(16)`), non devinable.

### A03 — Injection

- **SQL** : Doctrine QueryBuilder et DQL paramétrés partout, appels de procédures stockées avec
  paramètres liés (`ReservationRepository::callReserveProcedure`). Les jokers `%` et `_` saisis
  dans la recherche sont neutralisés (`SessionRepository::like`).
- **XSS** : Angular échappe toute interpolation ; les commentaires d'avis sont affichés en texte
  (`{{ }}`), jamais via `[innerHTML]` (vérifié dans les tests : un `<script>` est rendu tel quel).
  Les bulles de la carte Leaflet sont construites avec `textContent`.
- **E-mails** : templates Twig avec échappement automatique.

### A04 — Conception non sécurisée

- Règles métier vérifiées **en base** (contraintes `CHECK`, unicité, procédures stockées avec
  verrou `FOR UPDATE`) et pas seulement dans l'application : aucune sur-réservation possible
  même avec deux demandes simultanées (RG-03).
- Corps de `POST /api/reservations` validé par JSON Schema (`additionalProperties: false`).
- Limitation des abus : 5 échecs de connexion / 15 min par adresse et IP (429), 5 inscriptions
  par IP et par heure, 3 demandes d'e-mail par adresse / 15 min.
- Réponses identiques que l'adresse existe ou non (mot de passe oublié, renvoi du lien) : pas
  d'énumération des comptes.

### A05 — Mauvaise configuration de sécurité

- `TRUSTED_HOSTS` : en-têtes `Host` inconnus refusés (protège les liens envoyés par e-mail).
- CORS (NelmioCorsBundle) limité aux origines du front local ; à restreindre au domaine de
  production (voir `docs/deploiement.md`).
- Sous `/api`, toutes les erreurs sont converties en JSON sans trace (`ApiExceptionListener`) ;
  en production `APP_DEBUG=0`.
- La documentation Swagger (`/api/doc`) ne contient aucun secret ; elle peut être désactivée en
  production en retirant `config/routes/nelmio_api_doc.yaml`.

### A06 — Composants vulnérables

- `npm audit --omit=dev` : **0 vulnérabilité** (5 novembre 2026).
- `composer audit` signale des avis sur Symfony 7.4.x antérieurs à **7.4.23** (http-foundation,
  routing, security-http, serializer…). **Action requise** :
  `composer update "symfony/*" --with-all-dependencies` puis relancer les tests. La mise à jour
  n'a pas pu être faite depuis le poste de développement : l'entrée `8.8.8.8 codeload.github.com`
  du fichier `hosts` de Windows empêche Composer de télécharger les archives.
  Aucun des composants concernés n'est exposé de façon exploitable dans FitBook (pas de
  `private_ranges`, pas d'X509, pas de dénormalisation de fichiers), mais la mise à jour reste
  nécessaire.

### A07 — Identification et authentification

- Connexion refusée (403) tant que l'adresse n'est pas confirmée ou si le compte est bloqué
  (`UserChecker`).
- Mot de passe de 8 caractères minimum, vérifié côté client et côté serveur.
- Session expirée : l'intercepteur Angular oublie la session et renvoie vers `/connexion`, puis
  vers la page demandée.

### A08 — Intégrité des données et du logiciel

- Dépendances verrouillées (`composer.lock`, `package-lock.json`) ; installation reproductible.
- Avatars : type vérifié **sur le contenu** (JPEG, PNG, WebP), 2 Mo et 4000 px au maximum, nom
  de fichier aléatoire généré par le serveur (le nom envoyé n'est jamais utilisé), dossier
  `public/uploads/` hors dépôt.

### A09 — Journalisation et surveillance

- Monolog : erreurs d'envoi d'e-mail et de base journalisées avec la **classe** d'exception
  seulement, jamais le DSN, le lien signé ou le mot de passe.
- Échecs de connexion comptés par le limiteur de Symfony.

### A10 — Falsification de requête côté serveur (SSRF)

- Le serveur n'effectue aucune requête vers une URL fournie par l'utilisateur : les URL de
  photos sont seulement enregistrées (validées par `Assert\Url`) et chargées par le navigateur.

## 3. CSRF

L'API n'utilise pas de session PHP. Le cookie `BEARER` est `SameSite=Lax` : il n'est pas envoyé
par les requêtes `POST`/`PUT`/`DELETE` d'un autre site, et l'API n'accepte que des corps JSON.

## 4. Base de données

- L'application se connecte avec `fitbook_user` (droits limités aux bases `fitbook` et
  `fitbook_test`, `database/scripts/create_user.sql`), jamais `root`.
- Tables InnoDB, clés étrangères, contraintes `CHECK` (dates, capacité, prix, note 1-5,
  exactement une cible pour un favori ou un avis).

## 5. Secrets

| Secret | Emplacement | Versionné ? |
|--------|-------------|-------------|
| Mot de passe MySQL, `JWT_PASSPHRASE`, `APP_SECRET` de production | `backend/.env.local` | Non |
| Clés JWT | `backend/config/jwt/*.pem` | Non (`.gitignore`) |
| Mots de passe Docker | `.env` à la racine (copié de `.env.example`) | Non |
| Mots de passe des comptes de démonstration | README | Oui, **démonstration uniquement** |

## 6. Vérifications

Les protections sont couvertes par les tests (voir [`liste-des-tests.md`](liste-des-tests.md)) :
authentification, rôles, Voters, limitation, JSON Schema, validation, XSS des avis, fichiers
envoyés, procédures stockées et collection Postman (403, 401, 409, 422).
