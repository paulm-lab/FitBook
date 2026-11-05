# FitBook — frontend Angular

Application Angular 21 (composants standalone, signals, détection de changements *zoneless*,
routes chargées à la demande). Installation et commandes : voir le [README principal](../README.md#frontend).

## Organisation

```text
src/app/
├── app.*                 # Coquille : en-tête selon le rôle, notifications, mode sombre, pied de page
├── app.routes.ts         # Toutes les routes (loadComponent), guards, anciennes adresses redirigées
├── core/
│   ├── models.ts         # Types des réponses de l'API (docs/api.md)
│   ├── http.ts           # API_URL, paramètres de requête, messages d'erreur (422 par champ)
│   ├── auth/             # AuthService (session en signals), intercepteur JWT / 401, guards par rôle
│   └── services/         # CatalogueService, CompteService, GestionService, toasts, thème
├── shared/               # Composants réutilisables : toasts, confirmation, pagination, étoiles,
│                         # favoris, cartes, avis, FullCalendar, Chart.js, Leaflet
├── pages/                # Pages publiques et pages de compte
└── espaces/
    ├── espace-layout.ts  # Menu latéral commun (liens définis dans app.routes.ts)
    ├── sportif/          # /mon-espace
    ├── coach/            # /coach
    ├── gestionnaire/     # /gestionnaire
    ├── admin/            # /admin
    └── commun/           # Écrans partagés coach / gestionnaire / admin (séances, salles, calendrier…)
```

## Choix techniques

- **Session** : le jeton JWT est posé par l'API dans un cookie HttpOnly et gardé en mémoire
  (jamais dans `localStorage`) ; `/api/me` restaure la session au démarrage
  (`provideAppInitializer`). L'intercepteur ajoute l'en-tête `Authorization` et renvoie vers
  `/connexion?returnUrl=…` sur un 401.
- **Droits** : les guards masquent les espaces selon le rôle ; l'API reste seule juge (rôles et Voters).
- **Styles** : jetons de la charte dans `src/styles.css` (variables CSS), redéfinis pour le mode
  sombre (`data-theme="dark"`). Les composants n'utilisent que ces variables.
- **Sécurité** : aucun `innerHTML` ; les commentaires d'avis sont affichés en texte.
- **Accessibilité** : lien d'évitement, focus visible, libellés et erreurs liés aux champs,
  régions `aria-live` pour les toasts et les compteurs, règles d'accessibilité d'angular-eslint.
