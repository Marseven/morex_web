# Morex Web — API + interface web (Laravel 11, Inertia/Vue 3)

App de finances **personnelle** de Richard : un seul utilisateur en production, pas de publication publique.
Le web est la **source de vérité** ; le mobile (`morex_mobile`, iPhone) est un client/cache hors ligne.

## Production
- https://morex.mebodorichard.com — Hostinger mutualisé (LiteSpeed, PHP 8.5.12), app dans `public_html/morex`.
- Pas de CI ni de Deployer : déploiement manuel. Après chaque déploiement : `php artisan migrate`, puis les commandes de rattrapage listées dans la PR.
- `APP_DEBUG=false` obligatoire en prod (vérifier : une route `/api/inexistante` ne doit renvoyer aucune trace).

## Commandes
```bash
APP_KEY="base64:$(openssl rand -base64 32)" php artisan test   # pas de .env local : clé éphémère
vendor/bin/pint --dirty
pnpm dev / pnpm build                                         # jamais npm
php artisan l5-swagger:generate                               # doc OpenAPI (attributs OA dans les contrôleurs)
```

## Invariants métier (ne jamais casser)
- Montants en **FCFA entiers**, IDs en **UUID v4**.
- Le solde d'un compte ne bouge **que** par `Account::adjustBalance()` (verrou `lockForUpdate` + ligne `balance_history`). Jamais de `update(['balance' => …])` direct.
- Les effets sur le solde partent des événements de `Transaction::booted()`. Une transaction `pending_validation` (`Transaction::STATUS_PENDING`) ne compte pas ; elle compte à sa validation. `BudgetCycle::transactions()` l'exclut aussi.
- Synchro (`SyncController`) : `push` désactive les événements puis rejoue les ajustements une seule fois. Un `delete` rejoué → `already_deleted`, un `create` rejoué → `linked` (traité comme un update). Conflits : dernier `updated_at` gagne.
- SMS : `POST /api/webhook/sms` (Sanctum) → `SmsParser` → transaction en attente, dédupliquée par `sms_fingerprint` (unique par utilisateur). Sur iPhone, l'envoi passe par un Raccourci iOS (l'app ne peut pas lire les SMS).
- Catégories système (`user_id = NULL`) : en lecture seule, jamais modifiées ni dupliquées.
- 2FA API : `/login` renvoie un `login_token` (5 min, lié au hash du mot de passe) à présenter à `/two-factor-challenge`. Jamais de `user_id` seul.

## Entités synchronisées avec le mobile
Comptes, transactions, virements, objectifs, dettes, récurrences : créables des deux côtés.
Catégories et cycles budgétaires : créés/calculés sur le web uniquement.

## Où regarder
- `app/Models/{Account,Transaction,BudgetCycle}.php` — règles de solde.
- `app/Http/Controllers/Api/` — API mobile ; `app/Http/Controllers/` — pages Inertia (`resources/js/Pages/`).
- `app/Services/SmsParser.php` — formats SMS (Airtel Money, Moov Money, banques).
- `app/Console/Commands/` — commandes ponctuelles (rattrapages).
- `tests/Feature/` — un test de non-régression par bug corrigé.

## Objectifs financiers (contexte des écrans de suivi)
Fonds d'urgence 2 610 000 FCFA · épargne 25 % des revenus · sorties ≤ 50 000 FCFA/mois · dons ≤ 30 000 FCFA/mois · revenus projets → 100 % investissement.

## Façon de travailler
- Socle MDF (`~/.claude`) : mode Express pour un bug, `/cadrage` puis `/module` pour une évolution. Plan courant : `docs/plan.md`.
- Commits conventionnels en anglais, scopes `api`, `web`, `sync`. Richard est l'unique auteur : aucune attribution IA.
- Branches `fix/…`, `feat/…`, `docs/…` depuis `main` (pas de `develop` sur ce dépôt), PR vers `main`.
- Une PR qui change un contrat d'API se livre avec la PR mobile correspondante.
