# Deploiement Morex sur Hostinger

## Pre-requis

- Hostinger Premium ou Business
- PHP 8.2+
- MySQL 8.0
- Node.js 18+ (pour build)
- Composer

## 1. Preparation locale

### Build des assets

```bash
npm install
npm run build
```

### Fichiers a deployer

Tous les fichiers SAUF :
- `node_modules/`
- `.env`
- `storage/app/` (sauf `public/.gitkeep`)
- `storage/logs/`
- `tests/`

## 2. Configuration Hostinger

### Base de donnees MySQL

1. Dans hPanel > Bases de donnees > Creer une nouvelle base
2. Noter :
   - Nom de la base : `u123456789_morex`
   - Utilisateur : `u123456789_morex`
   - Mot de passe : (celui que vous avez defini)
   - Host : `localhost`

### Configuration du domaine

1. Dans hPanel > Domaines > Sous-domaine
2. Creer : `morex.mebodorichard.com`
3. Pointer vers : `public_html/morex/public`

### SSL

1. Dans hPanel > SSL > Installer SSL
2. Activer pour `morex.mebodorichard.com`

## 3. Upload des fichiers

### Via File Manager ou FTP

1. Creer le dossier `public_html/morex/`
2. Uploader tous les fichiers du projet
3. S'assurer que le DocumentRoot pointe vers `public_html/morex/public`

## 4. Configuration serveur

### Fichier .env

Creer `.env` dans `public_html/morex/` :

```env
APP_NAME=Morex
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=Africa/Libreville
APP_URL=https://morex.mebodorichard.com

APP_LOCALE=fr
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=fr_FR

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_morex
DB_USERNAME=u123456789_morex
DB_PASSWORD=VOTRE_MOT_DE_PASSE

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=morex.mebodorichard.com

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database

CACHE_STORE=database
CACHE_PREFIX=morex_

SANCTUM_STATEFUL_DOMAINS=morex.mebodorichard.com

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=noreply@mebodorichard.com
MAIL_PASSWORD=VOTRE_MOT_DE_PASSE_EMAIL
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="noreply@mebodorichard.com"
MAIL_FROM_NAME="Morex"

VITE_APP_NAME="Morex"
```

### Permissions

```bash
chmod -R 755 storage
chmod -R 755 bootstrap/cache
```

## 5. Commandes Artisan

Via SSH ou Terminal Hostinger :

```bash
cd ~/public_html/morex

# Generer la cle
php artisan key:generate

# Creer le lien storage
php artisan storage:link

# Migrations
php artisan migrate --force

# Seeders (admin + categories)
php artisan db:seed --force

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 6. Configuration .htaccess

Le fichier `public/.htaccess` devrait deja etre correct. Si probleme, verifier :

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

## 7. Verification

1. Acceder a https://morex.mebodorichard.com
2. Se connecter avec :
   - Email : `mebodoaristide@gmail.com`
   - Mot de passe : `274784336277`
3. Tester l'API : https://morex.mebodorichard.com/api/docs

## 8. App Mobile

Dans `lib/core/constants/api_constants.dart`, la variable `isProduction` est deja sur `true`.

L'app mobile pointera vers : `https://morex.mebodorichard.com/api`

## Troubleshooting

### Erreur 500

```bash
php artisan config:clear
php artisan cache:clear
chmod -R 755 storage
```

### Erreur de connexion DB

Verifier les credentials dans `.env` et que la base existe.

### Images non affichees

```bash
php artisan storage:link
```

### API non accessible

Verifier que CORS est configure et que le SSL est actif.
