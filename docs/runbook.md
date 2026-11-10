# Runbook — Morex en production

**Conclusion** : hébergement mutualisé Hostinger (LiteSpeed, PHP 8.5), application dans `~/domains/mebodorichard.com/public_html/morex`, sans CI. Ce document décrit, dans l'ordre : déployer, vérifier, sauvegarder, restaurer, revenir en arrière.

## 1. Vérifications du `.env` de production (une fois, puis à chaque changement)
- `APP_ENV=production` : c'est la condition du garde-fou qui force le debug à off.
- `APP_DEBUG=false` : test rapide, `https://morex.mebodorichard.com/api/route-inexistante` ne doit renvoyer que `{"message": "Not Found."}`, sans `exception` ni `file`.
- `SESSION_LIFETIME` : absent (30 min par défaut) ou `30`. `SESSION_SECURE_COOKIE=true`.
- Facultatif : `BACKUP_MYSQLDUMP` (chemin complet si `mysqldump` est introuvable depuis le cron), `BACKUP_KEEP_DAYS` (14 par défaut).

## 2. Déployer
```bash
cd ~/domains/mebodorichard.com/public_html/morex
php artisan down --retry=60
git fetch && git checkout <tag-ou-commit>    # noter le commit précédent pour le retour arrière
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan up
```
Les assets (`pnpm build`) se construisent en local et se versionnent dans `public/build`, ou s'envoient par FTP.
Ensuite : lancer les commandes ponctuelles listées dans la PR livrée (par exemple `transactions:reverse-pending-balance`, une seule fois).

## 3. Contrôles après déploiement
- `https://morex.mebodorichard.com/up` → 200 (l'app démarre et la base répond).
- Les en-têtes `Strict-Transport-Security` et `X-Frame-Options` sont présents, `X-Powered-By` est absent (`curl -I`). Si `X-Powered-By` reste, ajouter `expose_php = Off` dans le PHP de hPanel.
- `/api/documentation` redirige vers `/login` quand on n'est pas connecté.
- Surveillance : une sonde gratuite (UptimeRobot ou équivalent) sur `/up`, toutes les 5 minutes.

## 4. Cron (hPanel → Avancé → Tâches Cron), toutes les minutes
```
/usr/bin/php /home/u626597620/domains/mebodorichard.com/public_html/morex/artisan schedule:run >> /dev/null 2>&1
```
Le cron exécute le rappel de saisie à 20 h et la sauvegarde à 3 h. Vérification : `php artisan schedule:list`.

## 5. Sauvegardes
- Automatiques à 3 h dans `storage/app/private/backups/morex-AAAAMMJJ-HHMMSS.sql.gz`, conservées 14 jours. Lancement manuel : `php artisan backup:database`.
- En cas d'échec, le journal contient `BACKUP_FAILED`, avec le code de sortie de `mysqldump`.
- **Copie hors serveur** : chaque mois, télécharger la plus récente (gestionnaire de fichiers hPanel). Les sauvegardes propres à Hostinger s'y ajoutent, sans les remplacer.

## 6. Restaurer
```bash
php artisan down
gunzip -c storage/app/private/backups/morex-<date>.sql.gz | mysql -u <utilisateur> -p <base>
php artisan up
```
Toujours restaurer d'abord sur une base de test pour vérifier le fichier, sauf en cas d'urgence.

## 7. Revenir en arrière
`php artisan down` → `git checkout <commit-précédent>` → `composer install --no-dev` → `php artisan migrate:rollback --step=<n>`, seulement si la version annulée contenait des migrations → caches (étape 2) → `php artisan up`.
