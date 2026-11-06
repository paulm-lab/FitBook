# Conformité à l'énoncé et à la grille d'évaluation

> Contrôle du 6 novembre 2026, version `develop` après la release 1.1.1.
> Sources : [énoncé UAA MI08 et grille d'évaluation](consignes/enonce-et-grille-UAA-MI08.pdf)
> (É = énoncé, page ; G = grille, critère). Aucune exigence n'a été ajoutée à ces documents.

**Statuts** : ✅ conforme · 🟡 partiellement conforme · ❌ non conforme · ❔ non vérifiable
(la preuve n'est pas dans le dépôt ou le test n'a pas pu être fait).

## 1. Points qui peuvent bloquer l'évaluation

| # | Exigence (source) | Statut | Preuve | Correction / action |
|---|-------------------|--------|--------|---------------------|
| 1 | Inscription, connexion, **confirmation e-mail**, permissions par rôle (É p.2 obj. 4, É p.3 backend) | ✅ | Supprimée par erreur le 6/11, **rétablie** : `InscriptionController`, `UserChecker`, page `/verification-email`. Test réel : inscription → connexion refusée (403 `email_non_verifie`, FR et EN) → lien reçu dans Mailpit → `status=success` → connexion 200. PHPUnit `AuthenticationTest` | Fait |
| 2 | GitHub **créé avant le développement** (É p.4) | ❌ | Dépôt local depuis le 31/10, mais dépôt GitHub `paulm-lab/FitBook` créé et poussé le 6/11 | Ne peut pas être corrigé après coup. À expliquer à l'oral (dépôt local Git dès le 31/10) |
| 3 | **3 commits minimum par jour** de travail (É p.4) | 🟡 | `git log` : 3 commits le 31/10, 56 le 5/11, plusieurs dizaines le 6/11 ; aucun commit les autres jours | Respecter la règle désormais. Ne pas réécrire l'historique (dates falsifiées) |
| 4 | **1 pull request tous les 3 jours** maximum (É p.4) | ❌ | Aucune pull request sur GitHub (fusions locales `--no-ff`) | Passer désormais par des pull requests GitHub pour fusionner les branches `feature/*` |
| 5 | **Trello** créé avant le développement, permettant de retracer l'avancement (É p.4, p.5) | ❔ | Aucun lien Trello dans le dépôt | Ajouter le lien du tableau dans le README et le montrer à la soutenance |
| 6 | Validation préalable des **user stories, maquettes Figma et MCD** par les formateurs (É p.2) | ❔ | `user-stories.md`, `mcd.md` présents ; aucune trace de validation ni de maquette | Joindre la preuve de validation (e-mail, capture) |
| 7 | **Maquette Figma/Canva** : 3 écrans principaux minimum + déclinaisons responsive (É p.2, p.4) | ❔ | Aucun lien ni export de maquette dans le dépôt | Ajouter le lien Figma ou les exports dans `docs/` |
| 8 | **Déploiement** : application distante accessible et connectée à une base distante (G 6) | ❌ | Seulement la procédure [`deploiement.md`](deploiement.md) ; aucune application en ligne | Déployer sur un hébergement (Symfony + MySQL + build Angular) avant la soutenance |
| 9 | **Présentation finale** (PowerPoint, Canva, Figma…) : technologies, fonctionnalités, organisation, tests, résultats (É p.5, G 8) | ❔ | Hors dépôt | À préparer (voir § 5) |
| 10 | **Analyse critique de l'ergonomie** reprenant 80 % des points « de la procédure » (G 1) | ❔ | La procédure citée n'est pas fournie ; pas de document d'analyse ergonomique | Demander la procédure aux formateurs, puis rédiger l'analyse |

## 2. Analyse et documentation

| Exigence (source) | Statut | Preuve | Correction / action |
|-------------------|--------|--------|---------------------|
| MCD avec JMerise ou équivalent, **intégré au cahier des charges** (É p.3) | 🟡 | [`mcd.md`](mcd.md) (Mermaid) ; **intégré** au cahier des charges § 12.1 le 6/11. Le README indique « à reproduire dans JMerise » | Exporter le MCD depuis JMerise (image) et l'ajouter au cahier |
| MLD cohérent avec le MCD, intégré au cahier (É p.3) | 🟡 | [`mld.md`](mld.md), cahier § 12.2 | Idem (JMerise) |
| Use-cases avec StarUML ou équivalent (É p.3) | 🟡 | [`use-cases.md`](use-cases.md) (Mermaid + scénarios) | Exporter depuis StarUML |
| Diagramme de classes avec StarUML ou équivalent (É p.3) | 🟡 | [`diagramme-classes.md`](diagramme-classes.md) | Exporter depuis StarUML |
| Le cahier des charges contient les éléments d'analyse et de modélisation (É p.5, G 8) | ✅ | [`cahier-des-charges.md`](cahier-des-charges.md) § 1-12 | — |
| Analyse des données et analyse fonctionnelle (G 1, G 2) | ✅ | MCD, MLD, user stories (32), cas d'utilisation | — |
| Code aéré, indenté, commenté et documenté (G 3, 5, 7) | ✅ | ESLint et Prettier sans erreur ; commentaires en français ; README et 12 documents | — |
| Liste de tests (60 % des tests correspondant à la situation) (G 5) | ✅ | [`liste-des-tests.md`](liste-des-tests.md) | — |
| 60 % des attaques connues répertoriées (G 5) | ✅ | [`securite.md`](securite.md) : OWASP Top 10 | — |
| Courbes de stress (G 1, G 2) | ✅ | **Ajoutées le 6/11** : [`tests-de-charge/`](tests-de-charge/README.md), 1 → 50 utilisateurs, 0 % d'erreur, courbes SVG | — |

## 3. Technique

| Exigence (source) | Statut | Preuve | Correction / action |
|-------------------|--------|--------|---------------------|
| Frontend HTML/CSS + **Angular 21** (É p.3) | ✅ | `frontend/package.json` (`@angular/core` ^21.2) ; composants standalone, signals, `@if`/`@for` | — |
| Backend **PHP, Symfony, MySQL** (É p.3) | ✅ | PHP 8.3, Symfony 7.4, MySQL 9.1 (WAMP) ; base `fitbookdata` | — |
| Utilisateur MySQL autre que root, avec mot de passe (É p.3) ; droits configurés (G 2) | ✅ | `fitbook_user`, droits limités à `fitbookdata` et `fitbookdata_test` ([`create_user.sql`](../database/scripts/create_user.sql)) ; l'application s'y connecte | Le compte **root de WAMP n'a pas de mot de passe** : en définir un sur le poste |
| **3 procédures stockées** (É p.3, ajout manuscrit) | ✅ | `sp_reserver_seance`, `sp_annuler_reservation`, `sp_stats_salle` ; 8 tests d'intégration MySQL verts | — |
| **1 JSON Schema** (É p.3, ajout manuscrit) | ✅ | `reservation.schema.json`, validé par `JsonSchemaValidator` dans `ReservationController` | — |
| Validation des formulaires côté backend (É p.3, p.5) | ✅ | Test réel : inscription invalide → 422 avec un message par champ (FR/EN) | — |
| Contrôle de la capacité des réservations (É p.3, p.5) | ✅ | Procédure avec verrou `FOR UPDATE` ; verrous sur la baisse de capacité et l'annulation de séance | — |
| Notifications e-mail liées aux réservations (É p.3) | ✅ | Confirmation, annulation, rappel 24 h (`FitBookMailer`) ; e-mails reçus dans Mailpit | — |
| **3 sauvegardes** de la base : début, milieu, fin (É p.3, ajout manuscrit ; G 2, 3, 5) | 🟡 | `database/backups/01_debut.sql`, `02_milieu.sql`, `03_fin.sql` (avec procédures) ; script `backup.sh` qui affiche « Sauvegarde créée » | `03_fin` date du 5/11 et de l'ancienne base `fitbook` : la **refaire à la fin du projet** sur `fitbookdata` |
| Moteur de base de données configuré (G 2) | ✅ | InnoDB imposé (`doctrine.yaml`), voir [`mld.md`](mld.md) | — |
| Requêtes de sélection, ajout, modification, suppression (G 2) | ✅ | CRUD complets (API REST, 70 routes) | — |
| Table de données remplie à 80 % (G 5) | ✅ | Fixtures : 61 comptes, 10 salles, 101 séances, 508 réservations, 47 avis | — |
| Tests unitaires et d'intégration (É p.5) | ✅ | PHPUnit **124 tests** (dont 8 sur MySQL) ; Vitest **71 tests** ; tous verts le 6/11 | — |
| Tests de l'API avec Postman/Newman (É p.3, p.5) | 🟡 | Collection + rapport Newman du 5/11 : 60 requêtes, 106 assertions, 0 échec | **Relancer** Newman sur une base rechargée (fixtures) après les changements du 6/11 |
| Contrôle des permissions selon le rôle (É p.5) | ✅ | Tests réels : visiteur → 401, sportif → 403 sur `/api/admin`, gestionnaire → 403 sur la création d'activité ; Voters testés | — |
| Protection des données d'authentification (É p.5) | ✅ | Mots de passe bcrypt (`$2y$13$`), JWT en cookie HttpOnly, limitation des tentatives | — |
| GitFlow obligatoire (É p.4) | ✅ | Branches `feature/*`, `release/*`, `hotfix/*`, fusions `--no-ff`, tags v1.0.0 → v1.1.1 | — |

## 4. Fonctionnalités par rôle (É p.4)

| Rôle / exigence | Statut | Preuve |
|-----------------|--------|--------|
| Sportif : profil (création, modification), recherche + filtres, consultation, réservation, annulation, historique, favoris, avis et notes | ✅ | Routes `/api/me/profile`, `/api/recherche`, `/api/reservations` (+ `/annuler`, `?periode=historique`), `/api/favoris`, `/api/avis` ; testées |
| Coach : profil professionnel, création / modification / suppression des séances, horaires et disponibilités, calendrier, réservations et avis | ✅ | `/api/coach/*`, `/api/seances`, `/api/disponibilites`, calendrier FullCalendar |
| Salle / gestionnaire : informations de la salle, cours, horaires, capacités, calendrier, disponibilités, réservations, avis | ✅ | `/api/salles`, `/api/gestionnaire/*` |
| Gestionnaire : gestion des **activités** | 🟡 | Le gestionnaire choisit l'activité de chaque cours ; le **catalogue** des activités est géré par l'administrateur (création refusée au gestionnaire, 403). À justifier à l'oral, ou ouvrir la création d'activités aux gestionnaires |
| Administrateur : utilisateurs, salles, activités, réservations, modération des avis et notes | ✅ | `/api/admin/*`, `/api/activites` ; espace `/admin` |
| Administrateur : gestion des **coachs** | 🟡 | Via les comptes (rôle, blocage, suppression) ; l'administrateur ne modifie pas le profil professionnel d'un coach |
| Obligatoires : favoris, avis et notes, calendrier, barre de recherche | ✅ | Présents et testés |
| Bonus : carte, recherche par distance, notifications avant séance, statistiques, QR code | ✅ | Leaflet, tri par distance, rappel 24 h, `sp_stats_salle` + Chart.js, angularx-qrcode |
| Design moderne, responsive, adapté à tous les écrans (É p.2 obj. 1) | 🟡 | Règles responsive (`@media` 900 / 700 / 600 / 520 px), balise viewport ; **non contrôlé visuellement** sur téléphone pendant ce contrôle | Vérifier les écrans principaux sur mobile avant la soutenance |

## 5. À préparer pour la soutenance

1. **Présentation** (PowerPoint, Canva…) : technologies, fonctionnalités par rôle, organisation
   (GitFlow, releases, Trello), tests (PHPUnit, Vitest, Newman, courbes de stress) et résultats.
2. **Liens à montrer** : dépôt GitHub, tableau Trello, maquettes Figma, application déployée.
3. **Réponses prêtes** sur les écarts : dépôt GitHub créé tard, absence de pull requests, rythme
   des commits, gestion des activités et des coachs, choix de MySQL 9.1 (WAMP) compatible MySQL 8.
4. **Avant la démonstration** : recharger les fixtures (`php bin/console doctrine:fixtures:load`),
   relancer Newman, faire la sauvegarde `03_fin` sur `fitbookdata`, lancer `demarrer.ps1`
   (Mailpit et worker d'e-mails nécessaires à la confirmation d'adresse).
