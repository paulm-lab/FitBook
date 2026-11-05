# Tests Postman / Newman

| Fichier | Rôle |
|---------|------|
| `fitbook.postman_collection.json` | 60 requêtes, 106 assertions, en 6 dossiers : authentification, catalogue et recherche, réservation (favoris, avis), coach, gestionnaire, administrateur |
| `fitbook.postman_environment.json` | Environnement local : `baseUrl` = `http://127.0.0.1:8000`, compte sportif de démonstration |
| `generer_collection.py` | Source de la collection : modifier ce script puis `python postman/generer_collection.py` |
| `rapport-newman.txt`, `rapport-newman.xml` | Dernier rapport (sortie console et JUnit) |

La collection est un **parcours** : les requêtes s'enchaînent (jetons, identifiants de séance,
de réservation… conservés dans les variables de collection). Elle couvre les cas nominaux et
les erreurs attendues : 401 (sans jeton), 403 (compte non vérifié ou bloqué, ressource d'un
autre, rôle insuffisant), 404, 409 (doublon, séance annulée, annulation répétée), 422
(Symfony Validator et JSON Schema de `POST /api/reservations`).

## Exécution

Prérequis : l'API démarrée sur une base **fraîchement chargée** avec les fixtures
(`symfony console doctrine:fixtures:load`).

```bash
npx newman run postman/fitbook.postman_collection.json \
    -e postman/fitbook.postman_environment.json \
    --reporters cli,junit --reporter-junit-export postman/rapport-newman.xml
```

Autre adresse de l'API : `--env-var baseUrl=http://127.0.0.1:8001`.

Dans Postman : *Import* des deux fichiers JSON, choisir l'environnement « FitBook — local »,
puis *Run collection*.

## Dernier rapport

Exécuté le 5 novembre 2026 sur la base de démonstration (fixtures) : **60 requêtes,
106 assertions, 0 échec**, temps de réponse moyen ≈ 430 ms (serveur PHP intégré, un seul
processus). Cette exécution a utilisé une copie SQLite de la base ; sur MySQL, les
réservations passent par les procédures stockées `sp_reserver_seance` et
`sp_annuler_reservation` (mêmes codes de réponse, couverts aussi par les tests PHPUnit du
groupe `mysql`).
