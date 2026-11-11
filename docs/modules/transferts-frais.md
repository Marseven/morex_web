# Module transferts-frais (3c)                      statut : en cours · 2026-11-12

**Conclusion** : un transfert entre deux comptes peut porter des **frais**, par exemple :
- retrait Airtel Money → Portefeuille, 50 000 + 500 de frais ;
- GIMAC BGFI → Airtel, 100 000 + 1 100.

Le compte source baisse du montant plus les frais, le compte destination reçoit le montant. Comme au module 3a, les frais deviennent une **dépense séparée** « Frais bancaires », liée au transfert, et jamais fondue dans le montant. Ils comptent ainsi dans les dépenses et dans le bilan. La saisie, la modification, la suppression et l'**import CSV** des transferts prennent les frais en charge.

**But** : des soldes justes sur les retraits et les virements inter-opérateurs, sans saisir les frais à part. **Acteurs / droits** : Richard, sur ses propres comptes.

**Scénarios**
1. Transferts → Nouveau : Airtel → Portefeuille, 50 000, frais 500 (pré-rempli avec les derniers frais du même couple de comptes, modifiable) → Enregistrer.
2. Effets :
   - Airtel −50 500, Portefeuille +50 000 ;
   - une dépense « Frais de transfert Airtel → Portefeuille » de 500 dans « Frais bancaires », sur Airtel, à la même date ;
   - le transfert affiche « 50 000 · frais 500 ».
3. Modifier les frais à 600 : la dépense liée passe à 600. Les mettre à 0 : la dépense liée est supprimée. Supprimer le transfert : ses frais aussi.
4. Import CSV : une colonne `frais` facultative, avec les mêmes effets par ligne, dans une seule transaction.
- Erreurs :
  - frais négatifs ou supérieurs au montant → 422 ;
  - compte d'autrui → 422 (inchangé) ;
  - même compte des deux côtés → 422.

**Données** (migration réversible) : `transfers.fee` (entier, 0 par défaut) et `transfers.fee_transaction_id` (FK nullable vers `transactions`, `nullOnDelete`).

**API / écrans**
- Web : champ « Frais » dans Transferts (création et modification), colonne « Frais » dans l'import et dans la liste.
- API : `fee` accepté et renvoyé par `/api/transfers` (création, modification, multi-sauts).
- Synchro : un transfert poussé sans `fee` garde ses frais (champ absent : inchangé).

**Règles métier**
- RM-1 Les frais sont une dépense séparée, de catégorie système « Frais bancaires », sur le compte source et à la date du transfert. Ils ne sont jamais ajoutés au montant transféré.
- RM-2 Le lien est porté par le transfert (`fee_transaction_id`). Modifier, supprimer ou restaurer le transfert répercute ses frais, toujours via les événements de `Transaction` (solde, `balance_history`, cycles, plafonds).
- RM-3 0 ≤ frais ≤ montant ; frais à 0, pas de dépense.
- RM-4 Proposition : les frais du dernier transfert du même couple de comptes, sinon 0.

**Sécurité** : Form Request (comptes de l'utilisateur, entiers bornés) ; Policy existante sur les transferts ; aucun nouveau log.

**Tests prévus** :
- service : création avec ou sans frais, modification des frais (hausse, mise à 0, ajout), changement de compte source, suppression et restauration, soldes et `balance_history` ;
- endpoints web et API (200, 403, 422) ;
- import avec colonne `frais` ;
- synchro sans `fee` ;
- frais comptés dans les dépenses du cycle.

**Décisions** : le lien est porté par le transfert, comme `statement_lines.fee_transaction_id` au module 3a ; un service `TransferService` porte la logique ; aucune dépendance nouvelle.

**Questions** (hypothèse par défaut)
1. Faut-il une grille de frais automatique (Airtel, GIMAC) ? → *Hypothèse : non. Les derniers frais du même couple de comptes sont proposés, et vous corrigez si besoin.*
2. Le rapprochement des relevés (module 3a) doit-il reconnaître un transfert avec frais (ligne de relevé = montant + frais) ? → *Hypothèse : non dans ce module, à prévoir avec un prochain module relevés.*
3. Saisie des frais sur l'iPhone ? → *Hypothèse : non, avec l'écran mobile des transferts. Les frais saisis sur le web arrivent sur l'iPhone par la synchro, comme une dépense ordinaire.*

**Hors module** : grille de frais, rapprochement des transferts dans les relevés, écran mobile.
