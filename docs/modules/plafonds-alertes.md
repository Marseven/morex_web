# Module plafonds-alertes (5b)                      statut : livré · 2026-11-11

**Conclusion** : les plafonds par catégorie existent déjà (`budget_limit`). Les alertes actuelles ne vivent que sur l'iPhone et ne voient que ce qui y est saisi. Ce module les déplace **côté serveur** : toute dépense comptée dans le cycle (saisie web, saisie en lot, relevé, synchro) recalcule le plafond de sa catégorie. **Une seule alerte par seuil (80 %, 100 %) et par cycle** est enregistrée et envoyée en notification push. Le tableau de bord montre les plafonds avec un statut lisible.

**But** : être prévenu avant de dépasser, pas constater après (cas réel : sorties à 299 000 pour un plafond de 170 000 en octobre). **Acteurs / droits** : Richard ; le serveur envoie les notifications sur ses propres abonnements push.

**Scénarios**
1. Nominal :
   - une dépense « night club 40 500 » fait passer Sorties/Loisirs de 120 000 à 160 500 sur 170 000 (94 %) ;
   - une alerte « 80 % » est enregistrée ;
   - notification : « Sorties/Loisirs : 160 500 / 170 000 FCFA (94 %). Il reste 9 500 FCFA jusqu'au prochain salaire. »
2. Une dépense suivante franchit 100 % : alerte « dépassé », notification « +12 000 au-delà du plafond ». Une dépense de plus ne renvoie rien (déjà alerté pour ce seuil et ce cycle).
3. Tableau de bord, carte « Plafonds » : chaque catégorie plafonnée montre « 160 500 / 170 000 · ⚠ 94 % ». Les alertes du cycle sont listées, avec un lien « Voir les opérations » filtré sur la catégorie.
4. Nouveau cycle : les compteurs repartent, les alertes peuvent revenir.
- Erreurs :
  - pas de clé push configurée → alerte enregistrée et visible dans l'app, sans envoi ;
  - un abonnement push expiré (410) est supprimé.

**Données** (migration réversible) : `budget_alerts` (`id`, `user_id`, `category_id`, `cycle_start` date, `threshold` 80|100, `spent`, `limit`, `created_at`), avec un index unique (`user_id`, `category_id`, `cycle_start`, `threshold`) qui empêche tout doublon.

**API / écrans**
- `GET /api/budget-alerts` (alertes du cycle actif, Sanctum) ;
- tableau de bord web : carte « Plafonds » enrichie (statut, alertes du cycle) ;
- notification push (`SendDailyReminders` partage l'envoi via un `PushNotifier` extrait).

**Règles métier**
- RM-1 Le montant dépensé est la somme des dépenses de la catégorie sur le cycle actif (sinon le mois civil), selon les règles de `BudgetCycle::transactions()` : opérations en attente et écarts de rapprochement exclus.
- RM-2 Seuils : 80 % (« attention ») et 100 % (« dépassé »). Franchir les deux d'un coup n'envoie que l'alerte 100 %.
- RM-3 Une alerte par (catégorie, cycle, seuil), garantie par l'index unique.
- RM-4 Déclenchement après chaque création ou modification d'une dépense confirmée (événement de `Transaction`), y compris la validation d'une opération en attente.
- RM-5 Une suppression ou une baisse de montant ne retire pas une alerte déjà envoyée.

**Sécurité** : notifications envoyées uniquement aux abonnements de l'utilisateur concerné ; aucun montant dans les journaux ; `GET /api/budget-alerts` limité à l'utilisateur ; timeout sur l'envoi push.

**Tests prévus** :
- service (80 %, 100 %, saut direct à 100 %, pas de doublon, nouveau cycle, en attente et écarts exclus, sans plafond → rien) ;
- déclenchement par création, mise à jour et validation ;
- push simulé (configuré ou non, abonnement expiré) ;
- `GET /api/budget-alerts` (200, 401) ;
- tableau de bord.

**Décisions** : calcul synchrone et léger (une requête de somme par dépense, une seule catégorie) ; l'envoi push reste dans le même processus, avec timeout court ; pas de nouvelle dépendance (`minishlink/web-push` est déjà là).

**Questions** (hypothèse par défaut)
1. Faut-il un **plafond combiné** « discrétionnaire » (Sorties + Restaurants + Événements ≤ 350 000), comme recommandé dans vos analyses ? → *Hypothèse : non dans ce module, seulement les plafonds par catégorie. Le combiné irait dans un module ultérieur.*
2. Les seuils 80 % et 100 % vous conviennent-ils ? → *Hypothèse : oui, fixes, sans réglage.*
3. Faut-il aussi des alertes sur l'iPhone à partir du serveur ? → *Hypothèse : non. Les notifications web push suffisent ici, et l'iPhone garde ses alertes locales. L'envoi APNs relève d'un autre chantier.*

**Hors module** : plafond combiné, réglage des seuils, notifications APNs (iPhone natif), règle « une soirée club par mois ».

## Livré (5b)
- **Serveur** :
  - migration réversible `budget_alerts` (index unique `user_id`, `category_id`, `cycle_start`, `threshold`) ;
  - `BudgetAlertService` (période, dépensé, seuils, enregistrement, texte des notifications) ;
  - contrat `PushNotifier` et `WebPushNotifier`, partagés avec `SendDailyReminders` (timeout de 5 s, sans redirection).
- **Déclenchement** :
  - `Transaction::saved` sur une dépense confirmée (création, montant, catégorie, statut, date) ;
  - synchro : vérification de l'utilisateur après validation, hors de la transaction.
- **API** : `GET /api/budget-alerts`, réservé au propriétaire.
- **Web** : carte `BudgetCapCard` (direction A : barres triées par pourcentage, statut en texte et en icône, liste « Alertes du cycle »).
- **Tests** : `BudgetAlertTest` (15), `PushSafetyTest`, `SendDailyRemindersTest`. Suite complète : 452 au vert.
- **Vérifié dans le navigateur** sur une instance locale jetable.
- **Revue de sécurité, corrigé** :
  - isolation par utilisateur sur les catégories système partagées ;
  - notifications envoyées seulement aux services push connus (Google, Mozilla, Apple, Microsoft), sur l'abonnement comme à l'envoi.
- **Revue de code, corrigé** :
  - un échec d'envoi ne casse ni l'enregistrement ni la synchro (qui renvoie 200) ;
  - pas d'alerte 80 % après un dépassement dans la même période ;
  - période lue une fois par requête.
- **Écarts avec l'analyse** :
  - la carte du tableau de bord montre toutes les catégories plafonnées, plus seulement 5 ;
  - le dépensé y exclut désormais les opérations en attente (RM-1).
- **Non vérifié** :
  - MySQL réel ;
  - envoi push réel (clés VAPID de production) ;
  - aucune relance si un envoi échoue : l'alerte reste visible dans l'app.
