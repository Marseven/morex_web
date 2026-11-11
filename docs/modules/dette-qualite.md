# Module dette-qualite (7a : web)                   statut : en cours · 2026-11-11

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
