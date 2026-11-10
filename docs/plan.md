# Plan — Morex phase 2            VALIDÉ le 2026-11-10

**Conclusion** : on commence par `socle-prod` (une faille en production, sans dépendance), puis `saisie-rapide`, qui sert tous les jours.
Taux de référence : 80 000 FCFA par jour (projet personnel, à titre indicatif) → ~11,5 j ≈ 920 000 FCFA.

| # | Module | Contenu (1 ligne) | Dépend de | UI ? | Sensible ? | Est. | Statut |
|---|---|---|---|---|---|---|---|
| 0 | correctifs-critiques | P1–P4 (2FA, synchro, verrou du solde, SMS en attente) — PR morex_web#1 + morex_mobile#1 | — | non | oui | — | livré (PR ouvertes) |
| 1 | socle-prod | `APP_DEBUG` coupé, en-têtes de sécurité, Swagger fermé en prod, `/health`, cron `schedule:run`, sauvegarde MySQL quotidienne | 0 | non | oui | 1 j | livré (PR ouverte) |
| 2 | saisie-rapide | Formulaire mobile « montant d'abord », compte par défaut, catégorie suggérée par libellé, modèles favoris, widget iOS | 0 | oui | non | 2 j | livré (PR ouverte) |
| 3 | sms-iphone | Jeton Sanctum limité au webhook, Raccourci iOS documenté, `SmsParser` testé sur des SMS réels, validation en lot | 1 | oui | oui | 2 j | à faire |
| 4 | rapprochement | Serveur + API + écran web : solde du relevé → écart, pointage, opération d'écart tracée ; `adjust-balance` passe par le service | 0 | oui | non | 2 j | livré (PR ouverte) |
| 4 bis | rapprochement-mobile | Écran de rapprochement sur l'iPhone, branché sur l'API du module 4 | 4 | oui | non | 1 j | à faire |
| 5 | suivi-objectifs | Tableau de bord des 5 objectifs (fonds d'urgence, épargne 25 %, plafonds sorties et dons, projets), alertes de plafond | 4 | oui | non | 2 j | à faire |
| 6 | recurrences-serveur | Génération des récurrences par le scheduler (idempotente), plus seulement sur le mobile | 1 | non | non | 1 j | à faire |
| 7 | dette-qualite | 4 tests mobiles périmés, 9 avertissements `flutter analyze`, CI GitHub Actions, points moyens des revues de sécurité, opérations rapprochées modifiables, catégories d'écart sélectionnables | 0 | non | oui | 1,5 j | à faire |

Statuts : à faire · analysé · en cours · livré · vérifié. Un module = une session, ≤ 15 fichiers.
Les modules 2, 4 et 7 sont indépendants entre eux et peuvent se faire dans n'importe quel ordre après le 1.
