# Design — saisie-rapide                         retenu : A (pavé plein écran) · 2026-11-10

**Conclusion** : un seul écran, avec le montant en haut et un pavé numérique maison en bas (zone du pouce). Les suggestions sont déjà sélectionnées et les deux boutons d'enregistrement se trouvent juste au-dessus du pavé. Cas nominal « Taxi 2 000, Transport » : 2 taps en plus des chiffres quand la suggestion est la bonne, 3 sinon.

```
┌───────────────────────────────┐
│ ✕        Nouvelle opération   │
│   [ Dépense | Revenu ]        │  segmented 44 pt
│          − 2 000 FCFA         │  40 pt, couleur + signe (jamais la couleur seule)
│ Récents  [Taxi·2 000] [Total·15 000] →   chips 44 pt, défilement horizontal
│ Catégorie [●Transport][Courses][Resto][Autres…]   Wrap, « Autres… » = CategoryPicker
│ Compte  ▸ Airtel Money        │  ligne 52 pt → AccountPicker
│ ▸ Plus de détails             │  bénéficiaire, date, note (replié)
│ [Enregistrer et nouvelle]     │  Outlined 52 pt
│ [        Enregistrer       ]  │  Elevated 52 pt
│ │ 1 │ 2 │ 3 │                 │
│ │ 4 │ 5 │ 6 │  touches ≥ 56 pt│
│ │ 7 │ 8 │ 9 │                 │
│ │000│ 0 │ ⌫ │                 │
└───────────────────────────────┘
```

**États** : *vide* : pas de Récents ; texte « Vos opérations récentes apparaîtront ici » ; puces = catégories du type, sans présélection. *Chargement* : le pavé reste utilisable ; indicateur dans le bouton pendant l'envoi. *Erreur* : montant 0 → boutons désactivés + « Saisissez un montant » ; échec de l'enregistrement → message, saisie conservée.

**Accessibilité** : touches annoncées (« 2 », « zéro zéro zéro », « Effacer ») ; montant en `liveRegion` ; puce = « Catégorie Transport, sélectionnée » ; récent = « Rejouer : Taxi, 2 000 francs CFA » ; cibles ≥ 44 pt ; puces en `Wrap` en grand Dynamic Type ; retour haptique léger sur les touches.

**Composants** : réutilisés : `TransactionTypeSelector`, `CategoryPicker` (feuille « Autres… »), `AccountPicker`. Nouveaux : `NumericKeypad`, `SuggestedCategoryChips`, `RecentEntriesRow`, section repliable. `AmountInput` et `QuickAmountButtons` ne sont plus utilisés par ce formulaire.

**Widget iOS moyen** : deux pastilles `Link` en bas à droite, « + Dépense » (`minus.circle.fill`) et « + Revenu » (`plus.circle.fill`) → `morex://add?type=expense|income`, avec un libellé d'accessibilité. « MAJ » passe en petit sous le solde. Le widget petit est inchangé.

Écartées : B (le clavier système masque la moitié de l'écran, sans touche « 000 ») et C (deux parcours, hors du périmètre validé).
