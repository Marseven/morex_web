# Module recurrences-serveur (6)                    statut : livré · 2026-11-11

**Conclusion** : les opérations récurrentes (loyer, abonnements, épargne de 300 000…) sont **générées par le serveur** chaque matin, qu'on ouvre l'app ou non. Le serveur rattrape les échéances manquées et ne crée jamais deux fois la même échéance : chaque opération générée porte sa récurrence et sa date d'échéance, uniques ensemble. Sur l'iPhone, le bouton « Générer » demande désormais la génération au serveur au lieu de créer des opérations localement. La génération ne passe donc plus que par un seul chemin.

**But** : des soldes, des plafonds et un bilan justes sans action manuelle. **Acteurs** : le scheduler (cron Hostinger), Richard (bouton manuel, web ou iPhone).

**Scénarios**
1. 06:00, `recurrences:generate` : pour chaque récurrence active échue, l'opération du jour est créée avec la date d'échéance, puis la date suivante est calculée. Le solde bouge par le chemin habituel (`Transaction::booted`), et les plafonds et alertes suivent.
2. Serveur arrêté trois jours : au passage suivant, les trois échéances quotidiennes manquées sont créées, au plus 60 par récurrence et par passage.
3. iPhone, « Générer maintenant » : appel à `POST /api/recurring-transactions-process-due`, puis synchronisation. Les opérations arrivent par le pull.
4. Une même échéance traitée deux fois (cron et bouton en même temps, rejeu) : une seule opération existe.
- Erreurs :
  - compte ou catégorie supprimé → la récurrence est désactivée et signalée (`ACCOUNT_GONE`), sans erreur pour les autres ;
  - fin de date ou d'occurrences atteinte → la récurrence est désactivée, comme aujourd'hui.

**Données** (migration réversible) : `transactions.recurring_transaction_id` (nullable, FK `nullOnDelete`) et `transactions.occurrence_date` (date), avec un index unique sur la paire. La synchro et la Resource renvoient le lien.

**API / tâches**
- Commande `recurrences:generate`, planifiée `dailyAt('06:00')` (fuseau de l'app), `withoutOverlapping`.
- `POST /api/recurring-transactions-process-due` et `/{id}/generate` passent par le même service (auth Sanctum, throttle existant).
- Mobile : le bouton appelle l'API, puis synchronise (une seule page touchée).

**Règles métier**
- RM-1 Une opération par (récurrence, échéance), garantie par l'index unique.
- RM-2 Génération sous verrou de la récurrence (`lockForUpdate`), dans une transaction, une échéance à la fois.
- RM-3 Rattrapage limité à 60 échéances par passage ; le reste suit au passage suivant.
- RM-4 Les opérations générées sont confirmées (elles comptent tout de suite) et portent la description « (Récurrent) ».
- RM-5 Une dépense issue d'une récurrence n'est jamais « hors liste » (module 5d) : les charges fixes sortent de la règle des 30 000.

**Sécurité** : la commande ne traite que des récurrences actives dont le compte appartient au même utilisateur ; aucun montant dans les logs (seulement des compteurs) ; la Policy existante s'applique sur les endpoints.

**Tests prévus** :
- service : échéance du jour, rattrapage, plafond de 60, idempotence, désactivation par date ou occurrences, compte supprimé ;
- commande ;
- endpoints 200, 403, 401 ;
- exclusion du hors liste ;
- synchro (lien renvoyé).

**Décisions** : la génération quitte le mobile (une seule source) ; la date d'échéance est stockée à part de `date`, qui reste modifiable par l'utilisateur.

**Questions** (hypothèse par défaut)
1. Le cron `* * * * * php artisan schedule:run` est-il actif chez Hostinger (module 1) ? → *Hypothèse : oui. Sinon, le bouton manuel reste le filet.*
2. Heure de génération ? → *Hypothèse : 06:00, avant la journée et avant le rappel de 20:00.*
3. Faut-il une notification « 3 opérations récurrentes ajoutées » ? → *Hypothèse : non dans ce module.*

**Hors module** : édition d'une récurrence sur le web (page dédiée), notification, génération en attente de validation.

## Livré (6)
- **Données** : migration réversible `transactions.recurring_transaction_id` et `occurrence_date`, uniques ensemble ; rollback testé.
- **Service** : `RecurrenceGenerator`, qui assure :
  - la génération sous verrou, une échéance à la fois ;
  - le rattrapage, 60 échéances au plus par passage ;
  - l'idempotence, y compris sur les opérations supprimées ;
  - l'arrêt signalé si le compte ou la catégorie a disparu (log sans montant) ;
  - l'isolation des échecs : une récurrence en erreur n'arrête pas les autres.
- **Dates** : les mois, trimestres et années gardent le jour d'ancrage (`day_of_month`, sinon le jour de départ), sans débordement. Une échéance au 31 donne 31/01, 28/02, 31/03 : février n'est plus sauté.
- **Tâche** : commande `recurrences:generate`, planifiée à 06:00 (`withoutOverlapping`).
- **API** : `POST /api/recurring-transactions-process-due` (renvoie `count`) et `/{id}/generate` (renvoie l'opération créée) passent par le même service.
- **Synchro** :
  - le lien est renvoyé au pull ;
  - un push ne peut pas réécrire `last_generated_date` ni ramener l'échéance sur une occurrence déjà générée.
- **Achats prévus (RM-5)** : une dépense récurrente n'est jamais hors liste.
- **iPhone** (`morex_mobile`, `feat/recurrences-serveur`) :
  - le bouton « Générer » synchronise, demande la génération au serveur, puis synchronise à nouveau ;
  - rien n'est généré si la synchro échoue ;
  - les messages distinguent une coupure réseau d'un refus du serveur ;
  - la génération locale et ses requêtes sont supprimées.
- **Tests** : `RecurrenceGenerationTest`, 19 tests. Suite web : 531 au vert. Analyse Flutter : aucun nouvel avertissement.
- **Revue de code, points hauts corrigés** : février sauté en fin de mois (débordement des dates) ; une récurrence en échec arrêtait toute la génération.
- **Revue de code, points moyens corrigés** :
  - retour arrière par la synchro ;
  - `generate` renvoie l'opération sous verrou ;
  - erreurs mobiles distinguées ;
  - tests (fin de mois, trimestriel, annuel, échec isolé, catégorie supprimée, solde et historique, retour arrière).
- **Revue de code, point bas corrigé** : code mort du DAO mobile supprimé.
- **Transition** : des opérations générées en local par l'ancienne app et pas encore envoyées n'ont pas de lien de récurrence. Le serveur peut donc générer la même échéance une seconde fois. Il faut synchroniser l'iPhone avant le déploiement.
- **Non vérifié** :
  - cron Hostinger actif ;
  - MySQL réel ;
  - app iPhone en conditions réelles (pas de test mobile automatisé du service).
