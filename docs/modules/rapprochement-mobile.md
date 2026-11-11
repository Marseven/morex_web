# Module rapprochement-mobile (4 bis)               statut : en cours · 2026-11-12

**Conclusion** : le **rapprochement d'un compte** arrive sur l'iPhone, branché sur l'API du module 4, sans changement serveur.
1. Je tape le solde réel lu dans l'app Airtel ou BGFI.
2. Je coche les opérations déjà passées sur le relevé.
3. L'écart se calcule en direct ; je valide.

L'écart devient une opération tracée, créée par le serveur sous verrou, puis l'app se synchronise. L'écran suit la vue iPhone déjà prévue au design A du module 4 (`docs/design/rapprochement.md`) : cartes à cocher et barre fixe « Écart · Valider ».

**But** : rapprocher un compte en une minute depuis le téléphone, là où arrivent les soldes. **Acteurs / droits** : Richard, sur ses propres comptes (Policy serveur existante).

**Scénarios**
1. Comptes → un compte → « Rapprocher » : synchro d'abord, pour que le serveur voie les dernières saisies, puis chargement (solde Morex, dernier rapprochement, opérations non pointées).
2. Saisie du solde du relevé, 152 300 ; les cases sont cochées par défaut. Je décoche le taxi pas encore passé : l'aperçu donne « Écart ▲ +1 800 à justifier ».
3. « Valider » : avec un écart non nul, une confirmation « Créer une opération d'écart de +1 800 FCFA ? ». Envoi, puis synchro, puis retour au compte avec « Rapproché aujourd'hui ».
4. Écart nul : pas de confirmation, juste un message.
- Erreurs :
  - hors connexion : « Le rapprochement se fait en ligne », sans perte de la saisie ;
  - 422 `INVALID_RECONCILIATION` (opération déplacée entre-temps) : bandeau « Rechargez », cases conservées ;
  - opérations plus anciennes que la fenêtre (`older_count`) : mention « N opérations plus anciennes, pointées d'office ».

**Données** : aucune migration. Les ids d'opérations renvoyés par le serveur repartent tels quels ; seul l'id du compte est traduit de local en serveur (comme au module 2 ter).

**API / écrans** (mobile)
- `GET /api/accounts/{serverId}/reconciliation` et `POST /api/accounts/{serverId}/reconciliations` (`statement_balance`, `cleared_ids[]`, `note`).
- Nouvelle page `ReconciliationPage` (route `/accounts/:id/reconcile`) avec `ReconciliationCubit` ; bouton « Rapprocher » et badge « Rapproché il y a N j » sur la fiche du compte.

**Règles métier**
- RM-1 Aperçu de l'écart = relevé − (solde Morex − somme signée des opérations non cochées). Le serveur recalcule et fait foi (RM-5 du module 4).
- RM-2 Le rapprochement se fait en ligne : la synchro le précède et le suit. Aucun écart n'est créé localement.
- RM-3 Un compte jamais synchronisé (sans id serveur) ne peut pas être rapproché : message « Synchronisez d'abord ».

**Sécurité** : jeton existant ; validation et Policy côté serveur ; montants entiers bornés à ±10¹² (serveur).

**Tests prévus** :
- cubit : chargement, aperçu de l'écart en cochant et décochant, validation (écart nul ou non), hors connexion, 422 avec cases conservées, compte sans id serveur ;
- source de données : JSON du serveur (`older_count`, `last_reconciliation` nul).

**Décisions** : vue iPhone du design A du module 4, sans nouvelle étape de design ; en ligne uniquement.

**Questions** (hypothèse par défaut)
1. Reprendre la vue iPhone du design du module 4 sans nouvelle proposition ? → *Hypothèse : oui.*
2. Cases cochées par défaut, comme sur le web ? → *Hypothèse : oui, vous décochez ce qui n'est pas encore passé.*
3. Rappel hebdomadaire de rapprochement (notification) ? → *Hypothèse : non dans ce module.*

**Hors module** : rapprochement hors connexion, rappel, pointage des transferts (hors V1 au module 4).
