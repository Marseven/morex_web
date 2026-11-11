# Design — transferts-frais (3c)                  retenu : A (champ « Frais » visible, récapitulatif en direct) · 2026-11-12

**Conclusion** : dans Nouveau et Modifier, le champ « Frais » est toujours visible, pré-rempli avec les frais du dernier transfert du même couple de comptes. Un bouton « Sans frais » le remet à 0. Un récapitulatif montre en direct l'effet sur les deux soldes.

```
Montant (FCFA)  [ 50 000 ]
Depuis [Airtel Money ▾]   Vers [Portefeuille ▾]
Frais (FCFA)    [ 500 ]
  Repris du dernier transfert Airtel Money → Portefeuille. Modifiable.   [Sans frais]
Effet sur les soldes
  Airtel Money  −50 500 · Portefeuille  +50 000
  dont frais 500, classés « Frais bancaires »
```

**États**
- Sans frais : aide « Aucun frais enregistré pour ce couple » ; le récapitulatif n'a pas de ligne de frais.
- Pré-rempli : changer de compte recalcule les frais, tant qu'ils n'ont pas été modifiés à la main.
- Erreur : `aria-invalid`, bordure et icône, message sous le champ.
  - « Les frais ne peuvent pas être négatifs. »
  - « … dépasser le montant. »
- Modification, frais à 0 : « Avec 0, la dépense « Frais bancaires » liée sera supprimée. »

**Liste** : une ligne « frais 500 FCFA » sous la date, en couleur neutre et seulement si les frais dépassent 0.

**Import** :
- 6ᵉ colonne `frais`, facultative, pour que les anciens CSV restent valables ;
- champ « Frais » dans la revue de chaque ligne ;
- « Total des frais » sous le total transféré.

**Accessibilité** :
- `label for` ;
- `aria-describedby` vers l'aide et l'erreur ;
- `aria-live="polite"` sur le récapitulatif ;
- champs de 44 px et 16 px ;
- jamais la couleur seule.

Écartée : B (frais repliables, barre fixe en bas). Le repli cachait une valeur qui modifie un solde, et la barre fixe entrait en conflit avec le clavier iOS.
