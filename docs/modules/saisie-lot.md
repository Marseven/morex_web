# Module saisie-lot                                   statut : analysé · 2026-11-10

**Conclusion** : je colle mes notes d'une ou plusieurs journées telles que je les écris, et Morex les transforme en opérations à vérifier, avec catégorie et compte devinés d'après mon historique. J'enregistre le tout en un clic. Ce module couvre **le serveur (analyse + API) et l'écran web**. L'écran iPhone réutilisera la même API dans un module suivant. Aucune migration.

**But** : remplacer la génération de CSV par un assistant externe. **Acteurs / droits** : Richard, sur ses propres comptes et catégories.

**Scénarios**
1. Nominal : Transactions → « Saisie en lot » → je colle :
   ```
   Vendredi 16 oct
   1500 taxi
   25000+7000+12000 night club
   Entrée :
   110000 - Freelance
   ```
   → « Analyser » → un tableau de 5 lignes, datées du 16/10 : taxi en Transport, 3 lignes Night Club en Sorties/Loisirs, Freelance en revenu. Chaque ligne a un compte proposé.
2. Je corrige une catégorie ou un compte dans le tableau. Les lignes à compléter (catégorie inconnue) sont signalées. « Enregistrer 5 opérations » → les soldes bougent par les événements habituels.
3. Plusieurs jours collés d'un coup : chaque en-tête de jour change la date des lignes qui suivent.
- Erreurs :
  - ligne illisible (« taxi » sans montant) → affichée en rouge « non comprise » et exclue de l'enregistrement, jamais ignorée en silence ;
  - texte trop long → 422 (`NOTES_TOO_LONG`) ;
  - catégorie ou compte d'un autre utilisateur → 422.

**Données** : aucune migration. La table `transactions` est inchangée. L'enregistrement réutilise l'import existant, étendu à un compte par ligne.

**API / écrans**
- `POST /api/transactions/notes/preview` (Sanctum, `throttle:30,1`) : `{ notes, reference_date, default_account_id }` → lignes analysées avec suggestions ; rien n'est enregistré.
- Web : page `Transactions/BatchEntry.vue` (analyse via la même logique) ; enregistrement par `POST /transactions/import`, où `rows.*.account_id` devient permis.

**Règles métier**
- RM-1 Montants : entiers FCFA. `k` vaut ×1 000 et `M` ×1 000 000 (`205k`, `1.5M`). `a+b+c libellé` donne **une opération par montant**, pour coller aux relevés du module 3.
- RM-2 Dates : un en-tête de jour (« Lundi 5 oct », « Jeudi 22 », « Samedi 17 octobre ») fixe la date des lignes suivantes. S'il manque le mois, on prend celui de l'en-tête précédent ou de la date de référence. Une date en fin de ligne (`- 11/09`) l'emporte. L'année est celle de la date de référence ; si le résultat tombe plus de 2 mois dans le futur, on prend l'année précédente.
- RM-3 Sens : « Entrée : » (avec ou sans accent) fait passer les lignes suivantes en revenus, jusqu'au prochain en-tête de jour. Le reste est en dépense.
- RM-4 Catégorie et compte suggérés : ceux le plus souvent utilisés sur 180 jours avec le même libellé (normalisé : casse, accents, espaces). Sinon, catégorie vide (« à compléter ») et compte par défaut choisi en haut de page. Les catégories d'écart de rapprochement ne sont jamais proposées.
- RM-5 Doublons : une ligne identique (même date, même montant, même libellé) déjà présente est signalée « déjà saisie ? », sans être bloquée.
- RM-6 Rien n'est enregistré avant la validation explicite.

**Sécurité** : texte limité à 20 000 caractères et 300 lignes ; analyse sans `eval` ni regex catastrophique (une ligne à la fois, motifs ancrés) ; catégories et comptes revalidés à l'enregistrement (`OwnedOrSystemCategory`, compte de l'utilisateur) ; notes jamais journalisées.

**Tests prévus** :
- unitaires de l'analyse (montants `k`/`M`/`+`, en-têtes de jour, mois manquant, date en ligne, section Entrée, ligne illisible, passage d'année) ;
- suggestions (historique, libellé normalisé, repli, écart exclu) ;
- API preview (200, 401, 422) ;
- import étendu (compte par ligne, compte d'autrui → 422) ;
- page web (rendu).

**Décisions** : analyse côté serveur (une seule logique pour le web et le futur écran iPhone) ; pas de dictionnaire de mots-clés figé, seulement l'historique de Richard, qui s'améliore à l'usage.

**Questions** (hypothèse par défaut)
1. `22500+12000 night club` : faut-il une opération par montant ou une seule de 34 500 ? → *Hypothèse : une par montant (rapprochement relevé par relevé).*
2. Faut-il un compte par défaut pour les notes espèces ? → *Hypothèse : le compte choisi en haut de page (pré-rempli avec le compte par défaut), écrasé par la suggestion d'historique quand elle existe.*
3. L'écran iPhone fait-il partie de ce module ? → *Hypothèse : non, il fera l'objet d'un module « saisie-lot-mobile » ajouté au plan, qui appellera la même API.*

**Hors module** : écran iPhone, transferts et retraits dans les notes (module 3), dictée vocale, import de fichiers.
