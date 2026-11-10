# Module releves-sms (3a : rapprochement avec les relevés)    statut : livré · 2026-11-11

**Conclusion** : je colle le relevé d'un compte (historique Airtel Money ou export BGFI), Morex associe chaque ligne du relevé à l'opération que j'ai saisie (même montant ou montant + frais, à ±1 jour), crée les frais à part, et me soumet seulement ce qui ne correspond pas. C'est la « validation d'écriture » faite aujourd'hui à la main avec un assistant externe. Le module est **scindé** pour rester sous 15 fichiers :
- **3a (ce module)** : relevés, correspondance, frais, écran web ;
- **3b** : SMS iPhone (Raccourci → mêmes lignes de relevé) ;
- **3c** : transferts avec frais.

**But** : plus aucune écriture de relevé oubliée ou comptée deux fois (cas réel : « restaurant 155 000 » noté, alors que le relevé disait 15 500 + 547 de frais). **Acteurs / droits** : Richard, sur ses propres comptes.

**Scénarios**
1. Nominal :
   - Compte Airtel Money → « Relevé » → je colle l'historique (SMS ou export) → « Analyser » ;
   - lignes rapprochées automatiquement : Digitech Africa 16 047 ↔ restaurant 15 500, avec 547 de frais ;
   - lignes à valider : plusieurs candidats, ou montant proche ;
   - lignes du relevé sans opération : Bamboo Pay 35 855 → « Créer l'opération » (catégorie suggérée) ou « Ignorer » ;
   - opérations sans ligne de relevé sur la période : mauvais compte ? espèces ?
2. « Valider » : les correspondances sont enregistrées ; chaque frais devient une dépense « Frais » liée ; les opérations rapprochées sont marquées pointées (`reconciled_at`, module 4) ; un récap s'affiche.
3. Recoller le même relevé : les lignes déjà importées (même référence `TID`, ou même date + montant + libellé) sont reconnues et non dupliquées.
- Erreurs :
  - format non reconnu → lignes signalées « non comprises » ;
  - compte d'autrui → 403 ;
  - ligne déjà rapprochée → 422 (`ALREADY_MATCHED`).

**Données** (migration réversible)
- `statement_lines` : `id`, `user_id`, `account_id`, `date`, `amount` (signé), `label`, `external_ref` (TID, unique par compte), `source` (sms|paste|csv), `transaction_id` (nullable), `fee_transaction_id` (nullable), `status` (pending|matched|created|ignored), soft delete. Le texte brut n'est **pas** conservé.

**API / écrans** : `POST /accounts/{id}/statement/preview` (analyse + propositions, rien d'enregistré) ; `POST /accounts/{id}/statement` (validation) ; web `Accounts/Statement.vue` ; endpoints miroirs dans l'API (Sanctum, throttle) pour le futur écran iPhone.

**Règles métier**
- RM-1 Correspondance automatique : même compte, même sens, date à ±1 jour ; montant égal, ou relevé = opération + frais, avec frais ≤ max(1 000 FCFA, 3 %). Une seule candidate requise ; sinon, « à valider ».
- RM-2 Les frais deviennent une dépense séparée, catégorie « Frais bancaires » (système), liée à la ligne de relevé ; jamais fondus dans l'opération.
- RM-3 Une opération n'est rapprochée qu'à une seule ligne de relevé, et réciproquement.
- RM-4 Une ligne sans opération n'est jamais créée automatiquement : « Créer » (suggestion du module 2 bis) ou « Ignorer », au choix.
- RM-5 Validation atomique (une transaction DB) ; les soldes ne bougent que par les opérations créées (frais, lignes « Créer »).

**Sécurité** : Policy du compte ; IDOR sur `transaction_id` ; texte limité (20 000 caractères, 500 lignes) ; texte brut jamais stocké ni journalisé (données financières) ; throttle sur l'aperçu.

**Tests prévus** :
- lecture des relevés (SMS Airtel déjà connus de `SmsParser`, CSV BGFI) ;
- correspondance (égal, avec frais, plafond de frais, ±1 jour, ambiguïté, unicité) ;
- validation (frais créés, `reconciled_at`, atomicité, rejeu sans doublon) ;
- endpoints 200/401/403/422.

**Décisions** : réutiliser `SmsParser` pour Airtel et BGFI ; correspondance par score simple (pas d'apprentissage) ; catégorie système « Frais bancaires » à UUID fixe, comme pour l'écart de rapprochement.

**Questions** (hypothèse par défaut)
1. Sous quelle forme avez-vous vos relevés ? → *Hypothèse : Airtel Money = texte des SMS copiés-collés (formats déjà reconnus) ; BGFI = CSV d'export e-banking (date ; libellé ; débit ; crédit). **Il me faudrait 3 à 5 lignes réelles anonymisées de chaque** pour fixer les formats.*
2. Faut-il une catégorie « Frais » unique ou une par fournisseur (Airtel, BGFI) ? → *Hypothèse : une seule catégorie système « Frais bancaires », le compte suffit à distinguer.*
3. Le découpage 3a / 3b / 3c vous convient-il ? → *Hypothèse : oui, et 3b (SMS iPhone) passe juste après.*

**Hors module** : Raccourci iOS et SMS automatiques (3b), transferts avec frais (3c), lecture de PDF ou de captures d'écran, connexion bancaire.

## Livré (3a)
- **Serveur** :
  - migrations réversibles `statement_lines` et catégorie système « Frais bancaires » (`Category::BANK_FEES_ID`), avec rollback testé ;
  - `StatementParser` (SMS Airtel et BGFI via `SmsParser`, CSV avec en-tête détecté) ;
  - `StatementMatcher` (RM-1, RM-3, opérations absentes) et `StatementService` (frais, création, pointage, atomicité).
- **API** : `POST /api/accounts/{id}/statement/preview` et `POST /api/accounts/{id}/statement`. **Web** : page `Accounts/Statement.vue` (direction A, accordéon), lien « Relevé » sur la page Comptes.
- **Tests** : 4 fichiers, 47 tests. Suite complète : 404 au vert.
- **Vérifié dans le navigateur** sur une instance locale jetable :
  - 5 SMS collés → 2 lignes rapprochées avec frais (547 et 450), 1 à valider (deux taxis), 1 sans opération (Bamboo), 1 non comprise, 1 opération absente du relevé (courses) ;
  - après validation, solde exact : 125 648 ;
  - le même relevé recollé ressort en « déjà importé ».
- **Écarts avec l'analyse** :
  - `SmsParser` corrigé : motif précis en premier, nom borné à une ligne ;
  - la page Budgets affiche « Frais bancaires », pour pouvoir lui fixer un plafond ;
  - le plan avait une ligne 3 cassée, réparée et découpée en 3a/3b/3c.
- **Revue de sécurité** :
  - point haut corrigé : le serveur revérifie chaque paire relevé ↔ opération (montant, ±1 jour, plafond de frais), recalcule les frais et refuse les opérations en attente comme les frais déjà créés ;
  - points moyens corrigés : index uniques sur l'opération et sur le frais liés ; rejeu sans référence refusé ;
  - points bas corrigés : seuls les doublons sont traduits en `ALREADY_MATCHED`, limite de requêtes sur l'envoi web, catégorie du bon sens, webhook borné (2 000 caractères).
- **Non vérifié** :
  - les vrais relevés BGFI, faute de lignes réelles : les en-têtes reconnus sont une hypothèse ;
  - MySQL réel (tests sous SQLite).
