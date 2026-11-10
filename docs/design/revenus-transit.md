# Design — revenus-transit (5c)            retenu : A (cartes séparées) · 2026-11-11

**Conclusion** : la nature se choisit par trois lignes radio, chacune avec son aide (formulaire catégorie). Le tableau de bord gagne une carte « En transit » séparée, placée avant les Plafonds, et une ligne « Pro (MRTECH) » sous les dépenses. Rien ne s'affiche tant qu'aucune catégorie n'est en transit ou pro.

**Sélecteur** (Budgets/Create et Edit), entre « Nom » et « Budget mensuel » :
```
NATURE
(•) Courante       Revenus et dépenses du quotidien
( ) En transit     Tontine, remboursement, avance : hors revenus, dépenses et plafonds
( ) Pro (MRTECH)   Comptée, mais affichée à part du budget perso
```
- `fieldset` + `legend`, lignes de 56 px, coche sur la ligne choisie (pas seulement la couleur).
- En transit : le champ « Budget mensuel » est masqué. En pro : il est gardé, avec l'aide « Plafond propre au pro ».
- 422 : message `role="alert"` sous le fieldset.

**Carte « En transit »** (`TransitCard.vue`) :
```
En transit · cycle jusqu'au 25/11        Voir tout
Sorti          150 000 F   ↑
Revenu         500 000 F   ↓
Net          + 350 000 F   Reçu plus que sorti
Hors revenus et dépenses.
```
- Net négatif : « − 120 000 F · Sorti plus que reçu ». Net nul : « Équilibré ».
- Aucun mouvement dans le cycle : « Aucun mouvement en transit ce cycle. »
- Aucune catégorie transit : carte masquée.
- Le net porte toujours un signe, une icône et un libellé ; la couleur n'est jamais seule. Le lien « Voir tout » mène aux opérations filtrées sur les catégories transit.

**Ligne pro**, dans la carte des dépenses : « Pro (MRTECH) 62 000 F · inclus dans le total ». Badge neutre, masqué sans catégorie pro.

**Accessibilité** :
- `section aria-labelledby` ;
- `aria-label` complet sur le net ;
- cibles de 44 px ;
- montants de 16 px minimum, retour à la ligne autorisé.

Écartée : B, l'encart unique « Hors budget perso ». Il mélange pro (compté) et transit (exclu), et une liste déroulante cache l'aide.
