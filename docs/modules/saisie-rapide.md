# Module saisie-rapide                                statut : livré · 2026-11-10

**Conclusion** : sur le mobile seulement, sans migration ni changement d'API. On montre d'abord le montant, la catégorie et le compte se remplissent seuls à partir de l'historique, un tap rejoue une opération récente, et le widget iOS ouvre directement la saisie. Objectif : une dépense courante en moins de 10 secondes et 3 gestes.

**But** : réduire la saisie d'une dépense courante à « montant → catégorie suggérée → Enregistrer ». **Acteurs** : Richard sur iPhone, en ligne ou hors ligne (le flux de synchro est inchangé).

**Scénarios**
1. Nominal : tap sur « + Dépense » (widget ou app) → pavé numérique plein écran → saisie du montant → la catégorie et le compte les plus probables sont présélectionnés → « Enregistrer ». Bénéficiaire, date et note restent repliés sous « Plus de détails ».
2. Rejouer : dans « Récents », un tap sur « Taxi · Transport · 2 000 » préremplit le formulaire, qui reste modifiable avant l'enregistrement.
3. Suggestion par bénéficiaire : à la saisie de « Total », la catégorie et le compte les plus utilisés avec ce bénéficiaire sont proposés, sans écraser un choix déjà fait à la main.
4. Enchaîner : « Enregistrer et nouvelle » garde le compte et la date, puis remet le montant à zéro.
- Erreurs : montant à 0 → bouton désactivé (`AMOUNT_REQUIRED`) ; aucun historique → les suggestions tombent sur la catégorie la plus fréquente du type, puis sur aucune ; lien profond invalide (`morex://add?type=xyz`) → ouverture du formulaire en dépense par défaut, sans plantage.

**Données** : lecture seule de l'historique Drift (transactions, catégories, comptes). Aucune nouvelle table : les « Récents » sont calculés à partir de l'historique, ce qui évite de toucher à la synchro.

**Écrans** : formulaire de transaction réorganisé (pavé, puces de catégories suggérées, section repliée) ; rangée « Récents » ; widget iOS moyen avec deux liens profonds « + Dépense » et « + Revenu » (`morex://add?type=expense|income`) ; route go_router associée.

**Règles métier**
- RM-1 : suggestion de catégorie = la plus fréquente pour ce bénéficiaire sur 90 jours ; à défaut, la plus fréquente du type sur 30 jours.
- RM-2 : une suggestion ne remplace jamais un choix fait à la main dans la saisie en cours.
- RM-3 : « Récents » = les 5 dernières combinaisons distinctes (bénéficiaire, catégorie, compte, montant) du type choisi.
- RM-4 : montants en FCFA entiers ; la catégorie et le compte restent obligatoires.

**Sécurité** : paramètres du lien profond limités à une liste blanche (`type` ∈ {expense, income}) ; aucune donnée financière dans l'URL ; le widget n'affiche que ce qu'il affiche déjà.

**Tests prévus** : unitaires `TransactionSuggestionService` (RM-1 à RM-3, historique vide) ; bloc `TransactionFormBloc` (préremplissage par un récent, suggestion qui n'écrase pas un choix manuel, « Enregistrer et nouvelle ») ; widget (bouton désactivé à 0, section repliée) ; analyse du lien profond (type valide, invalide, absent).

**Décisions** : pas de nouvelle dépendance ; les raccourcis de l'icône iOS (`quick_actions`) sont écartés. Les suggestions sont calculées localement par des requêtes Drift agrégées, ce qui marche hors ligne.

**Questions** (hypothèse retenue par défaut)
1. Écran de saisie : faut-il garder un écran unique réorganisé, ou en ajouter un second dédié à la saisie express ? → *Hypothèse : un écran unique réorganisé (moins de code, un seul parcours).*
2. Les favoris épinglés à la main sont-ils utiles en plus des « Récents » automatiques ? → *Hypothèse : non pour l'instant ; ils demanderaient une table synchronisée, donc un autre module.*
3. Faut-il des raccourcis Siri ou dans l'app Raccourcis pour saisir à la voix ? → *Hypothèse : non dans ce module.*

**Hors module** : saisie sur le web, favoris synchronisés, Siri et App Intents, saisie par SMS (module 3), dépenses fractionnées.

## Livré
- **Code (morex_mobile, 15 commits)** :
  - `TransactionSuggestionService` : RM-1 et RM-3, classement des puces ;
  - `NumericKeypad` (avec « 000 », plafond de 999 999 999) ;
  - `SuggestedCategoryChips`, `RecentEntriesRow`, `QuickEntryActions` ;
  - `TransactionFormBloc` : suggestions, Récents, « Enregistrer et nouvelle », RM-2 ;
  - écran réorganisé (direction A) ;
  - `DeepLinks` + route : un lien reçu au démarrage à froid passe par l'écran de démarrage, puis seulement par la liste blanche ;
  - schéma `morex` dans `Info.plist` ; pastilles « Dépense / Revenu » du widget iOS moyen.
- **Tests** : 37 nouveaux (service, pavé, bloc, composants, liens profonds). Suite : 126 au vert, 4 échecs déjà présents sur `main` (tests périmés, module 7). `flutter analyze` : aucun nouveau problème.
- **Écarts avec l'analyse** : URL du widget `morex://app/transactions/new?type=…` au lieu de `morex://add?type=…` (go_router ne lit que le chemin) ; `AmountInput` et `QuickAmountButtons` supprimés (morts).
- **Revue de code** :
  - point haut corrigé : pavé masqué quand le clavier iOS est ouvert ;
  - moyens corrigés : messages empilés, erreurs successives, suggestion qui « clignote » à la frappe, compte suggéré qui ne revenait pas au compte par défaut ;
  - points bas corrigés : historique rechargé après « Enregistrer et nouvelle », échec de l'historique toléré, tri stable des Récents.
- **Risque accepté** : app déjà lancée, un lien `morex://` arrive à go_router sans liste blanche. L'écran de verrouillage couvre toutes les pages et aucune n'agit à l'ouverture. Le filtrer demanderait un plugin.
- **Non vérifié** : compilation iOS et essai sur iPhone. Le projet ne compile plus avec Xcode 27, car sa cible minimale est iOS 13 alors qu'il faut iOS 15 (problème déjà présent sur `main`, voir la PR). Le parcours réel (widget → saisie) reste à tester sur l'iPhone.
