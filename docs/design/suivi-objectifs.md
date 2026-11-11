# Design — suivi-objectifs (5a)                   retenu : A (barre + deux lignes d'indicateurs) · 2026-11-11

**Conclusion** : la carte « Objectif » du tableau de bord prolonge la barre existante avec deux lignes de texte. La première donne le versement du mois, la seconde la projection. Sur la fiche objectif, un bloc séparé « Suivre sur un compte » remplace le champ « Compte associé » du formulaire principal (un seul sélecteur de compte).

```
Objectif                                              Voir >
Épargne Investissement                              80 %
[████████████████████░░░░░]   1 590 000 / 2 000 000 FCFA
Ce cycle (depuis le 27/10)   300 000 / 300 000   ✓ Atteint
Rythme suffisant pour le 31/12
```

**États**
- Non suivi : lien « Suivre sur un compte › ».
- Sans versement visé : « Versé ce mois-ci : X » et « Il faudrait X FCFA/mois pour le 31/12 ».
- En retard : « ⚠ En retard : reste X », en couleur d'avertissement, et non en rouge d'erreur.
- Atteint : badge « Atteint » (icône et texte), barre pleine, lignes d'indicateurs masquées.
- Sans cycle : libellé « Ce mois-ci ».

**Bloc « Suivre sur un compte »** (Goals/Edit), `glass-card` séparée avec son propre bouton :
- non lié : compte, montant de départ (pré-rempli, avec une aide), versement mensuel visé (facultatif), puis « Suivre ce compte » ;
- lié : compte, date de début, départ et versement visé, puis « Arrêter le suivi » avec confirmation (« La progression redeviendra manuelle, figée à X FCFA »).

Sur un objectif suivi, le champ « Montant actuel » passe en lecture seule, et la liste remplace « Contribuer » par « Suivi automatique · compte ».

**Accessibilité** :
- barre : `role="progressbar"`, `aria-valuenow`, `aria-valuemax` et un libellé complet ;
- statuts en texte et icône, jamais la couleur seule ;
- `label for` et `aria-describedby` sur les champs ;
- confirmation au clavier ;
- cibles de 44 px.

Écartée : B, la jauge circulaire (composant SVG à créer, et plusieurs objectifs côte à côte tiennent mal sur iPhone).
