# Cadrage — Morex, phase 2 : saisie, rapprochement, suivi            2026-11-10

**Conclusion** : 7 modules, environ 11 jours. On commence par sécuriser la production (`APP_DEBUG` est activé), puis on réduit la saisie à quelques secondes depuis l'iPhone, avant d'outiller le rapprochement des soldes et le suivi des objectifs.

**Objectif** : que Richard saisisse chaque dépense en moins de 10 secondes, rapproche chaque compte avec son solde réel en moins de 2 minutes par semaine, et voie d'un coup d'œil où il en est de ses 5 objectifs financiers.

**Acteurs** : Richard (seul utilisateur, iPhone + navigateur) · l'app Raccourcis d'iOS (envoi des SMS) · Airtel Money, Moov Money et les banques (SMS) · Hostinger (hébergement, cron, MySQL).

**Périmètre V2**
- Production sûre : debug coupé, en-têtes de sécurité, Swagger fermé, sauvegarde MySQL quotidienne, `/health`.
- Saisie rapide sur mobile : montant d'abord, compte par défaut, catégorie suggérée d'après le libellé, modèles favoris, widget iOS.
- SMS sur iPhone : un Raccourci iOS envoie le SMS au webhook, le serveur le parse, la validation se fait en lot dans l'app.
- Rapprochement : je saisis le solde réel, l'app montre l'écart et les opérations non pointées, l'écart devient une transaction tracée (jamais un ajustement silencieux).
- Suivi : tableau de bord centré sur les objectifs (fonds d'urgence, épargne 25 %, plafonds sorties et dons, revenus de projets), alertes à l'approche d'un plafond.
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
