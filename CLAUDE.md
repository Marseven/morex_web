# Morex - Pilotez votre avenir financier

## Contexte Projet
- **Client** : Projet personnel (Richard Mebodo)
- **Type** : App mobile (Flutter) + Web (Laravel/Vue)
- **Objectif** : Gestion finances personnelles alignée sur objectifs 2026
- **Deadline** : MVP Mobile ~6 semaines

## Stack Technique

### Mobile
- **Framework** : Flutter 3.x
- **State** : BLoC/Cubit
- **DB locale** : Drift (SQLite)
- **HTTP** : Dio
- **Architecture** : Clean Architecture

### Backend
- **Framework** : Laravel 11
- **Auth** : Sanctum
- **DB** : MySQL 8
- **Hébergement** : Hostinger

### Web
- **Framework** : Vue 3 + Inertia.js
- **CSS** : TailwindCSS

## Commandes Essentielles

### Flutter (Mobile)
```bash
# Dev
flutter run

# Build APK
flutter build apk --release

# Tests
flutter test

# Générer code Drift
dart run build_runner build
```

### Laravel (API)
```bash
# Dev
php artisan serve

# Migrations
php artisan migrate
php artisan migrate:fresh --seed

# Tests
php artisan test

# Cache
php artisan config:cache
php artisan route:cache
```

## Structure Projet

```
morex/
├── mobile/          # App Flutter
├── api/             # Backend Laravel
├── web/             # (dans api/ avec Inertia)
└── docs/            # Documentation projet
    ├── product-brief.md
    ├── prd.md
    ├── architecture.md
    └── stories/
```

## Workflow Dev Junior

**IMPORTANT** : Claude agit comme dev junior supervisé.

### Avant chaque tâche
1. Lis la story dans `docs/stories/STORY-XXX.md`
2. Lis les fichiers référencés (architecture, PRD si besoin)
3. Propose un plan d'implémentation
4. **ATTENDS validation** avant de coder

### Pendant le dev
1. Implémente par étapes incrémentales
2. Commits atomiques : `feat(morex-xxx): description`
3. Lance les tests après chaque modification
4. Signale tout blocage immédiatement

### Après implémentation
1. Résume les changements
2. Liste les fichiers modifiés/créés
3. Indique les tests ajoutés
4. Vérifie les critères d'acceptation

## Standards de Code

### Flutter/Dart
- Analyse : `flutter analyze` doit passer
- Format : `dart format .`
- Null safety strict
- Nommage : camelCase pour variables, PascalCase pour classes

### PHP/Laravel
- PSR-12
- Typed properties et return types
- Form Requests pour validation
- Resources pour API responses

### Commits
```
type(scope): description

Types: feat, fix, docs, style, refactor, test, chore
Scope: mobile, api, web, docs
```

**IMPORTANT** :
- Ne JAMAIS ajouter `Co-Authored-By:` dans les commits
- Ne JAMAIS signer les commits (pas de --gpg-sign)

## Architecture Sync (IMPORTANT)

**Le Web est la SOURCE PRINCIPALE, le Mobile est un CLIENT/CACHE.**

### Principe fondamental
```
WEB (MySQL) = Source de vérité, toutes les données vivent ici
MOBILE (SQLite) = Cache local + stockage temporaire offline
```

### Mode ONLINE (connecté)
- Mobile AFFICHE les données du web (via cache local)
- Les calculs/traitements d'affichage peuvent être faits localement
- Si une donnée est déjà traitée et stockée en ligne → juste l'afficher
- **TOUTES les opérations CRUD vont DIRECTEMENT au serveur :**
  ```
  CREATE  → POST API   → succès → ajouter au cache local
  UPDATE  → PUT API    → succès → mettre à jour cache local
  DELETE  → DELETE API → succès → supprimer du cache local
  ```
- Le cache local est mis à jour APRÈS confirmation du serveur
- Pas de stockage local préalable en mode online

### Mode OFFLINE
- La BD locale sert de **CACHE** pour afficher les données
- Les nouvelles données sont stockées temporairement en local
- Marquées avec `syncStatus = pending` (en attente d'envoi)
- Aucune modification des données venues du web (read-only en cache)

### Reconnexion (Sync)
1. **Push** : Envoie UNIQUEMENT les données créées/modifiées localement
   - Données avec `syncStatus = pending`
   - Données SANS `server_id` (jamais envoyées au web)
2. **Pull** : Récupère les mises à jour du web → met à jour le cache local

### Catégories (cas spécial)
- **Catégories système** : Définies sur le web (`user_id = NULL`)
- Le mobile NE DOIT PAS créer de catégories → utiliser celles du serveur
- À la première connexion, pull les catégories et les cache localement
- Les catégories sont en lecture seule côté mobile

### Entités synchronisées
| Entité | Créable sur mobile | Notes |
|--------|-------------------|-------|
| Categories | ❌ NON | Viennent du web uniquement |
| Accounts | ✅ OUI | Sync bidirectionnelle |
| Transactions | ✅ OUI | Sync bidirectionnelle |
| Goals | ✅ OUI | Sync bidirectionnelle |
| Debts | ✅ OUI | Sync bidirectionnelle |
| RecurringTransactions | ✅ OUI | Sync bidirectionnelle |
| BudgetCycles | ❌ NON | Calculés sur le web |

### SyncStatus (mobile)
- `synced` (0) : Donnée venue du web, en cache, ne pas pusher
- `pending` (1) : Donnée créée/modifiée localement, à envoyer au web
- `conflict` (2) : Conflit détecté (rare)

## Points Critiques ⚠️

1. **Web = Source principale** : Toujours considérer le web comme référence
2. **Offline-First** : Le mobile DOIT fonctionner sans connexion
3. **Montants** : Toujours en FCFA (Integer), jamais de décimales
4. **UUIDs** : Utiliser UUID v4 pour tous les IDs (sync-friendly)
5. **Sécurité** : Jamais de credentials en dur
6. **Catégories système** : Ne pas les modifier, ne pas créer de doublons

## Références

| Document | Chemin | Usage |
|----------|--------|-------|
| Product Brief | `docs/product-brief.md` | Vision & contexte |
| PRD | `docs/prd.md` | Specs fonctionnelles |
| Architecture | `docs/architecture.md` | Specs techniques |
| Stories | `docs/stories/` | Tâches à implémenter |

## Objectifs Financiers (Contexte métier)

L'app doit aider à atteindre :
- Fonds d'Urgence : **2 610 000 FCFA**
- Taux d'épargne : **25%** des revenus
- Plafond Sorties : **50 000 FCFA/mois**
- Plafond Dons : **30 000 FCFA/mois**
- Revenus Projets : **100%** → Investissement
