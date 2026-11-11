# Plan — Morex phase 2            VALIDÉ le 2026-11-10 · ajusté le 2026-11-10

**Conclusion** : 0, 1, 2 et 4 sont livrés (PR ouvertes). Ajustement d'après les analyses mensuelles de Richard : la priorité devient **2 bis saisie-lot** (remplace la génération de CSV par un assistant externe), puis **3 relevés** (rapprochement notes ↔ relevés), puis **5 objectifs** avec l'épargne réelle.
Taux de référence : 80 000 FCFA par jour (à titre indicatif) → reste ~13 j ≈ 1 040 000 FCFA.

| # | Module | Contenu (1 ligne) | Dépend de | UI ? | Sensible ? | Est. | Statut |
|---|---|---|---|---|---|---|---|
| 0 | correctifs-critiques | P1–P4 (2FA, synchro, verrou du solde, SMS en attente) — PR morex_web#1 + morex_mobile#1 | — | non | oui | — | livré (PR ouvertes) |
| 1 | socle-prod | `APP_DEBUG` coupé, en-têtes de sécurité, Swagger fermé en prod, `/health`, cron `schedule:run`, sauvegarde MySQL quotidienne | 0 | non | oui | 1 j | livré (PR ouverte) |
| 2 | saisie-rapide | Formulaire mobile « montant d'abord », compte par défaut, catégorie suggérée par libellé, modèles favoris, widget iOS | 0 | oui | non | 2 j | livré (PR ouverte) |
| 2 bis | saisie-lot | Coller les notes d'une journée (« 1500 taxi », « 22500+12000 night club », entrées incluses) → aperçu avec catégorie et compte suggérés (service du module 2) → validation en une fois ; serveur + API + web | 2 | oui | non | 2 j | livré (PR ouverte) |
| 2 ter | saisie-lot-mobile | Écran iPhone de saisie en lot, branché sur l'API du module 2 bis | 2 bis | oui | non | 1 j | livré (PR ouverte) |
| 3a | releves-sms | Relevés Airtel (SMS collés) / BGFI (CSV) ; rapprochement automatique ligne ↔ opération (montant + frais, ±1 j) ; frais isolés ; non rapprochés à valider ; écran web + API | 2 bis | oui | oui | 2 j | livré (PR ouverte) |
| 3b | sms-iphone | Raccourci iOS → webhook → lignes de relevé (même moteur que 3a) ; jeton Sanctum limité au webhook | 3a | non | oui | 1 j | livré (PR ouverte) |
| 3c | transferts-frais | Transferts entre comptes avec frais (retraits Airtel, GIMAC), import compris | 3a | oui | non | 1 j | livré (PR ouverte) |
| 4 | rapprochement | Serveur + API + écran web : solde du relevé → écart, pointage, opération d'écart tracée ; `adjust-balance` passe par le service | 0 | oui | non | 2 j | livré (PR ouverte) |
| 4 bis | rapprochement-mobile | Écran de rapprochement sur l'iPhone, branché sur l'API du module 4 | 4 | oui | non | 1 j | à faire |
| 5a | suivi-objectifs | Objectif suivi sur un compte (progression = solde du Compte Epargne, transferts compris), versement du mois (300k), projection au 31/12, carte du tableau de bord | 4 | oui | non | 1,5 j | livré (PR ouverte) |
| 5b | plafonds-alertes | Plafonds réels (sorties 170k, Emilie 150k, abonnements 90k…) avec alertes à 80 % / 100 % et discrétionnaire agrégé | 5a | oui | non | 1 j | livré (PR ouverte) |
| 5c | revenus-transit | Tontines, remboursements et transferts internes exclus des revenus/dépenses ; séparation pro/perso (abonnements MRTECH) | 5a | oui | non | 1 j | livré (PR ouverte) |
| 5d | achats-prevus | Liste d'achats prévus avec statut ; règle « > 30k hors liste attend le cycle suivant » | 5a | oui | non | 1 j | livré (PR ouverte) |
| 6 | recurrences-serveur | Génération des récurrences par le scheduler (idempotente), plus seulement sur le mobile | 1 | non | non | 1 j | livré (PR ouvertes) |
| 8 | bilan-mensuel | Bilan du cycle (réalisé vs budget, discrétionnaire, épargne) et proposition du cycle suivant créée directement dans l'app (plus de CSV à générer) | 5 | oui | non | 2 j | livré (PR ouverte) |
| 7a | dette-qualite | Web : opérations rapprochées dépointées à la modification, catégories d'écart refusées partout, P12/P13/P16/P17, dépensé par catégorie, Form Request de saisie, CI (PHP 8.5, Pint, Gitleaks, garde-fou PHP) | 0 | non | oui | 1 j | livré (PR ouverte) |
| 7b | dette-qualite-mobile | Mobile : 4 tests périmés, avertissements `flutter analyze`, jeton 30 j et base Drift en clair (P15), hook `analysis_options.yaml` | 7a | non | oui | 1 j | livré (PR ouvertes) |

Statuts : à faire · analysé · en cours · livré · vérifié. Un module = une session, ≤ 15 fichiers.
Ordre conseillé : 2 bis → 3 → 5 → 8 ; 4 bis, 6 et 7 se glissent entre deux selon le besoin.
