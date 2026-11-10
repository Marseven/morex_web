# Cadrage — Morex, phase 2 : saisie, rapprochement, suivi            2026-11-10

**Conclusion** : 11 modules (4 livrés). Production sécurisée, saisie rapide et rapprochement de solde sont faits ; restent la saisie en lot, le rapprochement avec les relevés, le suivi de l'objectif 2 M avec l'épargne réelle et le bilan mensuel.

**Objectif** : que Richard saisisse chaque dépense en moins de 10 secondes (et une journée de notes en une fois), rapproche ses notes avec ses relevés Airtel/BGFI, et voie d'un coup d'œil où il en est de l'objectif 2 M au 31/12 et de ses plafonds — sans passer par un assistant externe pour générer des CSV.

**Ajusté le 2026-11-10** d'après les analyses mensuelles de Richard (juillet → octobre) : la saisie se fait en notes texte par journée, le vrai rapprochement est notes ↔ relevés (frais inclus), l'épargne circule par transferts, les tontines faussent revenus et dépenses. Modules 2 bis, 3 (élargi), 5 (enrichi) et 8 ajoutés ou revus.

**Acteurs** : Richard (seul utilisateur, iPhone + navigateur) · l'app Raccourcis d'iOS (envoi des SMS) · Airtel Money, BGFI et BGFI Mobile (relevés, SMS) · Hostinger (hébergement, cron, MySQL).

**Périmètre V2**
- Production sûre : debug coupé, en-têtes de sécurité, Swagger fermé, sauvegarde MySQL quotidienne, `/health`.
- Saisie rapide sur mobile : montant d'abord, compte par défaut, catégorie suggérée d'après le libellé, modèles favoris, widget iOS.
- Saisie en lot : coller les notes d'une journée (« 1500 taxi », « 22500+12000 night club ») → aperçu catégorisé → validation.
- Relevés et SMS : import des relevés Airtel/BGFI (et SMS via Raccourci iOS), rapprochement automatique avec les écritures (montant + frais, même jour), non rapprochés soumis à validation.
- Rapprochement : je saisis le solde réel, l'app montre l'écart et les opérations non pointées, l'écart devient une transaction tracée (jamais un ajustement silencieux).
- Suivi : objectif Épargne Investissement 2 M au 31/12 mesuré sur le Compte Epargne (transferts compris), versement de 300k du mois, plafonds réels avec alertes, tontines en transit, pro/perso, liste d'achats prévus.
- Bilan mensuel : réalisé vs budget et proposition du cycle suivant, directement dans l'app.
- Récurrences générées par le serveur (cron), plus seulement quand le téléphone est ouvert.
- Qualité : tests mobiles réparés, CI GitHub Actions minimale, points moyens restants de la revue de sécurité.

**Hors V2** : multi-utilisateurs, publication sur les stores, Android, lecture directe des SMS, connexion bancaire (open banking), réécriture de la synchro.

**Contraintes** : Laravel 11 + Inertia/Vue (web), Flutter iPhone (mobile), Hostinger mutualisé sans SSH connu ni CI, déploiement manuel, montants en FCFA entiers. Données financières personnelles : chiffrement en transit, aucun SMS brut dans les logs.

**Risques majeurs**
1. iOS interdit la lecture des SMS → parade : Raccourci iOS + parsing côté serveur (fonctionne sans l'app ouverte).
2. Écarts historiques déjà corrigés à la main → parade : le premier rapprochement fixe une base de départ datée ; aucun audit rétroactif.
3. Pas de CI ni de sauvegarde → parade : module 1 (sauvegarde) et module 7 (CI) avant toute migration lourde.

**Questions clés** (avec l'hypothèse retenue par défaut)
1. SMS « sur le téléphone » : c'est impossible sur iOS. → *Hypothèse : Raccourci iOS + parsing serveur.*
2. Quels expéditeurs exactement (Airtel Money, Moov Money, quelles banques) ? → *Hypothèse : Airtel + Moov ; il faut 5 SMS réels anonymisés par format.*
3. Le cron `schedule:run` est-il actif chez Hostinger ? → *Hypothèse : non ; à activer dans le module 1.*
4. À quel rythme rapprocher les comptes ? → *Hypothèse : chaque semaine, compte par compte, avec un rappel.*
5. La saisie se fait-elle aussi sur le web ? → *Hypothèse : mobile d'abord ; le web sert à consulter et corriger.*
