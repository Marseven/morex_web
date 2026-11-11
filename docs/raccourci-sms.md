# Raccourci iOS : envoyer les SMS bancaires à Morex

**En bref** : une automatisation iOS « Message reçu » envoie chaque SMS d'Airtel Money, de Moov ou de la banque au webhook de Morex. Si le SMS correspond à une opération déjà saisie, elle est **pointée**. Sinon, une opération **en attente** apparaît dans Morex, à valider.

## 1. Créer le jeton (une fois)
1. Morex web → **Profil** → carte « Raccourci SMS » → **Créer le jeton**.
2. Copiez le **jeton** et l'**URL du webhook** : le jeton n'est affiché qu'une seule fois.
3. Ce jeton ne sert qu'à envoyer des SMS : il ne donne accès à aucune autre donnée, et le Profil permet de le révoquer.

## 2. Créer l'automatisation (app Raccourcis)
1. Onglet **Automatisation** → **+** → **Message**.
2. **Expéditeur** : choisissez l'expéditeur (par exemple « AirtelMoney »). Créez une automatisation par expéditeur.
3. Cochez **Exécuter immédiatement**, puis **Suivant** → **Nouveau raccourci vide**.
4. Ajoutez l'action **Obtenir le contenu de l'URL** :
   - URL : l'URL du webhook copiée à l'étape 1 ;
   - Méthode : **POST** ;
   - En-têtes : `Authorization` = `Bearer <votre jeton>` et `Accept` = `application/json` ;
   - Corps de requête : **JSON**, avec `message` = la variable **Message** (contenu du SMS), et `sender` = le nom de l'expéditeur (texte, par exemple `AirtelMoney`).
5. Enregistrez.

## 3. Vérifier
- Envoyez-vous un SMS de test ou attendez le prochain SMS bancaire, puis ouvrez Morex :
  - opération déjà saisie : elle apparaît **pointée** ;
  - sinon : elle apparaît dans **Transactions → En attente**.
- La carte du Profil affiche la date du **dernier SMS reçu**.

## En cas de problème
| Réponse du webhook | Signification |
|---|---|
| 200 « pointée » | Le SMS correspond à une opération déjà saisie |
| 201 | Opération en attente créée |
| 200 « doublon » | Ce SMS (ou cette ligne de relevé) est déjà connu |
| 401 | Jeton absent, révoqué ou remplacé : recopiez le jeton du Profil |
| 403 | Jeton du Raccourci utilisé ailleurs que sur le webhook |
| 422 | SMS non reconnu (format inconnu) : rien n'est créé |
| 429 | Plus de 30 SMS par minute : réessai automatique au prochain SMS |

Le texte du SMS n'est **jamais stocké** : Morex garde seulement le montant, le libellé, la référence et une empreinte anti-doublon.
