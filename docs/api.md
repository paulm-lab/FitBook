# API REST FitBook

> Référence des routes de l'API (Phase 4). La documentation interactive complète est
> générée par NelmioApiDocBundle : **http://127.0.0.1:8000/api/doc** (JSON : `/api/doc.json`).

## Conventions

- JSON en entrée et en sortie, clés en français (`nom`, `prenom`, `dateDebut`…).
- Dates en **ISO 8601 avec décalage** (`2026-11-20T18:30:00+01:00`), stockées en UTC.
  `dateSeance`, `heureDebut`, `heureFin` sont déjà convertis à l'heure de Bruxelles.
- Authentification : `Authorization: Bearer <jeton>` ou cookie HttpOnly `BEARER` (posé par `/api/login`).
- Listes paginées : `?page=1&parPage=20` (50 au maximum) →
  `{ "items": [...], "page": 1, "parPage": 20, "total": 134, "pages": 7 }`.
- Erreurs : `{ "message": "…" }`, et pour une validation `{ "message": "…", "errors": { "champ": "message" } }`.

| Code | Signification |
|------|---------------|
| 400 | JSON mal formé, paramètre illisible |
| 401 | Non authentifié (jeton absent, invalide ou expiré) |
| 403 | Rôle insuffisant ou ressource d'un autre (Voters) |
| 404 | Ressource introuvable |
| 409 | Conflit métier : complet, déjà réservé, trop tard pour annuler, doublon… |
| 422 | Champs invalides (Symfony Validator ou JSON Schema) |
| 429 | Trop de tentatives (connexion, inscription, e-mails) |

## Public

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/recherche` | Recherche paginée (voir ci-dessous) |
| GET | `/api/seances` · `/api/seances/{id}` | Séances à venir (`?activite=&lieu=&date=`) · fiche avec avis et note |
| GET | `/api/salles` · `/api/salles/{id}` | Salles · fiche : prochaines séances, disponibilités, note, avis |
| GET | `/api/coaches` · `/api/coaches/{id}` | Coachs publiés · fiche : séances, disponibilités, note, avis |
| GET | `/api/activites` · `/api/activites/{id}` | Activités |
| GET | `/api/avis?type=salle&cibleId=3` | Avis visibles d'une cible (paginé) + note moyenne |
| GET | `/api/disponibilites?coachId=` / `?salleId=` | Créneaux d'un coach ou d'une salle |

### Recherche — `GET /api/recherche`

| Paramètre | Valeurs | Remarque |
|-----------|---------|----------|
| `type` | `seances` (défaut), `salles`, `coachs` | |
| `q` | texte | titre, activité, nom de salle ou de coach, spécialités |
| `ville` | texte ou code postal | |
| `activite` / `activiteId` | nom partiel / identifiant | |
| `du`, `au` | `AAAA-MM-JJ` | séances |
| `prixMax` | nombre | prix de la séance, tarif horaire du coach |
| `niveau` | `debutant`, `intermediaire`, `avance` | séances |
| `noteMin` | 1 à 5 | note moyenne des avis visibles |
| `lat`, `lng`, `rayon` | degrés, km | salles et séances ; ajoute `distanceKm` |
| `tri` | `date`, `prix`, `note`, `distance`, `nom` | `distance` exige `lat` et `lng` |

Chaque résultat porte `noteMoyenne`, `nbAvis` et `distanceKm`. La note d'une séance est celle de
son coach (à défaut, celle de sa salle).

## Authentification

`POST /api/inscription`, `GET /api/auth/verify-email`, `POST /api/auth/resend-verification`,
`POST /api/login`, `GET /api/me`, `POST /api/logout`, `POST /api/auth/forgot-password`,
`POST /api/auth/reset-password` — voir le [README](../README.md#authentification).

`/api/me` renvoie `{ id, nom, prenom, email, telephone, avatar, roles, role, coachId }`, où
`role` vaut `sportif`, `coach`, `gestionnaire` ou `admin`.

## Compte connecté

| Méthode | Route | Description |
|---------|-------|-------------|
| GET, PUT | `/api/me/profile` | Profil (`nom`, `prenom`, `telephone`) |
| POST, DELETE | `/api/me/avatar` | Avatar : `multipart/form-data`, champ `avatar` (JPEG, PNG, WebP, 2 Mo) |
| GET | `/api/me/notifications` | `{ nonLues, items }` |
| POST | `/api/me/notifications/lues` | Tout marquer comme lu |
| GET | `/api/me/avis` | Mes avis |
| POST | `/api/reservations` | `{ "seanceId": 12 }` — JSON Schema `reservation.schema.json` → 201 |
| GET | `/api/reservations?periode=a_venir\|historique` | Mes réservations (paginé) |
| GET | `/api/reservations/{id}` | Détail (avec `qrToken` si confirmée) |
| POST | `/api/reservations/{id}/annuler` | Annulation, au plus tard 2 h avant (409 sinon) |
| GET, POST | `/api/favoris` | `{ "type": "salle\|coach\|seance", "cibleId": 3 }` |
| DELETE | `/api/favoris/{id}` | |
| POST | `/api/avis` | `{ type, cibleId, note, commentaire }` — réservation terminée requise (403), un seul par cible (409) |
| PUT, DELETE | `/api/avis/{id}` | Auteur uniquement |

## Coach (`ROLE_COACH`)

| Méthode | Route | Description |
|---------|-------|-------------|
| GET, PUT | `/api/coach/profil` | `{ biographie, specialites[], experienceAnnees, tarifHoraire, ville, certifications }` |
| GET | `/api/coach/seances` | `?periode=a_venir\|passees` (paginé) ou `?debut=&fin=` (calendrier, liste) |
| GET | `/api/coach/stats` | Séances et inscrits à venir, remplissage, note, `evolution` hebdomadaire |
| GET | `/api/coach/avis` | Avis reçus |
| POST, PUT, DELETE | `/api/disponibilites[/{id}]` | `{ recurrent, jourSemaine (1-7) \| date, heureDebut, heureFin }` |

## Séances (coach, gestionnaire, admin)

| Méthode | Route | Description |
|---------|-------|-------------|
| POST | `/api/seances` | `{ titre, description, activiteId, salleId, coachId, debut, fin, capacite, prix, niveau }` |
| PUT | `/api/seances/{id}` | Mêmes champs ; capacité ≥ inscrits confirmés |
| DELETE | `/api/seances/{id}` | Seulement sans inscrit (409 sinon : annuler) |
| POST | `/api/seances/{id}/annuler` | Annule les réservations et prévient les sportifs par e-mail |
| GET | `/api/seances/{id}/inscrits` | Liste des inscrits |

Un coach crée ses propres séances ; un gestionnaire, celles de ses salles (`salleId` obligatoire).

## Gestionnaire (`ROLE_GESTIONNAIRE`)

| Méthode | Route | Description |
|---------|-------|-------------|
| POST, PUT, DELETE | `/api/salles[/{id}]` | `{ nom, description, adresse, ville, codePostal, latitude, longitude, telephone, email, horaires, photos[] }` |
| GET | `/api/salles/{id}/stats` | `sp_stats_salle` + `evolution` sur 12 semaines |
| GET | `/api/salles/{id}/reservations` | Réservations de la salle (paginé) |
| GET | `/api/gestionnaire/salles` | Mes salles avec leurs statistiques |
| GET | `/api/gestionnaire/stats` | Totaux de mes salles, par salle et évolution |
| GET | `/api/gestionnaire/seances` | `?salleId=`, `?periode=` ou `?debut=&fin=` |
| GET | `/api/gestionnaire/reservations` | `?periode=a_venir`, `?statut=` (paginé) |
| GET | `/api/gestionnaire/avis` | Avis sur mes salles et leurs séances |

## Administrateur (`ROLE_ADMIN`)

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/admin/stats` | Utilisateurs par rôle, salles, séances, réservations par statut, avis en attente |
| GET | `/api/admin/utilisateurs` | `?q=&role=` (paginé) |
| PATCH | `/api/admin/utilisateurs/{id}` | `{ role, estBloque, estVerifie }` — jamais sur son propre rôle admin |
| DELETE | `/api/admin/utilisateurs/{id}` | Refusé s'il gère des salles ou anime des séances |
| GET | `/api/admin/reservations` | `?statut=&periode=` (paginé) ; annulation via `/api/reservations/{id}/annuler` |
| GET | `/api/admin/seances` | Toutes les séances |
| GET | `/api/admin/avis?filtre=non_moderes\|masques` | Modération (paginé) |
| PATCH | `/api/admin/avis/{id}` | `{ "action": "valider\|masquer\|afficher" }` |
| POST, PUT, DELETE | `/api/activites[/{id}]` | `{ nom, categorie, description, image }` |

L'administrateur a aussi tous les droits des routes salles, séances et disponibilités.

## Commandes planifiées

```bash
php bin/console app:reservations:terminer   # séances finies → réservations « terminee »
php bin/console app:send-reminders          # rappel unique 24 h avant la séance
```

À lancer toutes les heures (Planificateur de tâches Windows ou cron).
