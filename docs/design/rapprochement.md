# Design — rapprochement (web)                    retenu : A (page unique, deux colonnes) · 2026-11-10

**Conclusion** : une seule page. À gauche, le relevé, l'écart et Valider (colonne fixe) ; à droite, les opérations à cocher. L'écart se recalcule à chaque case. Cas « relevé 152 300, Morex 150 000, 3 opérations » : 6 clics en plus de la saisie.

**Formule d'écart retenue** (amende RM-5) : `écart = relevé − (solde Morex − somme signée des opérations non cochées)`. Une opération non cochée n'est pas encore passée sur le relevé : elle reste en attente au lieu d'être compensée. Le serveur recalcule l'écart au moment de valider ; la valeur affichée sert d'aperçu.

```
Desktop (≥ 1024)                                   Mobile (iPhone)
Retour · Rapprocher Airtel Money                   Solde du relevé [152 300]
┌ Solde du relevé [152 300] FCFA ┐ ┌ Non pointées (3)   [Tout] ┐   cartes : case 44 px · libellé · −1 500
│ Solde Morex       150 000      │ │ ☑ 02/11 Salaire   +2 000 │   « corriger » sur la ligne du bas
│ Écart ▲ +2 300 à justifier     │ │ ☑ 03/11 Courses   −1 500 │   barre fixe en bas :
│ Note (option.)                 │ │ ☑ 05/11 Transport   −500 │   Écart ▲ +2 300 · [Valider]
│ [Valider le rapprochement]     │ └ Cochées : 3 / 3          ┘
└────────────────────────────────┘
```

**Confirmation** (`alertdialog`) si l'écart est non nul : « Créer une opération d'écart de +2 300 FCFA (revenu, catégorie Écart de rapprochement, datée d'aujourd'hui) ? ». Écart nul : pas de dialogue, un toast suffit.

**Badge des cartes de compte** : ≤ 7 j → coche + « Rapproché il y a 3 j » (texte atténué) ; > 7 j → horloge + texte orange ; jamais → « Jamais rapproché ». Le badge mène à la page. Un bouton « Rapprocher » s'ajoute à côté d'« Ajuster ».

**États** : *vide* : « Tout est pointé. Vérifiez que le relevé correspond à Morex », Valider reste actif. *Chargement* : squelettes, la saisie reste utilisable. *422* : message sous le champ ; pour `INVALID_RECONCILIATION`, bandeau « Une opération ne correspond plus à ce compte. Rechargez la page » ; les cases cochées sont conservées. Valider est désactivé pendant l'envoi.

**Accessibilité** :
- label visible « Solde du relevé (FCFA) » ;
- écart en `role="status" aria-live="polite"` (« Écart : plus 2 300 francs, à justifier ») ;
- signe, flèche et mot, jamais la couleur seule ;
- chaque case est libellée « Pointer : Courses, 3 novembre, moins 1 500 francs » ;
- le focus est piégé dans le dialogue (Échap ferme).

**Composants** : réutilisés : `AppLayout`, `glass-card`, `Toast`, les classes de champ et de bouton de la modale « Ajuster », `formatAmount`. Nouveaux : `Accounts/Reconcile.vue`, `ReconciledBadge`.

Écartée : B, l'assistant en 3 étapes (un clic de plus et une navigation entre étapes, pour un seul utilisateur qui connaît le parcours).
