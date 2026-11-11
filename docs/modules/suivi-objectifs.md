# Module suivi-objectifs (5a : objectif d'épargne réel)    statut : livré · 2026-11-11

**Conclusion** : un objectif peut être **suivi sur un compte** (Compte Epargne). Sa progression se calcule alors seule à partir du solde de ce compte : versements et transferts entrants comptent, et les retraits (les 700 000 repartis vers le Courant, par exemple) se déduisent. Fini les « contributions » saisies à la main et l'écart 390 000 / 3,18 M. S'y ajoutent le **versement du mois** (300 000 attendus, versé ou non) et la **projection** au 31/12. Pour rester sous 15 fichiers, le module est scindé :
- **5a** : ce module ;
- **5b** : plafonds et alertes ;
- **5c** : tontines et remboursements en transit, séparation pro/perso ;
- **5d** : achats prévus et règle hors liste.

**But** : savoir d'un coup d'œil, et juste, où en est « Épargne Investissement 2 M au 31/12 ». **Acteurs / droits** : Richard, sur ses propres objectifs et comptes (`GoalPolicy`).

**Scénarios**
1. Lier :
   - Objectifs → « Épargne Investissement » → « Suivre sur un compte » → Compte Epargne ;
   - montant de départ pré-rempli avec la progression actuelle, modifiable (par exemple 990 000 au 1er septembre) → enregistrer ;
   - la progression devient automatique.
2. Suivre :
   - après un transfert de 300 000 vers le Compte Epargne, la carte du tableau de bord affiche « 1 590 000 / 2 000 000 (80 %) · ce mois-ci : 300 000 / 300 000 ✓ · rythme suffisant pour le 31/12 » ;
   - après un retrait de 700 000, la progression baisse d'autant et la carte passe à « en retard : 137 000 par mois à verser ».
3. Délier : la progression redevient manuelle, figée à sa dernière valeur calculée.
- Erreurs :
  - compte d'un autre utilisateur → 422 ;
  - objectif d'autrui → 403 ;
  - montant de départ négatif ou non entier → 422 ;
  - « Contribuer » sur un objectif suivi → 422 (`TRACKED_GOAL`).

**Données** (migration réversible) : `goals` + `tracked_since` (date), `tracked_start_amount` (bigint), `tracked_account_balance` (bigint, solde du compte au moment du lien), `monthly_target` (bigint, nullable). Le champ `account_id` existant devient le compte suivi.

**API / écrans**
- `POST /goals/{id}/track` (`account_id`, `start_amount`, `monthly_target`) et `DELETE /goals/{id}/track`, en web et en API (Sanctum, throttle) ;
- la Resource et la synchro renvoient la progression calculée dans `current_amount` (le mobile la reçoit sans changement) ;
- carte « Objectif » sur le tableau de bord ; formulaire « Suivre sur un compte » sur la fiche objectif.

**Règles métier**
- RM-1 Progression suivie = `tracked_start_amount + (solde actuel du compte − tracked_account_balance)`, jamais négative. Un objectif non suivi garde sa progression manuelle.
- RM-2 Versement du mois = variation du solde du compte suivi depuis le début du cycle budgétaire actif (mois civil s'il n'y a pas de cycle), lue dans `balance_history`. Comparé à `monthly_target` : « atteint », « reste X » ou, après le 10 du cycle, « en retard ».
- RM-3 Projection = reste ÷ mois restants jusqu'à `target_date` (au moins 1) ; « rythme suffisant » si `monthly_target` ≥ ce montant.
- RM-4 Objectif atteint (progression ≥ cible) → badge « Atteint » ; pas de changement de statut automatique silencieux.
- RM-5 Sur un objectif suivi, « Contribuer » est refusé, et une valeur de `current_amount` poussée par la synchro est ignorée (le calcul fait foi).

**Sécurité** : `GoalPolicy` sur `track` et `untrack` ; compte vérifié comme appartenant à l'utilisateur ; Form Request (`integer`, `min:0`, borné à 10¹²) ; aucun nouveau log.

**Tests prévus** : service (progression après transfert entrant ou sortant, plancher à 0, versement du cycle, projection, mois civil sans cycle) ; endpoints `track`/`untrack` (200, 403, 422) ; contribution refusée ; synchro (progression calculée renvoyée, valeur poussée ignorée) ; carte du tableau de bord.

**Décisions** : calcul à la lecture (pas de recalcul dans `adjustBalance`), à partir du solde du compte et de `balance_history` ; pas de nouvelle dépendance.

**Questions** (hypothèse par défaut)
1. Le découpage 5a / 5b / 5c / 5d vous convient-il ? → *Hypothèse : oui ; 5b (plafonds et alertes) juste après.*
2. Quel point de départ ? → *Hypothèse : montant saisi au moment du lien (par défaut la progression actuelle), associé au solde du Compte Epargne à cet instant.*
3. Le versement du mois se mesure-t-il sur le cycle budgétaire (de salaire à salaire) ou sur le mois civil ? → *Hypothèse : sur le cycle actif, sinon sur le mois civil.*

**Hors module** : plafonds et alertes (5b), tontines et pro/perso (5c), achats prévus (5d), écran mobile dédié (la carte arrive par la synchro existante).

## Livré (5a)
- **Serveur** :
  - migration réversible (`tracked_since`, `tracked_start_amount`, `tracked_account_balance`, `monthly_target`), rollback testé ;
  - accesseur `Goal::current_amount` (RM-1, lit le compte par la relation) ;
  - `GoalProgressService` (lier, délier, versement du mois, projection, échéance dépassée) ;
  - `TrackGoalRequest`.
- **Routes** : `POST|DELETE /goals/{id}/track` (web) et `/api/goals/{id}/track` (API).
- **Synchro** :
  - les objectifs suivis non supprimés sont toujours renvoyés au pull ;
  - le compte et la progression d'un objectif suivi ne sont jamais modifiés par un push.
- **Web** :
  - carte `SavingsGoalCard` sur le tableau de bord (direction A) ;
  - bloc « Suivre sur un compte » sur la fiche objectif, qui remplace le sélecteur « Compte associé » ;
  - « Suivi automatique » à la place de « + Ajouter » dans la liste.
- **Tests** : `GoalTrackingTest`, 21 tests. Suite complète : 424 au vert.
- **Vérifié dans le navigateur** sur une instance locale jetable :
  - lien avec un départ de 1 590 000 et un versement visé de 300 000 ;
  - après un virement de 300 000, la carte affiche 1 890 000 / 2 000 000, « Ce mois-ci 300 000 / 300 000 ✓ Atteint » et « Rythme suffisant pour le 31/12 ».
- **Correctifs voisins** :
  - le compte d'un objectif est maintenant limité à son propriétaire (`store`/`update` web acceptaient n'importe quel compte, ce qui aurait permis de lire un solde étranger via le suivi) ;
  - `balance_history.created_at` est posé par l'application (horloge cohérente).
- **Revue de code**, points hauts corrigés : changement de compte d'un objectif suivi par la synchro ; une requête par lecture de la progression.
- **Revue de code**, points moyens corrigés : objectifs supprimés renvoyés sans fin ; versement du mois compté depuis le début du suivi ; tests 403 et validation.
- **Revue de code**, points bas corrigés : échéance dépassée signalée ; `forceCreate` ; typage.
- **Décision maintenue (RM-4)** : pas de passage automatique au statut `completed`. La carte affiche « Atteint » ; le statut reste à changer par Richard.
- **Non vérifié** : MySQL réel ; comportement de l'app iPhone face aux objectifs suivis renvoyés à chaque pull (attendu : simple mise à jour).
