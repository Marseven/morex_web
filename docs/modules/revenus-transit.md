# Module revenus-transit (5c)                       statut : livré · 2026-11-11

**Conclusion** : chaque catégorie reçoit une **nature** : `courante` (par défaut), `transit` (tontines, remboursements, avances) ou `pro` (outils MRTECH). Les catégories en transit sortent des revenus, des dépenses, des cycles, des statistiques et des plafonds ; un encart « En transit » montre ce qui est sorti et revenu, et le net. Le pro reste compté dans le total, mais il est affiché à part et exclu du budget perso. Un seul filtre central remplace les exclusions dispersées.

**But** : que « revenus » et « dépenses » disent vrai. Exemple d'octobre : une tontine de 500 000 reçue gonflait les revenus, et un remboursement d'ami passait pour du freelance. **Acteurs / droits** : Richard, sur ses propres catégories (les catégories système restent en lecture seule).

**Scénarios**
1. Budgets → catégorie « Tontine » → Nature : *Transit* → enregistrer.
2. La cotisation de 50 000 (dépense) et la levée de 500 000 (revenu) disparaissent des totaux du tableau de bord, du cycle, d'Analytics et de `/api/stats`. Les soldes des comptes ne bougent pas : l'argent a bien circulé.
3. Le tableau de bord affiche un encart « En transit ce cycle : sorti 150 000 · revenu 500 000 · net +350 000 », et « Pro (MRTECH) : 62 000 » sous les dépenses.
4. Les catégories « Abonnements pro » et « Outils MRTECH » passent en *Pro* : leurs montants sortent du total « perso » comparé au budget, et le plafond Abonnements 90 000 ne compte que le perso.
- Erreurs :
  - nature inconnue → 422 ;
  - catégorie système → 403 (inchangé) ;
  - catégorie de rapprochement → nature forcée, non modifiable.

**Données** (migration réversible) : `categories.nature` (`regular` | `transit` | `pro`, défaut `regular`, indexée).

**API / écrans**
- `nature` acceptée sur `POST|PUT /categories` (web et `/api`), renvoyée dans la Resource (le mobile l'ignore sans casse).
- Formulaire catégorie (Budgets/Create et Edit) : sélecteur de nature avec une aide.
- Tableau de bord : encart « En transit » et ligne « Pro ».

**Règles métier**
- RM-1 Revenus et dépenses « comptés » = types income et expense, hors en attente, hors rapprochement, **hors transit** : un seul scope `Transaction::counted()`, utilisé par `BudgetCycle`, le tableau de bord, Analytics et Stats.
- RM-2 Pro : compté dans les dépenses totales, mais affiché à part ; exclu du total « perso » et des plafonds perso. Une catégorie pro peut avoir son propre plafond.
- RM-3 Transit : ni plafond ni alerte ; net = entrées − sorties sur la période.
- RM-4 Changer la nature recalcule à la lecture (aucune donnée réécrite) ; les clôtures de cycle déjà figées restent telles quelles.
- RM-5 Les virements entre comptes (déjà de type `transfer`) restent hors revenus et dépenses : rien ne change.

**Sécurité** : validation `in:regular,transit,pro` ; Policy existante sur les catégories ; aucun nouveau log.

**Tests prévus** :
- scope `counted` (transit exclu, pro inclus, rapprochement exclu) ;
- totaux du cycle, du tableau de bord, d'Analytics et de `/api/stats` ;
- plafond et alerte ignorés en transit ;
- endpoints 200, 403, 422 ;
- encart transit (net).

**Décisions** : nature portée par la **catégorie**, pas par l'opération (un seul réglage par tontine ; YAGNI) ; pas de nouvelle dépendance.

**Questions** (hypothèse par défaut)
1. Un remboursement reçu peut-il aussi être une dépense avancée (vous payez pour un ami, il vous rembourse) ? → *Hypothèse : oui, les deux côtés vont dans une même catégorie transit (« Avances / remboursements »), et le net montre ce qu'on vous doit encore.*
2. Le pro doit-il disparaître complètement des totaux ? → *Hypothèse : non, il reste dans le total mais est affiché à part, car l'argent sort bien de vos comptes.*
3. Faut-il reclasser l'historique ? → *Hypothèse : non. Il suffit de changer la nature des catégories et tout se recalcule ; les cycles déjà clôturés gardent leurs chiffres.*

**Hors module** : nature par opération, suivi nominatif des dettes de tontine (le module Dettes existe), refacturation pro, écran mobile dédié.

## Livré (5c)
- **Données** : migration réversible `categories.nature` (`regular`, `transit` ou `pro`, indexée), rollback testé.
- **Règles** :
  - scopes `Transaction::counted()` (hors attente, écarts et transit), `personal()` (hors pro) et `ofNature()` ;
  - `Category::personalBudgetFor()`, partagé par les cycles, leur création et la commande `budget:recompute-closures`.
- **Branché sur** :
  - cycles (dépensé perso face aux plafonds perso ; épargne = revenus − toutes les dépenses comptées) ;
  - tableau de bord ;
  - Analytics ;
  - `/api/stats/*` ;
  - `/api/transactions-stats` ;
  - alertes de plafond (transit ignoré).
- **API** : `nature` validée (`in`) et exposée par la Resource ; filtre `/transactions?nature=transit`.
- **Web** (direction A) :
  - sélecteur `NatureField` dans Budgets/Create et Edit ;
  - carte `TransitCard` sur le tableau de bord ;
  - ligne « Pro (MRTECH) » sous les dépenses.
- **Tests** : `TransitIncomeTest`, 16 tests. Suite complète : 468 au vert.
- **Vérifié dans le navigateur**, au format iPhone, sur une instance locale jetable : sélecteur, carte transit (+350 000), ligne pro, filtre « Voir tout ».
- **Revue de code, points hauts corrigés** : `/api/transactions-stats` et les séries de `/api/stats/monthly` passent par `counted()` ; création de cycle et recalcul des clôtures sur le budget perso.
- **Revue de code, points moyens corrigés** : alertes via `counted()` ; natures lues une fois ; tests (sans catégorie, catégorie supprimée, clôture).
- **Écart avec l'analyse** :
  - les totaux du tableau de bord excluent désormais aussi les opérations en attente et les écarts de rapprochement ;
  - changer la nature reclasse l'historique à la lecture, mais les clôtures déjà créées restent figées (RM-4).
- **Hors module** (signalé par la revue, antérieur au module) : le dépensé par catégorie des listes Budgets et Cycles compte encore les opérations en attente. À traiter avec le module 7 (dette qualité).
- **Non vérifié** :
  - MySQL réel ;
  - l'app iPhone face au champ `nature` (attendu : ignoré).
