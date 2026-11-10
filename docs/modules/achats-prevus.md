# Module achats-prevus (5d)                         statut : en cours · 2026-11-11

**Conclusion** : une **liste d'achats prévus** par cycle (nom, montant estimé, catégorie). La règle « tout achat de plus de 30 000 hors liste attend le cycle suivant » devient visible à deux endroits :
- **à la saisie web** : une dépense de 30 000 ou plus sans achat prévu associé déclenche un avertissement, avec deux choix : « Reporter au cycle suivant » (l'achat est ajouté à la liste du cycle suivant, rien n'est dépensé) ou « Acheter quand même » ;
- **sur le tableau de bord** : une carte « Achats prévus » montre le prévu, le réalisé et les achats hors liste du cycle.

Rien n'est bloqué : l'app rend l'écart visible, elle n'interdit pas.

**But** : tenir la règle d'achat sans y penser. Exemple de septembre : 3 achats impulsifs de plus de 30 000, pour 145 000 au total. **Acteurs / droits** : Richard, sur ses propres achats prévus (Policy).

**Scénarios**
1. Achats → « + Prévoir » : « Chaussures » · 45 000 · Habillement · pour ce cycle.
2. Saisie d'une dépense de 45 000 en Habillement : le formulaire propose « C'est l'achat prévu “Chaussures” ? ». Après validation, l'achat passe à *Acheté* et il est lié à l'opération.
3. Saisie d'une dépense de 60 000 sans achat prévu : avertissement « Hors liste : la règle dit d'attendre le cycle suivant ».
   - « Reporter » : l'achat est ajouté pour le cycle suivant, et la dépense n'est pas enregistrée.
   - « Acheter quand même » : la dépense est enregistrée et comptée comme hors liste.
4. Tableau de bord : « Achats prévus · cycle : 2/4 faits · 85 000 / 140 000 · 1 hors liste (60 000) ».
- Erreurs :
  - montant négatif ou nul → 422 ;
  - achat prévu d'autrui → 403 ;
  - opération déjà liée à un autre achat → 422 (`ALREADY_LINKED`).

**Données** (migration réversible) : `planned_purchases`, avec soft delete. Champs :
- `id`, `user_id`, `name`, `estimated_amount` ;
- `category_id` (nullable) ;
- `planned_for` (date : début de la période où l'achat est permis) ;
- `status` (`planned`, `bought` ou `cancelled`) ;
- `transaction_id` (nullable, unique) ;
- `unplanned` (booléen : acheté hors liste, assumé).

**API / écrans** (web)
- Page « Achats prévus » : liste du cycle, à venir, faits ; ajout, modification, annulation.
- Formulaire « Nouvelle transaction » : avertissement et association à un achat prévu.
- Carte du tableau de bord.
- Routes `purchases.*` (auth, Policy). L'API mobile vient avec l'écran iPhone (hors module).

**Règles métier**
- RM-1 Seuil : 30 000 FCFA (constante), sur les dépenses comptées (`counted()`) de nature courante. Le transit et le pro ne sont pas concernés.
- RM-2 Période : le cycle actif (sinon le mois civil), comme les plafonds. « Cycle suivant » = lendemain de la fin du cycle, ou le 1er du mois suivant si le cycle n'a pas de fin connue.
- RM-3 Une dépense de 30 000 ou plus est « hors liste » si aucun achat prévu de la période ne lui est lié. « Acheter quand même » crée un achat `bought` et `unplanned` lié à la dépense, pour garder la trace.
- RM-4 Association : proposée pour un achat `planned` de la période ou d'une période antérieure, de même catégorie (ou sans catégorie). Un achat ne se lie qu'à une opération, et inversement.
- RM-5 Un achat non fait à la fin du cycle reste `planned` et apparaît « en retard » au cycle suivant. Pas de report automatique.

**Sécurité** : Form Request (`integer`, `min:1`, borné à 10⁹ ; `name` ≤ 120) ; catégorie et opération appartenant à l'utilisateur ; Policy ; aucun log de montant.

**Tests prévus** :
- service : hors liste (seuil, nature, période), association, report au cycle suivant ;
- endpoints 200, 403, 422 ;
- saisie avec `planned_purchase_id` et avec « acheter quand même » ;
- carte du tableau de bord.

**Décisions** : le lien est porté par l'achat prévu (`transaction_id`), pour ne pas toucher au schéma des opérations ni à la synchro ; aucune dépendance nouvelle.

**Questions** (hypothèse par défaut)
1. Les charges fixes de 30 000 ou plus (loyer, factures, scolarité) sont-elles concernées ? → *Hypothèse : non. Les dépenses issues d'une récurrence seront exclues dès que le module 6 les marquera. D'ici là, un clic « C'était prévu » les associe.*
2. Le seuil de 30 000 est-il fixe ? → *Hypothèse : oui, en constante.*
3. Faut-il l'avertissement sur l'iPhone ? → *Hypothèse : oui, mais avec l'écran mobile, dans un module à part (API comprise).*

**Hors module** : API et écran iPhone, report automatique, liste partagée, prix suivis en ligne.
