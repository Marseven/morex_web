# Module dette-qualite (7a : web)                   statut : livré · 2026-11-11

**Conclusion** : on solde la dette du web relevée par l'analyse initiale et par les revues des modules 4 à 6, sans fonctionnalité nouvelle. Le module est **scindé** pour rester sous 15 fichiers :
- **7a, web** : ce module ;
- **7b, mobile** : 4 tests périmés, avertissements `flutter analyze`, jeton de 30 jours et base Drift en clair (P15), hook qui modifie `analysis_options.yaml`.

**But** : des soldes et des chiffres qui ne dérivent pas, une CI qui le prouve. **Acteurs** : Richard ; la CI GitHub.

**Points traités**
1. **Opérations rapprochées** (module 4) : modifier le montant, la date, le compte ou le type d'une opération pointée la **dépointe** automatiquement (web, API, synchro), avec le message « Opération dépointée : refaites le rapprochement du compte ». Le solde rapproché ne dérive plus en silence.
2. **Catégories d'écart** (module 4) : refusées en saisie unitaire web et API (422) et en synchro, sauf sur une opération d'écart existante qui les garde.
3. **P12** : changer la date d'une opération recalcule le cycle de l'**ancienne** date et celui de la nouvelle (aujourd'hui, seul le cycle actif de la nouvelle date l'est).
4. **P13** : webhook SMS avec une source vide (`LIKE '%%'`) : plus de correspondance au premier compte venu ; `%` et `_` sont échappés.
5. **P16** : export CSV, cellules commençant par `=`, `+`, `-`, `@`, tabulation ou retour chariot préfixées d'une apostrophe (injection de formule).
6. **Dépensé par catégorie** (module 5c) : listes Budgets et Cycles, et `spent_this_month`, passent par `counted()` (hors attente, écarts et transit).
7. **Saisie web** (module 5d) : `TransactionController::store` reçoit un `StoreTransactionRequest`, et l'orchestration (opération + achat prévu) passe dans un service.
8. **P17** : `Pdo\Mysql::ATTR_SSL_CA` quand la classe existe (PHP ≥ 8.4), sinon l'ancienne constante. Les centaines d'avertissements de dépréciation disparaissent des tests.
9. **CI** :
   - `ci.yml` teste sur PHP 8.5 (la version de production) en plus de 8.2 ;
   - ajout de `vendor/bin/pint --test`, de Gitleaks et du garde-fou `php-securite.yml` (règle du dossier htdocs) ;
   - déclenchement aussi sur les PR vers les branches `feat/*`.

**Données** : aucune migration.

**Sécurité** : Form Request, validation `notIn` sur les catégories d'écart, échappement CSV et LIKE, aucun nouveau log.

**Tests prévus** (un test de non-régression par point) :
- dépointage (web, API, synchro) ;
- catégorie d'écart refusée (web, API, synchro) et conservée sur une opération d'écart ;
- recalcul des deux cycles ;
- webhook avec source vide ;
- CSV échappé ;
- dépensé par catégorie ;
- 422 de la saisie.

**Décisions** :
- dépointer plutôt que bloquer : l'app reste utilisable et le rapprochement suivant reprend la main ;
- le plancher `composer.json` reste « ^8.2 » (question 1).

**Questions** (hypothèse par défaut)
1. `verifier-php.sh` suggère `">=8.5.12"` dans `composer.json`. → *Hypothèse : non. Hostinger fournit l'interpréteur, et un plancher au correctif casserait la CI 8.2. Le garde-fou passe par `php-securite.yml` en CI et par le script au déploiement.*
2. Faut-il demander une confirmation avant de modifier une opération pointée ? → *Hypothèse : non, le dépointage automatique suffit, avec un message.*
3. 7b (mobile) juste après ? → *Hypothèse : oui, dans une session dédiée.*

**Hors module** : 7b mobile, déploiement automatisé (Deployer), `BalanceService` unique (refonte des événements de modèle).

## Livré (7a)
- **Opérations pointées** :
  - changer le montant, la date, le compte, le type ou le statut d'une opération pointée la dépointe (web, API, synchro) ;
  - le web affiche « Opération dépointée : refaites le rapprochement du compte » ; la suppression d'une opération pointée affiche aussi un avertissement ;
  - le message `flash.warning` est partagé et affiché dans le layout.
- **Catégories d'écart** : la règle `NotReconciliationCategory` s'applique en saisie web et API, et la synchro les refuse aussi. Elle est insensible à la casse ; une opération d'écart existante garde sa catégorie.
- **P12** : les cycles de l'ancienne et de la nouvelle date sont recalculés une seule fois chacun, et seulement sur une modification matérielle. Un cycle clôturé ne met à jour que son dépensé : son budget reste celui de la clôture.
- **P13** : `WebhookController::accountIdForSource` ne cherche qu'avec une source non vide, jokers échappés et ordre stable.
- **P16** : les cellules de l'export CSV qui commencent par `= + - @`, une tabulation ou un retour chariot sont préfixées d'une apostrophe.
- **Dépensé par catégorie** :
  - les opérations en attente sont exclues (Budgets, Cycles, `spent_this_month`) ;
  - la borne haute de la période est exclusive au lendemain, indexable, et compte le dernier jour même sous SQLite.
- **Saisie web** : `StoreTransactionRequest` et `TransactionEntryService` ; message du transfert conservé.
- **P17** : `Pdo\Mysql::ATTR_SSL_CA` dans `config/database.php`. **Écart** : les avertissements restent dans les tests, car ils viennent du fichier par défaut du framework (`vendor/laravel/framework/config/database.php`, Laravel 11.48). Ils disparaîtront avec une mise à jour de Laravel (hors module).
- **CI** :
  - tests sur PHP 8.2 et 8.5 ;
  - Pint sur les fichiers de chaque PR (comparés au merge-base) ;
  - Gitleaks ;
  - garde-fou `php-securite.yml` (copie du modèle htdocs, avec `permissions` ajouté) ;
  - jeton en lecture seule, actions épinglées par SHA, Dependabot `github-actions` ;
  - déclenchement sur toutes les PR.
- **Tests** : `QualityDebtTest`, 22 tests. Suite complète : 550 au vert.
- **Revue de sécurité** (0 critique, 0 haute), points moyens corrigés :
  - casse des UUID ;
  - statut « en attente » qui contournait le dépointage ;
  - droits et épinglage de la CI.
- **Revue de sécurité**, points bas corrigés : injection d'expression dans la CI, correspondance SMS stable.
- **Revue de code**, point haut corrigé : l'avertissement n'atteignait pas l'écran.
- **Revue de code**, points moyens corrigés : double recalcul, filtre de date non indexable, merge-base de Pint.
- **Reste ouvert** (préexistant, signalé par les revues) :
  - `update` web et API sans Form Request ;
  - une opération d'écart peut voir son montant modifié ;
  - espace en tête de cellule CSV.
- **Non vérifié** :
  - premier passage de la CI sur GitHub ;
  - collation MySQL de production (la casse est désormais neutralisée par le code).
