# Analyse de l'existant et écarts avec la cible

> Version 1.0 — 5 novembre 2026 — complète le [cahier des charges](cahier-des-charges.md)

FitBook n'est pas parti d'une page blanche : une première version fonctionne déjà.
Ce document recense ce qui est réutilisé, ce qui change et **les décisions à valider**
avant la Phase 2.

---

## 1. Ce qui existe

### 1.1 Backend (Symfony 7.4, `backend/`)

| Domaine | État actuel |
|---------|-------------|
| Entités | `Utilisateur`, `Coach`, `Salle`, `Activite`, `Seance`, `Reservation`, `Avis`, `Favori` (noms français) |
| Base | MySQL **9.1** (WAMP), 21 migrations Doctrine, connexion en `root`. Tables en **MyISAM** (moteur par défaut de WAMP) : ni clés étrangères, ni transactions, ni verrous — découvert en Phase 2, corrigé en imposant InnoDB (voir [MLD § 5](mld.md)) |
| Authentification | Session PHP (cookie), `json_login`, un seul rôle utilisé (`ROLE_USER`) |
| Inscription | Validation manuelle, mot de passe haché, e-mail de vérification par jeton maison (haché, 24 h) |
| E-mails | Symfony Mailer + Messenger (file asynchrone), Mailpit en développement |
| API | Catalogue en lecture (activités, coachs, salles, séances filtrables), profil, création de réservation avec verrou pessimiste |
| Tests | 23 tests PHPUnit (inscription, vérification, connexion, profil, redirections) |

### 1.2 Frontend (Angular 21, `frontend/`)

| Domaine | État actuel |
|---------|-------------|
| Pages | Accueil, séances (recherche), activités, coachs/salles, tarifs, connexion, inscription, vérification d'e-mail, profil, réservation |
| Technique | Composants *standalone*, mode *zoneless*, formulaires réactifs, services HTTP, proxy `/api` |
| Design | Charte orange `#ff642d` / bleu nuit `#10213a`, photographies et vidéo de présentation — **conservés** |
| Tests | 39 tests Vitest |

## 2. Écarts avec l'énoncé

| Exigence de l'énoncé | Existant | Action |
|----------------------|----------|--------|
| Monorepo `backend/ frontend/ database/ postman/ docs/` | 2 dépôts séparés | ✅ Fait en Phase 1 (historiques conservés) |
| GitFlow | Commits sur `main` | ✅ `develop` + branches `feature/*` dès la Phase 1 |
| Modèle de données en anglais (User, Gym, Session…) | Modèle français, incomplet | Phase 2 — nouvelles entités (voir § 3) |
| 4 rôles + hiérarchie + Voters | 1 rôle | Phase 3 |
| JWT (LexikJWTAuthenticationBundle) | Session PHP | Phase 3 |
| VerifyEmailBundle (lien signé) | Jeton maison | Phase 3 — remplacé par le bundle |
| Mot de passe oublié | Absent | Phase 3 |
| Utilisateur MySQL `fitbook_user` | `root` | Phase 2 |
| MySQL 8 | MySQL 9.1 installé | Schéma limité aux fonctionnalités MySQL 8 ; image `mysql:8` dans `docker-compose.yml` (Phase 2) |
| CRUD complets, recherche avancée paginée | Lecture seule + 1 création | Phase 4 |
| Contrôle de capacité via procédure stockée + JSON Schema | Verrou pessimiste seul | Phase 4 |
| 3 procédures stockées | Aucune | Phase 2 (création) / Phase 4 (utilisation) |
| Fixtures Faker (Bruxelles) | Commande `app:demo:seed` (10 séances) | Phase 2 — DoctrineFixturesBundle |
| Annulation, favoris, avis, modération | Absents de l'API | Phase 4 |
| Espaces coach / gestionnaire / admin | Absents | Phase 7 |
| Routes *lazy-loaded*, signals, interceptor, guards | Routes chargées d'office, pas de guard | Phase 5 |
| Swagger, Postman/Newman, PHPStan, ESLint | Absents | Phases 4 et 9 |

## 3. Correspondance entre l'ancien et le nouveau modèle

| Ancienne table | Nouvelle table | Correspondance des colonnes | Remarques |
|----------------|----------------|-----------------------------|-----------|
| `utilisateur` | `user` | `nom`→`last_name`, `prenom`→`first_name`, `telephone`→`phone`, `mot_de_passe`→`password`, `role`→`roles` (JSON), `statut`/`email_verified_at`→`is_verified`, `date_inscription`→`created_at` | Colonnes de jeton de vérification supprimées (lien signé sans stockage) |
| `coach` | `coach_profile` + `user` | `specialite`→`specialities`, `biographie`→`bio`, `experience`→`experience_years`, `certification`→`certifications`, `tarif_horaire`→`hourly_rate`, `photo`→`user.avatar` | Chaque coach devient un **compte** `ROLE_COACH` relié à son profil |
| `salle` | `gym` | `nom`→`name`, `adresse`→`address`, `image`→`photos` (JSON) | Ajouts : ville, code postal, coordonnées, horaires, gestionnaire. `capacite` et `equipements` abandonnés (la capacité est portée par la séance) |
| `activite` | `activity` | `nom`→`name`, `description` | Ajouts : `icon`, `category`. `duree` et `niveau` passent sur la séance |
| `seance` | `session` | `titre`→`title`, `date_debut`→`start_at`, `date_fin`→`end_at`, `capacite_max`→`capacity`, `prix`→`price`, `statut`→`status` | Ajout `level`. `places_disponibles` est **calculé** (capacité − réservations confirmées) ; `date_seance`, `heure_debut`, `heure_fin` redondants supprimés |
| `reservation` | `reservation` | `utilisateur_id`→`user_id`, `seance_id`→`session_id`, `statut`→`status`, `date_reservation`→`created_at`, `date_annulation`→`cancelled_at` | **1 réservation = 1 place** : `nombre_places` et `prix_total` disparaissent (voir décision D3) |
| `avis` | `review` | `note`→`rating`, `commentaire`→`comment`, `date_avis`→`created_at`, `utilisateur_id`→`author_id`, `statut`→`is_visible` | Ajout de `is_moderated`. La cible « activité » est abandonnée : un avis porte sur une salle, un coach ou une séance |
| `favori` | `favorite` | `utilisateur_id`→`user_id`, `date_ajout`→`created_at`, cibles salle/coach/séance conservées | Ajout de `type`. Cible « activité » et colonne héritée `datetime_immutable` abandonnées |
| — | `availability`, `notification`, `reset_password_request` | — | Nouvelles tables |

## 4. Décisions à valider

| # | Décision proposée | Alternative |
|---|-------------------|-------------|
| **D1** | **Reconstruire la base** à partir des nouvelles migrations et des fixtures. L'ancienne base est d'abord sauvegardée (`mysqldump`) hors du dépôt, car elle contient des adresses réelles. | Écrire une migration de données qui convertit les comptes existants |
| **D2** | Les comptes de test créés pendant le développement ne sont pas repris ; les comptes de démo sont fournis par les fixtures et documentés dans le README. | Reprendre les comptes vérifiés |
| **D3** | **Une réservation = une place**, comme le prévoit l'énoncé (contrainte « pas deux fois la même séance »). La réservation de plusieurs places est retirée. | Garder `nombre_places` (contredit RG-04) |
| **D4** | Le coach est représenté par un **compte utilisateur + un profil coach** (relation 1-1), conformément à l'énoncé. | Coach sans compte (actuel) |
| **D5** | La page **Tarifs** existante est conservée comme page publique. Les autres pages sont réorganisées selon les routes de l'énoncé (`/recherche`, `/salles/:id`, `/mon-espace`…), avec des redirections depuis les anciennes adresses. | Supprimer la page Tarifs |
| **D6** | L'identité visuelle actuelle remplace la palette proposée par l'énoncé (orange `#FF5A1F` / vert `#22C55E`). | Adopter la palette de l'énoncé |
