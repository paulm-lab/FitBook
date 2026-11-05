# Tests Postman / Newman

| Fichier | Rôle |
|---------|------|
| `fitbook.postman_collection.json` | 60 requêtes, 106 assertions, en 6 dossiers : authentification, catalogue et recherche, réservation (favoris, avis), coach, gestionnaire, administrateur |
| `fitbook.postman_environment.json` | Environnement local : `baseUrl` = `http://127.0.0.1:8000`, compte sportif de démonstration |
| `generer_collection.py` | Source de la collection : modifier ce script puis `python postman/generer_collection.py` |
| `rapport-newman.txt`, `rapport-newman.xml` | Rapport sur la base de démonstration SQLite (console et JUnit) |
| `rapport-newman-mysql.txt`, `rapport-newman-mysql.xml` | Rapport sur MySQL : réservations par les procédures stockées |

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

## Derniers rapports

| Base | Date | Requêtes | Assertions | Échecs | Temps moyen |
|------|------|----------|------------|--------|-------------|
| SQLite (copie des fixtures) | 5 novembre 2026 | 60 | 106 | 0 | ≈ 430 ms |
| MySQL 9.1 (WAMP), procédures `sp_reserver_seance` / `sp_annuler_reservation` | 5 novembre 2026 | 60 | 106 | 0 | ≈ 520 ms |

Serveur PHP intégré (un seul processus), d'où des temps supérieurs à ceux d'un serveur réel.

**Limitation des inscriptions** : la collection envoie deux inscriptions (refusées en 422). Le
limiteur accepte 5 inscriptions par IP et par heure : après plusieurs passages rapprochés, ces
requêtes reçoivent 429. Vider le compteur avant de relancer :
`php bin/console cache:pool:clear cache.rate_limiter`.
