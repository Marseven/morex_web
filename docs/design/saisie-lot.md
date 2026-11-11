# Design — saisie-lot (web)                       retenu : A (étapes Coller → Vérifier, une colonne) · 2026-11-10

**Conclusion** : une seule page en deux étapes : ① coller les notes, puis « Analyser » ; ② vérifier le tableau et « Enregistrer N opérations ». **La catégorie est obligatoire** : le bouton reste bloqué tant qu'une ligne en manque. Les lignes non comprises sont exclues et visibles. Cas de 20 lignes : environ 7 actions.

```
① Coller                                         ② Vérifier                         [‹ Modifier le texte]
[Date de référence] [Compte par défaut ▾]        [Tout 20][⚠ À compléter 2][✕ Non comprise 1][⧉ 0]
┌ Vos notes ───────────────────────────┐         Dépenses −213 400 · Revenus +110 000
│ Vendredi 16 oct / 1500 taxi / …      │         Statut  Date  Sens  Montant  Libellé  Catégorie  Compte  ✕
└ 12 / 300 lignes · 340 / 20 000 car. ┘         ⚠ À compléter 16/10 Dép. −25 000 Night club [Choisir ▾] [Cpt ▾]
                            [Analyser]           ✕ Non comprise  « Tontine Zara »   [Modifier le texte]
                                                 [Enregistrer 19 opérations] (bloqué : 2 catégories à choisir)
iPhone : mêmes étapes, lignes en cartes, barre fixe en bas (totaux + Enregistrer).
```

**Statuts** : icône + mot, jamais la couleur seule. ✓ OK · ⚠ À compléter (catégorie ou libellé manquant) · ✕ Non comprise (exclue, texte source affiché) · ⧉ Déjà saisie ? (simple signal).

**Repérer et corriger** : filtres avec compteurs (`aria-pressed`) ; après l'analyse, le focus va au premier sélecteur à compléter ; une ligne corrigée passe en ✓ tout de suite ; « ✕ » retire une ligne ; « Modifier le texte » revient à l'étape ① en conservant les corrections (clé : ligne + montant + libellé).

**États** : *vide* : un exemple d'aide est visible et « Analyser » est désactivé. *Analyse en cours* : bouton « Analyse… », `aria-busy`. *Aucune opération reconnue* : message et lien vers le texte. *422* : message sous la zone de notes (`NOTES_TOO_LONG`) ou bandeau « Compte ou catégorie introuvable ». *429* : « Trop d'analyses, réessayez dans une minute ».

**Accessibilité** : résumé en `aria-live` (« 20 lignes, 17 ok, 2 à compléter, 1 non comprise ») ; sélecteurs libellés « Catégorie, ligne 7 : Night club, 16 octobre » ; champs ≥ 16 px (pas de zoom iOS) ; cibles de 44 px.

Écartée : B, deux panneaux avec aperçu en direct. L'analyse serveur à chaque frappe dépasserait la limite de requêtes, les corrections seraient perdues à chaque modification du texte, et sur iPhone les panneaux deviendraient des onglets, sans gain.
