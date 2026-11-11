# Module saisie-lot-mobile (2 ter)                  statut : en cours · 2026-11-12

**Conclusion** : l'écran de **saisie en lot** arrive sur l'iPhone. Je colle mes notes du jour (« 1500 taxi », « 22500+12000 night club »). Le serveur les analyse avec l'API du module 2 bis (`POST /api/transactions/notes/preview`, déjà livrée). Je vérifie les lignes proposées (montant, catégorie obligatoire, compte, date), puis j'enregistre. Les opérations sont créées **localement**, par le même cas d'usage que la saisie unitaire, puis partent au serveur par la synchro habituelle : les soldes, les plafonds et les achats prévus suivent comme pour toute saisie.

**But** : saisir une journée de notes en 30 secondes depuis l'iPhone, sans passer par le web. **Acteurs / droits** : Richard, sur ses propres comptes et catégories.

**Scénarios**
1. Transactions → « Saisie en lot » → je colle ou tape les notes. La date de référence est aujourd'hui, modifiable ; le compte par défaut est choisi.
2. « Analyser » (connexion requise) : liste des lignes, avec montant, libellé, catégorie et compte suggérés, et l'extrait de la note d'origine. Les lignes illisibles sont signalées, sans être perdues.
3. J'ajuste une ligne (catégorie obligatoire, compte, montant, date), j'en retire une, puis « Enregistrer N opérations » (total affiché).
4. Les opérations apparaissent tout de suite dans la liste, puis la synchro les envoie.
- Erreurs :
  - hors connexion à l'analyse : message, notes conservées ;
  - texte vide ou trop long : message du serveur (422) ;
  - ligne sans catégorie : bouton bloqué, ligne signalée.

**Données** : aucune migration (API et tables existantes).

**API / écrans** (mobile)
- Appel `POST /api/transactions/notes/preview`, avec les paramètres et les limites du module 2 bis.
- Nouvelle page `BatchEntryPage` (route `/transactions/batch`) et `BatchEntryCubit`.
- Bouton « Saisie en lot » sur la liste des transactions.

**Règles métier**
- RM-1 L'analyse est faite par le serveur (même moteur que le web) ; rien n'est enregistré à l'analyse.
- RM-2 L'enregistrement crée chaque opération localement, avec `CreateTransactionUseCase` (catégorie obligatoire), puis la synchro les pousse. Les soldes bougent par le chemin habituel.
- RM-3 Les catégories d'écart et les catégories en transit sont proposées comme sur le web ; une catégorie d'écart est refusée par le serveur à la synchro (module 7a).

**Sécurité** : jeton existant ; validation serveur (limites du 2 bis) ; notes jamais journalisées.

**Tests prévus** :
- cubit : analyse réussie, hors connexion, 422, ligne modifiée et retirée, enregistrement (une création par ligne), catégorie manquante ;
- widget : liste et bouton bloqué sans catégorie.

**Décisions** : enregistrement local plutôt qu'un endpoint d'enregistrement en lot (cohérent avec l'app hors connexion) ; design A du web (`docs/design/saisie-lot.md`) adapté à l'iPhone, sans nouvelle étape de design (question 2).

**Questions** (hypothèse par défaut)
1. Enregistrer localement puis synchroniser (plutôt qu'un envoi direct au serveur) ? → *Hypothèse : oui, comme toute saisie mobile.*
2. Reprendre le design A du web (liste éditable, catégorie obligatoire) sans nouvelle proposition ? → *Hypothèse : oui.*
3. Analyse hors connexion (parseur embarqué) ? → *Hypothèse : non, le serveur reste l'unique moteur.*

**Hors module** : analyse hors connexion, dictée vocale, saisie en lot sur le widget iOS.
