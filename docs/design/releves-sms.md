# Design — releves-sms (3a, web)                   retenu : A (accordéon par groupe) · 2026-11-11

**Conclusion** : page « Relevé » d'un compte, en deux étapes ① Coller → ② Vérifier. On vérifie quatre groupes en accordéon, et seuls ceux qui demandent une décision s'ouvrent d'office. Les rapprochées restent repliées : l'attention va aux quelques lignes à trancher. « Valider » dit ce qui reste à décider.

```
② Vérifier · Compte Airtel Money                 [‹ Modifier le texte]
25 lignes · 19 rapprochées · 2 à valider · 3 sans opération · 1 non comprise
▼ ⚠ À valider (2)                       ← ouvert
   05/11 RELEVÉ −2 000   ○ Taxi −2 000 (04/11)  ○ Bus −2 000 (05/11)  ○ Créer  ○ Ignorer
▼ ＋ Sans opération (3)                 ← ouvert
   18/10 BAMBOO PAY −35 855   [Créer ▾ Sorties]  [Ignorer]
▶ ✓ Rapprochées (19, dont 6 avec frais)
   ✓ RELEVÉ 24/10 DIGITECH −16 047
     MOREX  24/10 Restaurant −15 500   + Frais 547
▶ ✕ Non comprises (1)   « Promo … » — exclue
▶ ◌ Absentes du relevé (2)   information seulement
                                  [Valider — 3 à décider] (bloqué)
iPhone : mêmes groupes, barre fixe en bas (« 3 à décider » · Valider).
```

**Paire relevé ↔ opération** : montant du relevé, montant saisi et frais sont nommés à part, jamais fondus. Sans frais, un seul montant.

**Règles d'interface**
- Valider est bloqué tant qu'une ligne « à valider » n'a pas de choix, ou qu'une ligne « Créer » n'a pas de catégorie.
- Les lignes « déjà importées » sont montrées en information et ne sont pas renvoyées.
- Après validation, un récap s'affiche : « 19 rapprochées, 6 frais pour 3 282 FCFA, 3 opérations créées, 2 ignorées ».

**États**
- *Vide* : exemple de SMS et de CSV visible, « Analyser » désactivé.
- *Analyse en cours* : `aria-busy`.
- *Tout rapproché* : bandeau « Tout correspond » et « Valider N rapprochements ».
- *Erreur 422* : message de l'API affiché (`ALREADY_MATCHED` → « relancez l'analyse ») ; les choix sont conservés.

**Accessibilité** :
- en-tête de groupe = `<button aria-expanded>` avec icône, mot et compteur ;
- choix d'une candidate = `radiogroup` natif ;
- récap en `role="status"` ;
- cibles de 44 px ;
- la couleur n'est jamais le seul repère.

Écartée : B, la liste unique filtrée. Les quatre groupes y sont moins distincts, et les lignes rapprochées diluent les décisions.
