# Design — bilan-mensuel (8)                  retenu : A (récit vertical en cartes) · 2026-11-11

**Conclusion** : la page « Bilan du cycle » est une seule colonne de cartes, dans l'ordre de lecture : épargne, discrétionnaire, catégories, à part, proposition. Le bouton « Lancer le cycle suivant » est fixé en bas de l'écran et ouvre la fenêtre de lancement de Cycles, déjà remplie.

```
‹ Cycles   Bilan · 25/10 → 24/11   [Clôturé | En cours]
ÉPARGNE            ✓ Cible atteinte / ⚠ Sous la cible
308 000 FCFA · 29 % / 25 %   [barre]
Revenus 1 050 000 · Dépenses perso 742 000 / 800 000
DISCRÉTIONNAIRE   ⚠ Dépassé   412 000 / 350 000 (+62 000)
CATÉGORIES (dépassements d'abord)
⚠ Sorties/Loisirs  299 000 / 170 000 · Dépassé de 129 000
✓ Alimentation      98 000 / 120 000 · Reste 22 000   [Tout afficher (N)]
À PART   Pro · Transit net · Hors liste (n · total)
PROPOSITION · cycle suivant
Revenu attendu 1 020 000
Sorties/Loisirs 170 000 · Dépassé 2 cycles sur 3 : plafond maintenu
⚠ Plafonds 905 000 > revenu − épargne visée 765 000
[ Lancer le cycle suivant ]   (barre fixe en bas, 48 px)
```

**États**
- Cycle en cours : bandeau « Chiffres provisoires au jj/mm ».
- Cycle clôturé ancien : bouton secondaire, avec la mention « Un cycle plus récent existe déjà ».
- Pas d'historique : « Pas assez d'historique pour proposer des plafonds » ; la fenêtre s'ouvre sans préremplissage.
- Tout dans les clous : « Aucun dépassement », avec une coche.
- Catégories dans les clous : repliées derrière « Tout afficher (N) ».

**Pièces communes**
- Page Cycles : lien « Bilan » sur chaque cycle et dans la carte du cycle actif (44 px).
- Fenêtre de lancement : préremplie depuis la proposition (`?proposal=1`), avec la raison de chaque plafond modifié. Champs et croix de fermeture en 44 px.
- Formulaire catégorie : case « Dépense discrétionnaire » sous `NatureField`, toute la ligne cliquable, avec une aide.

**Accessibilité** :
- statut toujours donné par une icône et un mot ;
- `tabular-nums` sur les montants ;
- lignes sur deux niveaux, pas de tableau ;
- `progressbar` avec un libellé complet ;
- marge basse dégagée pour la barre fixe (`safe-area`).

Écartée : B (résumé et onglets). La proposition, qui est le but de la page, y serait cachée par défaut.
