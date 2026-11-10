# Module rapprochement                                statut : livré · 2026-11-10

**Conclusion** : je saisis le solde réel d'un compte, l'app montre l'écart et les opérations pas encore pointées, et l'écart devient une **opération tracée** (catégorie système « Écart de rapprochement »). Le solde ne change plus jamais en silence. Pour rester sous 15 fichiers, le module se scinde : ici le **serveur, l'API et l'écran web** ; l'écran mobile devient le module 4 bis (`rapprochement-mobile`), qui appellera la même API.

**But** : rapprocher un compte avec son relevé en moins de 2 minutes, et garder la trace de chaque écart. **Acteurs / droits** : Richard, sur ses propres comptes uniquement (Policy `AccountPolicy::update`).

**Scénarios**
1. Nominal : Comptes → « Rapprocher » sur Airtel Money → saisie du solde du relevé (152 300) → l'app affiche « Solde Morex 150 000 · écart +2 300 » et la liste des opérations non pointées depuis le dernier rapprochement.
2. Je coche celles qui figurent sur le relevé, je corrige au besoin une opération (lien vers son édition) et l'écart se recalcule.
3. « Valider » : l'écart restant devient une opération (revenu si positif, dépense si négatif, datée du jour), les opérations cochées sont marquées pointées, et un rapprochement est enregistré (solde du relevé, solde avant, écart, opération d'écart).
4. Écart nul → rapprochement enregistré sans opération. Page Comptes : « Rapproché il y a 3 j » sous chaque compte (badge orange au-delà de 7 jours).
- Erreurs : compte d'un autre utilisateur → 403 ; solde non entier ou opération cochée qui n'appartient pas au compte → 422 (`INVALID_RECONCILIATION`) ; deux validations simultanées → la seconde voit le solde mis à jour (verrou de `adjustBalance`).

**Données** (migrations réversibles)
- `reconciliations` : `id` uuid, `user_id`, `account_id`, `statement_balance`, `balance_before`, `difference`, `adjustment_transaction_id` (nullable), `reconciled_at`, `note` (nullable), soft delete. Non synchronisé avec le mobile.
- `transactions.reconciled_at` : timestamp nullable, index `(account_id, reconciled_at)`. Le mobile ignore ce champ.
- La catégorie système « Écart de rapprochement » (`user_id` NULL, revenu et dépense) est créée par une migration de données, avec un `down()` qui la retire.

**API / écrans**
- `GET /api/accounts/{id}/reconciliation` : solde actuel, dernier rapprochement, opérations non pointées (paginées, 100 maximum).
- `POST /api/accounts/{id}/reconciliations` : `statement_balance`, `cleared_ids[]`, `note`.
- Les deux sont en `auth:sanctum` + `throttle:60,1`, avec Form Request et Resource.
- Web (Inertia) : page `Accounts/Reconcile.vue` et badge sur `Accounts/Index.vue`.
- `adjust-balance` (web et API) est conservé pour compatibilité, mais passe désormais par le service et crée donc une opération d'écart.

**Règles métier**
- RM-1 : un écart ne modifie le solde qu'à travers une transaction (événements de `Transaction` → `adjustBalance`), jamais en écrivant `balance` directement.
- RM-2 : sont « non pointées » les transactions confirmées, non supprimées, du compte, avec `reconciled_at` NULL et une date ≤ aujourd'hui. Les transferts sont hors pointage en V1.
- RM-3 : le premier rapprochement d'un compte sert de base datée ; aucun audit rétroactif.
- RM-4 : l'opération d'écart est **exclue des cycles budgétaires et des plafonds** (sorties, dons), mais compte dans le solde et apparaît dans l'historique.
- RM-5 : montants en FCFA entiers ; écart = solde du relevé − (solde Morex − somme signée des opérations **non cochées**), recalculé par le serveur sous verrou. Les opérations non cochées ne sont pas compensées : elles restent à pointer (décision du 2026-11-10, voir `docs/design/rapprochement.md`).

**Sécurité** : Policy sur le compte, plus vérification que chaque `cleared_id` appartient bien au compte (protection IDOR) ; Form Request stricte (`integer`, `uuid`, `max:500` pour la note) ; journaux sans montant ni note.

**Tests prévus** : service (écart positif, négatif ou nul ; IDOR sur `cleared_ids` ; écart exclu du cycle) ; endpoints (200, 401, 403, 422) ; `adjust-balance` qui crée désormais une opération ; migration avec `down()`.

**Décisions** : `balance-reset` (aucun appel côté web ni mobile, et contourne verrou et historique) est **supprimé**. Pas d'ADR : le choix reste local au module.

**Questions** (hypothèse par défaut)
1. Faut-il scinder en 4 (web) et 4 bis (mobile) ? → *Hypothèse : oui, j'ajoute `rapprochement-mobile` au plan.*
2. Faut-il exclure l'écart des budgets et plafonds (RM-4) ? → *Hypothèse : oui, sinon un écart de 50 000 fausse le taux d'épargne du mois.*
3. Peut-on supprimer `balance-reset` ? → *Hypothèse : oui (code mort qui contourne les invariants).*

**Hors module** : écran mobile (4 bis), pointage des transferts, rappel push hebdomadaire, import de relevés PDF ou CSV.

## Livré
- **Serveur** :
  - migrations réversibles `transactions.reconciled_at`, `reconciliations` et catégories système d'écart à UUID fixes (`Category::RECONCILIATION_*`), avec rollback testé ;
  - `ReconciliationService` (`reconcile`, `adjustTo`, verrou, fenêtre de pointage) ;
  - écarts exclus des cycles et de la page Budgets.
- **API** : `GET /api/accounts/{id}/reconciliation` (avec `older_count`), `POST /api/accounts/{id}/reconciliations`, `StoreReconciliationRequest`, `ReconciliationResource`, `UnclearedTransactionResource`. `balance-reset` est supprimé. `adjust-balance` (web et API) passe par le service et est borné à ±10¹².
- **Web** : `Accounts/Reconcile.vue` (direction A), `ReconciledBadge`, lien « Rapprocher » dans la page Comptes.
- **Tests** : 4 fichiers, 37 tests (schéma, service, API 401/403/422, web). Suite : 309 au vert. Parcours vérifié dans le navigateur sur une instance locale jetable : relevé 152 300, taxi décoché → écart +1 800, solde final 151 800, badge à jour, taxi resté à pointer, affichage mobile correct.
- **Écarts avec l'analyse** :
  - RM-5 amendée (opérations non cochées non compensées) ;
  - pas de borne de date sur les opérations à pointer ;
  - fenêtre des 500 opérations les plus récentes : les plus anciennes sont pointées d'office (RM-3), au lieu d'une pagination à 100.
- **Revue de sécurité** :
  - aucun contournement d'autorisation ni IDOR ;
  - point haut corrigé : une opération future créait un faux écart ;
  - points moyens corrigés : écart affiché différent au-delà de 500 opérations, montant d'ajustement non borné ;
  - point bas corrigé : test 403 du POST web.
- **Reste ouvert (module 7)** :
  1. une opération déjà pointée reste modifiable depuis le web ou le mobile sans alerte, ce qui peut faire dériver le solde rapproché ;
  2. les catégories d'écart sont sélectionnables lors d'une saisie normale ; il faudra les refuser dans les validations.
- **Non vérifié** : le verrou sur MySQL réel (les tests tournent sous SQLite).
