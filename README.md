# FitBook

Plateforme web de réservation de **salles de sport**, de **cours** et de **coachs sportifs**.
Projet de formation **UAA MI08 — Produire un projet Web**.

> 🚧 **Phase 1 — Analyse** terminée, en attente de validation par les formateurs.
> Le guide d'installation complet sera rédigé en Phase 10.

---

## Documentation d'analyse

| Document | Contenu |
|----------|---------|
| [Cahier des charges](docs/cahier-des-charges.md) | Contexte, objectifs, périmètre, règles de gestion, charte graphique, planning |
| [Analyse de l'existant](docs/analyse-existant.md) | Version actuelle, écarts avec l'énoncé, **décisions à valider** |
| [User stories](docs/user-stories.md) | 32 stories par rôle, avec critères d'acceptation |
| [MCD](docs/mcd.md) | Modèle conceptuel (à reproduire dans JMerise) |
| [MLD](docs/mld.md) | Modèle logique MySQL, contraintes, procédures stockées |
| [Cas d'utilisation](docs/use-cases.md) | Acteurs, diagramme et scénarios détaillés (à reproduire dans StarUML) |
| [Diagramme de classes](docs/diagramme-classes.md) | Domaine et architecture backend (à reproduire dans StarUML) |

## Stack

| Couche | Technologies |
|--------|--------------|
| Frontend | Angular 21 (standalone, signals, lazy loading) |
| Backend | PHP 8.3, Symfony 7.4 (API REST JSON), Doctrine ORM, JWT, Symfony Mailer |
| Base de données | MySQL 8+, utilisateur dédié `fitbook_user`, procédures stockées |
| E-mails (dev) | Mailpit |
| Tests | PHPUnit, tests Angular, Postman + Newman |

## Arborescence

```text
fitbook-mi08/
├── backend/            # API Symfony
├── frontend/           # Application Angular
├── database/
│   ├── procedures/     # Procédures stockées (.sql)
│   ├── backups/        # Dumps 01_debut.sql, 02_milieu.sql, 03_fin.sql
│   └── scripts/        # create_user.sql, backup.sh
├── postman/            # Collection, environnement, rapport Newman
├── docs/               # Documentation d'analyse et technique
└── README.md
```

`backend/` et `frontend/` proviennent des deux anciens dépôts du projet : leur historique Git
complet est conservé (import par `git subtree`).

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
| 1 | Analyse : GitFlow, arborescence, documentation | ✅ Terminée — **en validation** |
| 2 | Base de données et socle backend | ⏳ |
| 3 | Authentification, rôles, voters | ⏳ |
| 4 | API métier, recherche, réservations, e-mails | ⏳ |
| 5 | Socle frontend, design system | ⏳ |
| 6 | Pages publiques et espace sportif | ⏳ |
| 7 | Espaces coach, gestionnaire, admin | ⏳ |
| 8 | Bonus : carte, distance, QR code, rappels | ⏳ |
| 9 | Tests et qualité | ⏳ |
| 10 | Finalisation et release `v1.0.0` | ⏳ |
