# Design — achats-prevus (5d)                retenu : A (liste du cycle + bloc dans la saisie) · 2026-11-11

**Conclusion** : une page « Achats prévus » en liste unique groupée par statut, avec un ajout en ligne (comme « Contribuer » dans Objectifs). Dans la saisie d'une dépense de 30 000 ou plus, un bloc apparaît sous la catégorie et propose l'association ou l'avertissement hors liste. Une carte sur le tableau de bord résume le cycle.

**Page** (`/purchases`) :
```
Achats prévus                      [+ Prévoir]
Prévu 140 000 · Fait 85 000 · 2/4   [=====>   ] 61 %
EN RETARD   Casque      28 000   [Acheté] [Annuler]
CE CYCLE    Chaussures  45 000   [Acheté] [Annuler]
            Livre       12 000   ✓ Fait 03/11
HORS LISTE  Écran       60 000   Acheté hors liste, assumé
À VENIR     Sac         30 000   dès le 25/11
```
- Le formulaire d'ajout déplié comprend : nom, montant, catégorie, puis une période « Ce cycle » ou « Cycle suivant » (radios).
- « Acheté » ouvre `/transactions/create`, pré-rempli et lié à l'achat.
- « Annuler » demande une confirmation.
- État vide : « Aucun achat prévu ce cycle », avec un bouton « Prévoir un achat ».

**Bloc dans la saisie** (Transactions/Create) :
- Il apparaît quand le type est dépense et le montant de 30 000 ou plus. Il est recalculé au `blur` du montant et au changement de catégorie.
- Le conteneur porte `role="status"` : il est annoncé sans voler le focus.
- S'il existe un ou plusieurs candidats : « C'est l'achat prévu “Chaussures” ? », avec des radios dans un `fieldset` et l'option « Aucun d'eux ».
- Sinon, l'avertissement hors liste (ambre, icône et texte) propose :
  - [Reporter au cycle suivant] : rien n'est enregistré, l'achat est ajouté à la liste ;
  - [Acheter quand même] : le hors liste est confirmé et la dépense est enregistrée.
- Si l'utilisateur enregistre sans avoir fait de choix, rien n'est envoyé : le focus va sur le titre du bloc. « Enregistrer » n'est jamais désactivé.

**Carte du tableau de bord** (`id="achats"`), dans le style de BudgetCapCard :
- « 2/4 faits », barre de progression, « 85 000 / 140 000 · reste 55 000 » ;
- « 1 hors liste · 60 000 » et « 1 en retard », chacun avec une icône ;
- état vide : « Aucun achat prévu ce cycle. Prévoir un achat ».

**Accessibilité** :
- statut toujours donné par une icône et un mot ;
- cibles de 44 px ;
- champs de 16 px, pour éviter le zoom iOS ;
- `progressbar` avec un libellé complet.

Écartée : B (onglets, page d'ajout séparée, feuille à l'envoi). L'information y arrive trop tard et le parcours demande plus de clics.
