# Design — plafonds-alertes (5b)                  retenu : A (barres compactes triées) · 2026-11-11

**Conclusion** : la carte « Budgets » du tableau de bord devient « Plafonds ». Elle compte une ligne par catégorie plafonnée, triée du pourcentage le plus élevé au plus bas, et un statut en texte et icône. La liste « Alertes du cycle » est placée dessous. Les notifications push renvoient vers cette carte (`/#plafonds`) ou vers les opérations de la catégorie.

```
Plafonds · cycle jusqu'au 25/11                          Voir tout
2 plafonds dépassés
Emilie/Courses                         ✕ Dépassé · 105 %
██████████████████████████████
158 000 / 150 000 · +8 000 au-delà           Voir les opérations
Sorties/Loisirs                        ⚠ Attention · 94 %
███████████████████████████░░
160 500 / 170 000 · reste 9 500              Voir les opérations
Abonnements                            50 %
45 000 / 90 000 · reste 45 000
— Alertes du cycle —
✕ 10/11  Emilie/Courses   Dépassé   150 500 / 150 000
⚠ 09/11  Sorties/Loisirs  80 %      136 000 / 170 000
```

**États**
- Aucun plafond : « Aucun plafond défini », avec un lien vers Budgets.
- Tout sous 80 % : « Tout est dans les clous » (icône coche).
- Plusieurs dépassements : en-tête « N plafonds dépassés » (`role=status`).
- Barre plafonnée à 100 % ; l'excédent est donné en texte.
- Nouveau cycle : liste d'alertes vide, « Aucune alerte ce cycle ».

**Accessibilité** :
- `role=progressbar` avec `aria-valuenow`, `aria-valuemax` et un libellé (« Sorties/Loisirs, 94 %, attention, reste 9 500 francs CFA ») ;
- statut toujours en texte et icône ;
- lien « Voir les opérations » libellé avec la catégorie ;
- `<time datetime>` dans la liste des alertes ;
- cibles de 44 px sur iPhone.

**Notifications** :
- 80 % : « X à 94 % » / « 160 500 / 170 000 FCFA. Il reste 9 500 jusqu'au 25/11. » (ou « jusqu'au prochain salaire » si le cycle n'a pas de date de fin) ;
- 100 % : « Plafond dépassé : X » / « … soit +8 000 au-delà ».

Écartée : B, les tuiles « À surveiller » (un plafond à 79 % resterait caché derrière un clic, et il y a deux modes d'affichage à maintenir).
