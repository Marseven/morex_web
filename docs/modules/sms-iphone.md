# Module sms-iphone (3b)                            statut : en cours · 2026-11-12

**Conclusion** : les SMS bancaires arrivent par un **Raccourci iOS** (automatisation « Message reçu ») qui les envoie au webhook. Deux changements :
1. **Le SMS passe par le moteur de rapprochement du module 3a.** S'il correspond à une opération déjà saisie (dans vos notes du jour), il la **pointe**, au lieu de créer une opération en attente qui ferait doublon. Sinon, il crée l'opération en attente, comme aujourd'hui.
2. **Un jeton réservé au Raccourci** : il ne sert qu'au webhook. S'il est volé, il permet seulement d'envoyer des SMS, jamais de lire vos comptes.

Un guide pas à pas configure le Raccourci.

**But** : zéro doublon entre notes et SMS, sans ouvrir l'app. **Acteurs / droits** : Richard ; le Raccourci iOS, avec un jeton à capacité unique `sms:webhook`.

**Scénarios**
1. Profil → « Raccourci SMS » → « Créer le jeton ». Le jeton s'affiche **une seule fois**, avec l'URL du webhook et un bouton Copier. Un jeton existant est remplacé : un seul jeton actif.
2. Le guide (`docs/raccourci-sms.md`) explique l'automatisation, l'action « Obtenir le contenu de l'URL » (POST JSON `message` et `sender`) et l'en-tête `Authorization`.
3. SMS Airtel « Paiement de 15 500 à DIGITECH… » à 14 h, alors que « restaurant 15 500 » est déjà saisi : l'opération est **pointée**, la ligne de relevé est enregistrée (source `sms`), et aucune opération en attente n'est créée.
4. SMS sans opération correspondante : opération en attente, comme aujourd'hui ; elle se valide dans « En attente ».
5. « Révoquer » dans le Profil : le Raccourci reçoit 401.
- Erreurs :
  - jeton du Raccourci utilisé sur une autre route de l'API → 403 ;
  - SMS illisible → 422 (inchangé) ;
  - même SMS renvoyé → doublon détecté (inchangé).

**Données** : aucune migration. Le jeton est un jeton Sanctum nommé `sms-shortcut`, de capacité `sms:webhook`. Les lignes de relevé utilisent `statement_lines` (source `sms`) du module 3a.

**API / écrans**
- `POST /api/webhook/sms` : accepte un jeton complet ou `sms:webhook` ; throttle 30/min.
- Toutes les autres routes de l'API refusent un jeton qui n'a pas la capacité complète (`*`).
- Web : section « Raccourci SMS » du Profil (création, révocation, date du dernier usage). Elle est trop petite pour une étape de design ; la question 3 vous la laisse ouvrir.

**Règles métier**
- RM-1 Un SMS lisible devient une entrée de relevé, à la date du jour ; la correspondance suit les règles du module 3a (même compte, même sens, ±1 jour, montant égal ou montant + frais, une seule candidate).
- RM-2 Une correspondance unique pointe l'opération et enregistre la ligne ; sinon, une opération en attente est créée (comportement actuel).
- RM-3 Un seul jeton de Raccourci actif ; il n'expire pas (le Raccourci ne sait pas se renouveler), mais il est révocable et réservé au webhook.

**Sécurité** :
- capacité `sms:webhook` vérifiée ;
- middleware qui refuse un jeton restreint partout ailleurs ;
- throttle ;
- texte brut du SMS jamais stocké ni journalisé (empreinte seulement, inchangé) ;
- jeton affiché une fois, jamais renvoyé ensuite.

**Tests prévus** :
- SMS pointant une opération existante (pas d'opération en attente) ;
- SMS sans correspondance (opération en attente) ;
- jeton restreint : webhook 201, autres routes 403 ;
- révocation 401 ;
- création, remplacement et révocation depuis le Profil ;
- throttle.

**Décisions** : réutiliser `StatementMatcher` et `StatementService` du module 3a ; pas de nouvelle dépendance.

**Questions** (hypothèse par défaut)
1. Les SMS BGFI : avez-vous des exemples réels anonymisés ? → *Hypothèse : on garde les formats déjà reconnus (Airtel, Moov, BGFI, UBA) ; un SMS illisible renvoie 422, sans rien casser.*
2. Le jeton du Raccourci sans expiration vous convient-il ? → *Hypothèse : oui. Il est réservé au webhook et révocable, et un jeton qui expire casserait le Raccourci en silence.*
3. La section Profil sans étape de design ? → *Hypothèse : oui, une carte simple dans le style du Profil.*

**Hors module** : lecture des frais dans les SMS (aucun format n'en donne aujourd'hui), écran mobile, rapprochement des transferts (module 3c).
