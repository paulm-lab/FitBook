# Déploiement — FitBook

> Phase 10. Mise en production d'une version (`main`, tag `vX.Y.Z`) sur un serveur Linux
> (Apache ou Nginx + PHP-FPM 8.3, MySQL 8). L'installation de développement est décrite dans
> le [README](../README.md).

## 1. Architecture cible

```text
Navigateur ──HTTPS──> serveur web
                       ├── /            → frontend/dist/fitbook-front/browser (fichiers statiques Angular)
                       ├── /api, /uploads → backend/public/index.php (Symfony, PHP-FPM)
                       └── MySQL 8 (utilisateur fitbook_user)
Tâches : commandes horaires (rappels, réservations terminées) ; e-mails envoyés directement en SMTP
```

Servir le front et l'API **sur le même domaine** garde le cookie HttpOnly du JWT sur la même
origine (pas de CORS à ouvrir, `SameSite=Lax` suffit).

## 2. Base de données

```bash
# En root, une seule fois : bases fitbookdata / fitbookdata_test et utilisateur dédié.
mysql -u root -p -e "SET @fitbook_password = 'mot-de-passe-fort'; SOURCE database/scripts/create_user.sql;"
```

Les migrations créent les tables InnoDB, les contraintes `CHECK` et les trois procédures stockées.
Sauvegarde avant chaque mise à jour : `bash database/scripts/backup.sh avant_vX.Y.Z`.

## 3. Backend

```bash
git clone … && git checkout vX.Y.Z
cd backend
composer install --no-dev --optimize-autoloader
```

`backend/.env.local` (jamais versionné, droits 600) :

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<64 caractères aléatoires>
DATABASE_URL="mysql://fitbook_user:<mot-de-passe>@127.0.0.1:3306/fitbookdata?serverVersion=8.0.32&charset=utf8mb4"
MAILER_DSN=smtp://<utilisateur>:<mot-de-passe>@<serveur-smtp>:587
MAILER_FROM=noreply@fitbook.exemple
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
JWT_PASSPHRASE=<longue phrase aléatoire>
FRONTEND_URL=https://fitbook.exemple
TRUSTED_HOSTS='^fitbook\.exemple$'
CORS_ALLOW_ORIGIN='^https://fitbook\.exemple$'
```

```bash
php bin/console lexik:jwt:generate-keypair          # clés propres au serveur
php bin/console doctrine:migrations:migrate -n
php bin/console messenger:setup-transports
php bin/console cache:clear && php bin/console cache:warmup
mkdir -p public/uploads/avatars                     # écriture par l'utilisateur de PHP-FPM uniquement
```

Ne **pas** charger les fixtures en production (comptes et mots de passe de démonstration).
Le premier administrateur se crée en base puis se gère depuis `/admin`.

Pour exposer moins de surface : retirer `config/routes/nelmio_api_doc.yaml` (Swagger) et la
route de test d'e-mail (`MailTestController`, déjà limitée à l'environnement de développement).

## 4. Frontend

```bash
cd frontend
npm ci
npm run build          # → dist/fitbook-front/browser
```

Copier `dist/fitbook-front/browser` dans la racine web. Toutes les routes inconnues du serveur
doivent renvoyer `index.html` (routage Angular).

## 5. Serveur web (exemple Nginx)

```nginx
server {
    listen 443 ssl http2;
    server_name fitbook.exemple;
    # ssl_certificate … (Let's Encrypt)

    root /var/www/fitbook/frontend;
    add_header X-Content-Type-Options nosniff;
    add_header Referrer-Policy strict-origin-when-cross-origin;
    add_header X-Frame-Options DENY;

    location /api     { try_files $uri /index.php$is_args$args; root /var/www/fitbook/backend/public; }
    location /uploads { root /var/www/fitbook/backend/public; }
    location ~ ^/index\.php(/|$) {
        root /var/www/fitbook/backend/public;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        internal;
    }
    location / { try_files $uri $uri/ /index.html; }
}
server { listen 80; server_name fitbook.exemple; return 301 https://$host$request_uri; }
```

HTTPS est obligatoire : le cookie `BEARER` est `Secure` en production.

## 6. Tâches de fond

| Tâche | Commande | Fréquence |
|-------|----------|-----------|
| Envoi des e-mails (Messenger) | `php bin/console messenger:consume async --time-limit=3600` | En continu (systemd, redémarrage automatique) |
| Rappels 24 h avant la séance | `php bin/console app:send-reminders` | Toutes les heures |
| Réservations des séances finies → terminées | `php bin/console app:reservations:terminer` | Toutes les heures |
| Sauvegarde | `bash database/scripts/backup.sh` | Chaque nuit, conservation 30 jours |

Exemple de crontab :

```cron
5 * * * *  cd /var/www/fitbook/backend && php bin/console app:reservations:terminer -q
10 * * * * cd /var/www/fitbook/backend && php bin/console app:send-reminders -q
30 2 * * * cd /var/www/fitbook && bash database/scripts/backup.sh
```

Sous Windows (WAMP), les mêmes commandes se planifient avec le Planificateur de tâches.

## 7. Mise à jour d'une version

1. `bash database/scripts/backup.sh avant_vX.Y.Z`
2. `git fetch && git checkout vX.Y.Z`
3. Backend : `composer install --no-dev -o`, `doctrine:migrations:migrate -n`, `cache:clear`
4. Frontend : `npm ci && npm run build`, copie de `dist/`
5. Vérifier l'envoi d'un e-mail (inscription de test)
6. Vérifier : `curl -I https://fitbook.exemple/api/activites` (200) et une connexion de test

Retour arrière : `git checkout` de la version précédente, restauration du dump si une migration
a modifié les données.

## 8. Avant la première mise en production

- `composer audit` et `npm audit --omit=dev` sans vulnérabilité.
- Mots de passe et clés propres au serveur, aucune donnée de démonstration.
