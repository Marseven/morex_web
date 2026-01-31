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

## Architecture Sync (Important)

**Le Web est la source principale, le Mobile est un client.**

### Principes
1. **Web = Master** : La base de données web (MySQL) est la source de vérité
2. **Mobile = Client** : Le mobile synchronise avec le web, pas l'inverse
3. **Offline-First** : Le mobile fonctionne hors ligne avec SQLite, puis sync quand connecté

### Catégories
- **Catégories système** : `user_id = NULL`, partagées par tous, créées via seeder
- **Catégories utilisateur** : `user_id = X`, spécifiques à un utilisateur
- Le mobile doit utiliser les catégories du serveur (pas créer de doublons)
- Les catégories système sont en lecture seule

### Flux de sync
1. **Pull** : Mobile récupère les données du serveur (categories système + user)
2. **Push** : Mobile envoie ses changements locaux
3. **Matching** : Éviter les doublons par matching nom/type
4. **Last-write-wins** : En cas de conflit, la version la plus récente gagne

### Entités synchronisées
- Accounts, Categories, Transactions, Goals, Debts, RecurringTransactions, BudgetCycles

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
