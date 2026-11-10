# Module socle-prod                                   statut : analysé · 2026-11-10

**Conclusion** : 5 garde-fous dans le code et un runbook. Trois actions restent à Richard dans hPanel : couper `APP_DEBUG`, activer le cron, vérifier la sauvegarde Hostinger.

**But** : la production ne laisse plus rien fuiter, elle se sauvegarde chaque jour et on sait la redéployer et la restaurer. **Acteurs / droits** : Richard (seul utilisateur, connecté) ; le cron Hostinger.

**Scénarios**
1. Une erreur survient en production → réponse générique (`{"message":"Server Error"}`), sans trace ni chemin, même si `APP_DEBUG=true` a été oublié dans le `.env`.
2. Chaque réponse porte les en-têtes de sécurité ; `X-Powered-By` disparaît.
3. `/api/documentation` sans session → redirection vers `/login` (302) ; connecté → la doc s'affiche.
4. Chaque nuit à 03:00, `backup:database` écrit `storage/app/private/backups/morex-AAAAMMJJ-HHMM.sql.gz` et supprime les copies de plus de 14 jours.
5. `/up` vérifie aussi la base : 200 si la base répond, 500 sinon (pour une surveillance externe gratuite).
- Erreurs : `mysqldump` absent ou en échec → la commande sort en erreur et le journal note `BACKUP_FAILED`, sans identifiants ; la base ne répond pas → `/up` renvoie 500.

**Données** : aucune migration. Les sauvegardes restent hors de `public/`.

**API / écrans** : pas de nouvel écran. `/up` (existant) enrichi ; `/api/documentation` protégé par la session web.

**Règles métier**
- RM-1 : en `production`, `app.debug` est forcé à `false` dans le code, quel que soit le `.env`.
- RM-2 : une sauvegarde est un fichier compressé, horodaté, conservé 14 jours.
- RM-3 : le mot de passe de la base n'apparaît jamais en ligne de commande (fichier `--defaults-extra-file` temporaire en 0600, supprimé après usage).

**Sécurité** : middleware `SecurityHeaders` (HSTS 1 an, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` restrictive, CSP non bloquante `frame-ancestors 'none'; base-uri 'self'; object-src 'none'; upgrade-insecure-requests`) ; session web ramenée à 30 min par défaut ; journaux sans secret.

**Tests prévus** (Feature) : en-têtes présents sur une page web et sur l'API ; `/api/documentation` en 302 sans session et en 200 connecté ; debug forcé à `false` en production (aucune trace dans la réponse 500) ; `backup:database` : fichier créé, rotation à 14 jours, échec propre (exécution de `mysqldump` simulée) ; `/up` en 500 si la base tombe.

**Décisions** : pas de nouvelle dépendance (`spatie/laravel-backup` est écarté : trop lourd pour un mutualisé) ; `Process` de Laravel avec timeout pour `mysqldump` ; CSP stricte sur les scripts reportée (risque de casser Vite et Inertia).

**Questions** (hypothèse retenue par défaut)
1. `APP_ENV=production` est-il bien défini sur le serveur ? → *Hypothèse : oui ; sinon RM-1 ne s'applique pas, et le runbook le fait vérifier.*
2. Comment déployez-vous aujourd'hui (git pull en SSH, FTP, gestionnaire de fichiers) ? → *Hypothèse : git pull + commandes artisan ; le runbook décrit ce cas.*
3. Copie hors serveur ? → *Hypothèse : on s'appuie sur la sauvegarde Hostinger + un téléchargement manuel mensuel ; pas d'envoi externe automatique dans ce module.*

**Hors module** : CSP stricte, CI (module 7), alertes de surveillance, copie de sauvegarde vers un service externe.
