# Module recurrences-serveur (6)                    statut : en cours · 2026-11-11

**Conclusion** : les opérations récurrentes (loyer, abonnements, épargne de 300 000…) sont **générées par le serveur** chaque matin, qu'on ouvre l'app ou non. Le serveur rattrape les échéances manquées et ne crée jamais deux fois la même échéance : chaque opération générée porte sa récurrence et sa date d'échéance, uniques ensemble. Sur l'iPhone, le bouton « Générer » demande désormais la génération au serveur au lieu de créer des opérations localement. La génération ne passe donc plus que par un seul chemin.

**But** : des soldes, des plafonds et un bilan justes sans action manuelle. **Acteurs** : le scheduler (cron Hostinger), Richard (bouton manuel, web ou iPhone).

**Scénarios**
1. 06:00, `recurrences:generate` : pour chaque récurrence active échue, l'opération du jour est créée avec la date d'échéance, puis la date suivante est calculée. Le solde bouge par le chemin habituel (`Transaction::booted`), et les plafonds et alertes suivent.
2. Serveur arrêté trois jours : au passage suivant, les trois échéances quotidiennes manquées sont créées, au plus 60 par récurrence et par passage.
3. iPhone, « Générer maintenant » : appel à `POST /api/recurring-transactions/process-due`, puis synchronisation. Les opérations arrivent par le pull.
4. Une même échéance traitée deux fois (cron et bouton en même temps, rejeu) : une seule opération existe.
- Erreurs :
  - compte ou catégorie supprimé → la récurrence est désactivée et signalée (`ACCOUNT_GONE`), sans erreur pour les autres ;
  - fin de date ou d'occurrences atteinte → la récurrence est désactivée, comme aujourd'hui.

**Données** (migration réversible) : `transactions.recurring_transaction_id` (nullable, FK `nullOnDelete`) et `transactions.occurrence_date` (date), avec un index unique sur la paire. La synchro et la Resource renvoient le lien.

**API / tâches**
- Commande `recurrences:generate`, planifiée `dailyAt('06:00')` (fuseau de l'app), `withoutOverlapping`.
- `POST /api/recurring-transactions/process-due` et `/{id}/generate` passent par le même service (auth Sanctum, throttle existant).
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
