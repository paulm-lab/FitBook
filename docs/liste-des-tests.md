# Liste des tests — FitBook

> Phase 9. Inventaire généré à partir du code (noms des tests), complété par la recette des
> parcours. Les critères d'acceptation viennent de [`user-stories.md`](user-stories.md).

## 1. Synthèse

| Niveau | Outil | Nombre | Résultat (6 novembre 2026) | Commande |
|--------|-------|--------|---------------------------|----------|
| Unitaires et fonctionnels API | PHPUnit 12 (SQLite en mémoire) | 116 tests, 490 assertions | ✅ tous verts | `cd backend && php bin/phpunit --exclude-group mysql` |
| Intégration MySQL (procédures, CHECK) | PHPUnit, groupe `mysql` (MySQL 9.1 / WAMP) | 8 tests, 33 assertions | ✅ tous verts | `php bin/phpunit --group mysql` |
| Frontend | Vitest (`ng test`) | 71 tests | ✅ tous verts | `cd frontend && npm test -- --watch=false` |
| API de bout en bout | Postman + Newman, sur SQLite puis sur MySQL (procédures stockées) | 60 requêtes, 106 assertions | ✅ 0 échec sur les deux bases | voir [`postman/README.md`](../postman/README.md) |
| Parcours dans le navigateur | Recette (script Playwright headless + contrôle visuel) | 47 pages, 8 parcours | ✅ sans erreur | § 3 |
| Qualité front | ESLint (angular-eslint, accessibilité des templates) + Prettier | — | ✅ 0 erreur | `npm run lint` · `npm run format:check` |
| Qualité back | PHPStan niveau 6, PHP-CS-Fixer | — | ⚠️ configurés, outils à installer | `phpstan.dist.neon`, `.php-cs-fixer.dist.php` |

Les tests fonctionnels PHPUnit utilisent une base SQLite en mémoire recréée à chaque test ; sur
cette base, les règles des procédures stockées sont appliquées par le même service PHP
(`ReservationService`), avec les mêmes codes de réponse. Le groupe `mysql` vérifie les procédures
elles-mêmes sur la base `fitbookdata_test`.

## 2. Couverture des exigences

| Exigence | Tests automatisés |
|----------|-------------------|
| EF-01 Recherche, filtres, tri, pagination, distance | `SearchTest` · Postman « Catalogue et recherche » |
| EF-02 Fiches | `CatalogueAndReservationTest`, `FavoritesAndReviewsTest` (fiche sur proxy Doctrine) |
| EF-03/04/05 Inscription par rôle, vérification de l'adresse (lien signé), JWT, mot de passe oublié | `AuthenticationTest`, `LocalizationTest` (messages et e-mails en anglais) · specs `inscription`, `connexion`, `verification-email`, `core.spec.ts` |
| EF-06, RG-03, RG-04 Réservation, capacité, doublon | `CatalogueAndReservationTest`, `ReservationFlowTest`, `StoredProceduresTest` (mysql) · Postman |
| EF-07, RG-05 Annulation jusqu'à 2 h avant | `ReservationFlowTest`, `StoredProceduresTest` · Postman |
| EF-08 Favoris | `FavoritesAndReviewsTest` · Postman |
| EF-09, RG-06, RG-07 Avis | `FavoritesAndReviewsTest` · Postman (403 sans participation) |
| EF-10 Profil, avatar | `ProfileControllerTest`, `AvatarAndRemindersTest` |
| EF-11, RG-09, RG-10, RG-12 Séances | `SessionManagementTest` · Postman « Coach » |
| EF-12 Salles, statistiques (`sp_stats_salle`) | `ProfessionalSpacesTest`, `StoredProceduresTest` · Postman « Gestionnaire » |
| EF-13 Administration | `ProfessionalSpacesTest`, `ProfileControllerTest` · Postman « Administrateur » |
| EF-14, RG-08 Modération | `FavoritesAndReviewsTest` |
| EF-15 E-mails et rappel 24 h | `assertQueuedEmailCount` (réservation, annulation, séance annulée), `AvatarAndRemindersTest` |
| RG-11 Voters | `VotersTest`, réponses 403 de `SessionManagementTest`, `ProfessionalSpacesTest`, Postman |
| Sécurité : XSS, envoi de fichier, JSON Schema, limitation | `FavoritesAndReviewsTest`, `AvatarAndRemindersTest`, `ReservationFlowTest`, `AuthenticationTest` |

## 3. Recette des parcours (navigateur)

Sur les données des fixtures. Les 47 pages (publiques et quatre espaces) ont été ouvertes avec
chaque rôle : aucune erreur JavaScript, aucune réponse 5xx.

| # | Parcours | Étapes | Attendu | Résultat |
|---|----------|--------|---------|----------|
| P1 | Réserver puis annuler (sportif) | Recherche « pilates » → fiche → Réserver → Mes réservations → Annuler → confirmer | Toast, place décomptée puis libérée, fenêtre de confirmation | ✅ |
| P2 | Créer une séance (coach) | Espace coach → Nouvelle séance → Enregistrer | Toast « Séance créée », retour à la liste | ✅ |
| P3 | Calendrier (coach) | Calendrier → semaine suivante | Un chargement par période, séances et disponibilités affichées | ✅ |
| P4 | Modérer un avis (admin) | Modération → Valider | L'avis quitte la liste « non modérés », toast | ✅ |
| P5 | Session expirée | Ouvrir `/mon-espace/favoris` sans session | `/connexion?returnUrl=…`, puis retour à la page | ✅ (`core.spec.ts`) |
| P6 | Mode sombre | Bouton ☾ ou préférence du système | Pages lisibles, contrastes conservés | ✅ |
| P7 | Mobile (390 px) | Accueil, recherche, espaces | Pas de défilement horizontal, menu repliable | ✅ |
| P8 | Clavier | Tab dans l'en-tête, les filtres, les formulaires ; Échap dans une fenêtre | Lien d'évitement, focus visible, Échap ferme la confirmation | ✅ |

## 4. Inventaire des tests automatisés

### 4.1 PHPUnit (backend)

#### `tests/Controller/Api/AuthenticationTest.php` (26)

- registration persists an unverified sportif with hashed password
- new account cannot log in before confirming its address
- coach registration creates an empty coach profile
- manager role can be chosen but never administrator
- registration reports an email accepted synchronously as sent
- smtp failure still creates the account and allows a resend
- registration normalizes email and rejects duplicates
- invalid fields get422 with one french message per field
- registration rejects malformed json
- signed link from the email activates the account
- tampered link or link for another address is rejected
- resend verification queues a new link only for unverified accounts
- resend verification is rate limited
- login returns a jwt and sets an http only cookie
- jwt in authorization header authenticates requests
- cookie session works until logout
- forged token is rejected
- rejected cookie is cleared by the401 response
- unverified or blocked account cannot log in
- blocking an account invalidates its existing token
- account status is not revealed with a wrong password
- login is throttled after five failures
- forgotten password can be reset once with the emailed token
- forgotten password answers the same for unknown addresses
- reset rejects invalid tokens and short passwords
- untrusted host header is rejected

#### `tests/Controller/Api/LocalizationTest.php` (9)

- validation errors follow the request language
- api exception messages are translated
- json schema messages with parameters are translated
- login failure is translated and login remembers the language
- rejected jwt is translated and its cookie still cleared
- registration in english stores the locale and sends an english verification email
- unverified login and resend answers are translated
- welcome email after verification uses the account language
- notifications and emails use the recipient language

#### `tests/Controller/Api/AvatarAndRemindersTest.php` (2)

- avatar accepts only real images
- reminders are sent once for sessions within24 hours

#### `tests/Controller/Api/CatalogueAndReservationTest.php` (7)

- list shows only active upcoming sessions with computed places
- past session detail is hidden
- reservation requires authentication
- sportif reserves one place and cannot reserve twice
- full session is refused
- cancelled reservation is reactivated instead of duplicated
- unavailable sessions and several places are refused

#### `tests/Controller/Api/FavoritesAndReviewsTest.php` (4)

- favorites can be added listed and removed by their owner only
- review requires a finished reservation and is unique
- session detail works with lazy loaded coach proxy
- hidden review disappears from rating and only admin moderates

#### `tests/Controller/Api/ProfessionalSpacesTest.php` (5)

- manager creates and edits only his gyms
- sportif cannot create gym or open professional spaces
- coach updates profile and availabilities
- admin manages accounts but not his own admin role
- admin manages activities

#### `tests/Controller/Api/ProfileControllerTest.php` (8)

- anonymous visitor cannot read own profile
- authenticated user reads only safe profile fields
- anonymous visitor cannot update profile
- profile update changes only the authenticated user and ignores submitted ids
- invalid profile update is rejected before persistence
- user directory requires authentication
- sportif cannot list all users
- administrator lists users

#### `tests/Controller/Api/ReservationFlowTest.php` (5)

- body is validated by json schema
- reservation has qr token and notification
- upcoming and history tabs
- cancellation rules
- athlete cannot read someone else reservation

#### `tests/Controller/Api/SearchTest.php` (5)

- sessions are filtered and paginated
- minimum rating uses visible reviews only
- gyms around a point are sorted by distance
- coaches are searched by speciality and rate
- invalid criteria are refused with field errors

#### `tests/Controller/Api/SessionManagementTest.php` (10)

- coach creates a session attached to his profile
- invalid session is refused field by field
- sportif cannot create and anonymous is unauthenticated
- coach cannot edit another coach session
- manager must use one of his gyms
- capacity cannot drop below confirmed reservations
- cancelling a session cancels reservations and warns athletes
- organizer sees attendees
- session without reservation is deleted
- coach calendar lists only his sessions in the window

#### `tests/Controller/FrontendRedirectTest.php` (4)

- frontend page opened on the backend redirects to angular
- query string and root are kept
- api routes are never redirected
- only get is redirected

#### `tests/Integration/StoredProceduresTest.php` (8)

- reservation succeeds then refuses a duplicate
- full session is refused
- unknown cancelled or past sessions are refused
- cancelled reservation is reactivated
- cancellation rules
- cancellation is refused less than two hours before the session
- gym statistics
- check constraints reject invalid rows

#### `tests/Unit/Entity/DomainRulesTest.php` (6)

- user without role is treated as sportif
- coach profile keeps both sides of the relation
- remaining places ignore cancelled reservations
- past or cancelled session is not bookable
- cancellation deadline is two hours
- favorite has exactly one target and matching type

#### `tests/Unit/Security/VotersTest.php` (6)

- coach edits only the sessions he teaches
- manager edits only the sessions and gyms he manages
- role is required in addition to ownership
- only the athlete cancels his reservation but the coach can see it
- only the author edits a review and only the admin moderates
- voters abstain on unsupported attributes

### 4.2 Vitest (frontend)

#### `src/app/core/core.spec.ts` (9)

- ignore les paramètres vides
- extrait le premier message de validation, puis le message général
- restaure la session depuis /api/me, sans erreur si le visiteur est anonyme
- garde le jeton en mémoire et le joint aux appels suivants
- sur un 401, oublie la session et renvoie vers /connexion avec la page demandée
- l'administrateur hérite de tous les rôles
- renvoie un visiteur vers /connexion avec returnUrl
- renvoie un sportif vers son espace s'il ouvre l'espace coach
- attend la fin de /api/me avant de décider (rechargement de page)

#### `src/app/pages/accueil/accueil.spec.ts` (1)

- should create

#### `src/app/pages/connexion/connexion.spec.ts` (15)

- connecte l’utilisateur et le redirige vers la page demandée
- refuse une redirection vers un autre site et ouvre l’espace du rôle
- affiche un message pour des identifiants incorrects
- pré-remplit l’adresse transmise par la page d’inscription
- affiche le message du serveur pour un compte bloqué
- propose de renvoyer le lien pour une adresse non confirmée (code de l’API)
- reconnaît une adresse non confirmée en anglais, même sans code
- renvoie le lien à l’adresse saisie et affiche la réponse de l’API
- signale l’échec du renvoi et réactive le bouton
- affiche le bouton de renvoi en anglais
- affiche le message de limitation après trop de tentatives
- réactive le formulaire si le service lève une exception synchrone
- n’appelle pas l’API pour un formulaire invalide
- réaffiche « Se connecter » dans la page après une erreur réseau
- s’affiche en anglais quand la langue choisie est l’anglais

#### `src/app/pages/inscription/inscription.spec.ts` (22)

- confirme le succès et indique que le lien de vérification a été mis en file
- annonce un e-mail envoyé seulement quand le serveur SMTP l’a accepté
- indique un e-mail en file d’attente sans prétendre qu’il est parti
- signale l’échec d’envoi et propose un nouveau lien
- ne déduit jamais « envoyé » d’une réponse sans statut explicite
- pré-remplit l’adresse inscrite dans le lien de connexion
- affiche la confirmation et le bouton de renvoi en anglais
- envoie le rôle choisi à l’API
- envoie le rôle sportif par défaut et ne propose jamais administrateur
- réactive le formulaire quand le service lève une exception synchrone
- évite les appels pour un formulaire invalide
- remet le bouton en état normal dès la réponse HTTP
- réactive le formulaire et signale un e-mail déjà utilisé
- réactive le formulaire et affiche un message si la requête expire
- affiche « Création... » pendant l’envoi puis « Créer mon compte » après une erreur réseau
- affiche la confirmation dans la page après la réponse du serveur
- affiche les erreurs de champ renvoyées par le serveur
- réactive le formulaire et affiche un message en cas d’erreur serveur
- termine l’état d’envoi du lien après le succès du backend
- termine l’état d’envoi et affiche une erreur si le renvoi échoue
- termine l’état d’envoi et affiche une erreur si le renvoi expire
- ne renvoie pas plusieurs demandes simultanément

#### `src/app/pages/verification-email/verification-email.spec.ts` (7)

- affiche la confirmation et le bouton de connexion en cas de succès
- affiche le formulaire de renvoi pour un lien invalide ou expiré
- renvoie le lien à l’adresse fournie et termine l’état d’envoi
- n’appelle pas l’API pour une adresse invalide
- affiche le message du serveur si le renvoi échoue
- réactive le bouton si le service de renvoi lève une exception synchrone
- s’affiche en anglais quand la langue choisie est l’anglais

#### `src/app/shared/ui.spec.ts` (4)

- affiche au plus 5 numéros autour de la page courante
- n'affiche rien pour une seule page et n'émet pas hors limites
- arrondit la note et annonce une étiquette accessible
- résout la promesse avec la réponse et referme la fenêtre

### 4.3 Postman (`postman/fitbook.postman_collection.json`)


#### 1. Authentification (9)

- `POST /api/login` — Connexion sportif
- `POST /api/login` — Connexion — mauvais mot de passe (401)
- `POST /api/login` — Connexion — adresse non confirmée (403)
- `POST /api/login` — Connexion — compte bloqué (403)
- `GET /api/me` — Utilisateur courant
- `GET /api/me` — Utilisateur courant sans jeton (401)
- `POST /api/inscription` — Inscription — rôle admin refusé (422)
- `POST /api/inscription` — Inscription — champs invalides (422)
- `POST /api/auth/forgot-password` — Mot de passe oublié — réponse neutre

#### 2. Catalogue et recherche (9)

- `GET /api/activites` — Activités
- `GET /api/recherche?type=seances&activite=yoga&parPage=5` — Recherche de séances (yoga)
- `GET /api/recherche?tri=prix&parPage=20` — Recherche triée par prix
- `GET /api/recherche?type=salles&lat=50.8466&lng=4.3528&rayon=5&tri=distance` — Salles autour de Bruxelles (5 km)
- `GET /api/recherche?type=coachs` — Recherche de coachs
- `GET /api/recherche?type=planetes&du=2026-13-45&noteMin=9` — Recherche — critères invalides (422)
- `GET /api/salles/{{salleId}}` — Fiche salle
- `GET /api/coaches/{{coachId}}` — Fiche coach
- `GET /api/seances/999999` — Fiche séance introuvable (404)

#### 3. Réservation, favoris, avis (sportif) (17)

- `POST /api/login` — Connexion d'un second sportif
- `GET /api/reservations?periode=a_venir&parPage=50` — Mes réservations à venir
- `GET /api/recherche?type=seances&du={{demain}}&parPage=50` — Choix d'une séance réservable (demain ou plus tard)
- `POST /api/reservations` — Réserver — corps refusé par le JSON Schema (422)
- `POST /api/reservations` — Réserver sans être connecté (401)
- `POST /api/reservations` — Réserver la séance (201)
- `POST /api/reservations` — Réserver deux fois la même séance (409)
- `GET /api/reservations/{{reservationId}}` — Détail de ma réservation
- `GET /api/reservations/{{reservationId}}` — Réservation lue par un autre sportif (403)
- `POST /api/reservations/{{reservationId}}/annuler` — Annuler ma réservation
- `POST /api/reservations/{{reservationId}}/annuler` — Annuler une seconde fois (409)
- `GET /api/reservations?periode=historique` — Historique
- `POST /api/favoris` — Ajouter la séance aux favoris
- `GET /api/favoris` — Mes favoris
- `DELETE /api/favoris/{{favoriId}}` — Retirer le favori
- `POST /api/avis` — Avis sans participation (403)
- `GET /api/me/notifications` — Notifications

#### 4. Coach (11)

- `POST /api/login` — Connexion coach
- `POST /api/seances` — Créer une séance — fin avant début (422)
- `POST /api/seances` — Créer une séance (201)
- `PUT /api/seances/{{seanceCoachId}}` — Modifier ma séance
- `PUT /api/seances/{{seanceCoachId}}` — Un sportif modifie la séance d'un coach (403)
- `GET /api/seances/{{seanceCoachId}}/inscrits` — Inscrits de ma séance
- `GET /api/coach/seances?periode=a_venir` — Mes séances à venir
- `GET /api/coach/stats` — Mes statistiques
- `POST /api/seances/{{seanceCoachId}}/annuler` — Annuler ma séance (RG-12)
- `POST /api/reservations` — Réserver une séance annulée (409)
- `DELETE /api/seances/{{seanceCoachId}}` — Supprimer ma séance

#### 5. Gestionnaire (7)

- `POST /api/login` — Connexion gestionnaire
- `GET /api/gestionnaire/salles` — Mes salles (sp_stats_salle)
- `GET /api/salles/{{maSalleId}}/stats` — Statistiques d'une salle
- `GET /api/salles/{{maSalleId}}/stats` — Statistiques de salle par un sportif (403)
- `POST /api/salles` — Créer une salle — champs invalides (422)
- `GET /api/gestionnaire/reservations?periode=a_venir` — Réservations de mes salles
- `GET /api/gestionnaire/stats` — Tableau de bord

#### 6. Administrateur (7)

- `POST /api/login` — Connexion administrateur
- `GET /api/admin/stats` — Tableau de bord global
- `GET /api/admin/utilisateurs?q=sportif&role=sportif` — Recherche d'utilisateurs
- `GET /api/admin/avis?filtre=non_moderes` — Avis non modérés
- `GET /api/admin/stats` — Espace admin ouvert par un sportif (403)
- `POST /api/activites` — Activité déjà existante (409)
- `POST /api/logout` — Déconnexion
