# Module bilan-mensuel (8)                          statut : en cours · 2026-11-11

**Conclusion** : une page **Bilan du cycle** remplace l'export CSV et l'analyse faite à côté. Elle montre, pour le cycle en cours ou un cycle clôturé :
- revenus comptés ;
- dépenses perso face au budget, catégorie par catégorie (écart, dépassements en tête) ;
- dépenses **discrétionnaires** agrégées ;
- **épargne** (montant, taux face à la cible de 25 %, versement de l'objectif 2 M) ;
- pro, transit net et achats hors liste.

Le bilan se termine par une **proposition de cycle suivant** (revenu attendu et plafonds par catégorie). Le bouton « Lancer le cycle suivant » ouvre la fenêtre de lancement existante, déjà remplie : vous ajustez puis validez, sans CSV.

**But** : boucler un cycle en 5 minutes dans l'app. **Acteurs / droits** : Richard, sur ses propres cycles (Policy).

**Scénarios**
1. Cycles → « Bilan » sur le cycle en cours ou un cycle clôturé.
2. Lecture :
   - « Revenus 1 050 000 · Dépenses perso 742 000 / 800 000 · Épargne 308 000 (29 % ✓ cible 25 %) » ;
   - « Discrétionnaire 412 000 / 350 000 ⚠ » ;
   - « Sorties/Loisirs 299 000 / 170 000 (+129 000) » en tête des dépassements ;
   - « 2 achats hors liste · 95 000 ».
3. « Proposition pour le cycle suivant » : revenu attendu et plafond par catégorie. Chaque ligne est expliquée (« dépassé 2 cycles sur 3 : plafond maintenu », « jamais plafonnée : 45 000 proposé d'après la moyenne »).
4. « Lancer le cycle suivant » : la fenêtre de lancement s'ouvre déjà remplie. Vous ajustez, puis validez (route existante).
- Erreurs :
  - cycle d'autrui → 403 ;
  - aucun cycle → bilan du mois civil, sans proposition de lancement ;
  - plafond négatif → 422 (inchangé).

**Données** (migration réversible) : `categories.discretionary` (booléen, faux par défaut), réglable dans le formulaire catégorie. Le reste est calculé à la lecture (filtres `counted()` et `personal()` des modules 5c et 5d). Aucune donnée n'est figée.

**API / écrans**
- `GET /budget-cycles/{cycle}/bilan` (web, auth, Policy) : page `BudgetCycles/Bilan`, avec un lien « Bilan » dans Cycles.
- Formulaire catégorie : case « Dépense discrétionnaire ».

**Règles métier**
- RM-1 Les montants viennent des règles existantes : revenus via `counted()`, dépenses perso via `personal()`, budget perso via `personalBudgetFor()`. Le pro, le transit et le hors liste s'affichent à part.
- RM-2 Épargne = revenus − toutes les dépenses comptées (pro compris). Taux = épargne ÷ revenus. Le versement de l'objectif suivi vient du module 5a.
- RM-3 Proposition de plafond par catégorie courante, sur les 3 derniers cycles :
  - dépassé 2 fois ou plus : le plafond est **maintenu** et la ligne signalée ;
  - toujours sous 70 % : baisse proposée à la moyenne réalisée, arrondie au millier supérieur ;
  - sans plafond mais avec des dépenses : la moyenne est proposée ;
  - sinon, le plafond est inchangé.
- RM-4 Revenu attendu = moyenne des revenus comptés des 3 derniers cycles. Si une épargne visée existe, la proposition signale le cas où la somme des plafonds dépasse revenu − épargne visée.
- RM-5 La proposition ne s'applique qu'au lancement, après validation de Richard. Rien n'est modifié en lisant le bilan.

**Sécurité** :
- Policy sur le cycle ; aucune donnée d'un autre utilisateur.
- **Correctif voisin** : le lancement de cycle modifie aujourd'hui le plafond des catégories **système**, partagées entre utilisateurs (`orWhereNull('user_id')`). Il sera limité aux catégories de l'utilisateur.

**Tests prévus** :
- service : totaux, discrétionnaire, épargne, chacune des règles de proposition, cycle clôturé ou en cours, mois civil ;
- endpoint 200, 403, 302 (invité) ;
- lancement : catégorie système intacte ;
- case discrétionnaire (422 sur une valeur invalide).

**Décisions** : calcul à la lecture (pas de nouveau snapshot) ; la fenêtre de lancement existante est réutilisée ; aucune dépendance nouvelle.

**Questions** (hypothèse par défaut)
1. Quelles catégories sont discrétionnaires ? → *Hypothèse : vous les cochez (Sorties/Loisirs, Restaurants, Événements…). Le plafond combiné vaut la somme de leurs plafonds, ou 350 000 si vous en fixez un dans le bilan plus tard.*
2. Les règles de proposition (2 dépassements sur 3 : maintenu ; sous 70 % : baisse) vous conviennent-elles ? → *Hypothèse : oui, en constantes.*
3. Faut-il garder l'import CSV de budgets ? → *Hypothèse : oui, en option secondaire, pour ne rien casser.*

**Hors module** : bilan sur l'iPhone, export PDF, plafond combiné saisi à part, comparaison d'une année sur l'autre.
