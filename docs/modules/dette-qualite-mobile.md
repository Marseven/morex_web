# Module dette-qualite-mobile (7b)                  statut : livré · 2026-11-12

**Conclusion** : on solde la dette de l'app iPhone, sans fonctionnalité nouvelle, et la suite de tests mobile repasse au vert. Au programme :
- 4 tests périmés ;
- 9 avertissements `flutter analyze` ;
- la **rotation du jeton** (P15), avec un petit endpoint serveur ;
- `analysis_options.yaml` stabilisé.

La base locale chiffrée (SQLCipher) demande une nouvelle dépendance native : elle reste une question (question 1).

**But** : une suite mobile verte qu'on peut ajouter à la CI, et un vol de jeton à l'impact réduit. **Acteurs** : Richard (iPhone) ; l'API Sanctum.

**Points traités**
1. **Tests périmés** :
   - `categories_bloc_test` (création réussie, échec) ;
   - `debts_bloc_test` (dette marquée payée) ;
   - `widget_test` (titre Morex).

   Chacun est réaligné sur le comportement actuel, sans être désactivé. Si un test révèle un vrai bug, le code est corrigé à la place du test.
2. **9 avertissements** :
   - variables et champs inutilisés, import inutile ;
   - `!` et comparaison à `null` sans effet ;
   - 4 usages de `BuildContext` après un `await` sans garde `mounted` (risque de crash si l'écran est fermé entre-temps).
3. **P15, rotation du jeton** :
   - serveur : `POST /api/token/rotate` (Sanctum, throttle) crée un nouveau jeton, de mêmes capacités et de 30 jours, puis révoque l'ancien ;
   - iPhone : le jeton est renouvelé au démarrage et au retour au premier plan quand il a plus de 7 jours. En cas d'échec réseau, l'ancien jeton est gardé jusqu'à sa fin ;
   - un jeton volé expire donc au plus 7 jours après la dernière utilisation légitime, contre 30 aujourd'hui.
4. **`analysis_options.yaml`** : les exclusions des dossiers natifs (`android/**`, `ios/**`…), qu'un outil ajoute à chaque analyse, sont commitées. Le fichier ne bouge plus.
5. **CI mobile** : workflow `flutter analyze` et `flutter test` sur les PR (macOS non requis).

**Données** : aucune migration ; date d'émission du jeton gardée dans le trousseau (`first_unlock_this_device`, déjà en place).

**Sécurité** : la rotation révoque l'ancien jeton dans la même requête ; throttle `6,1` ; aucun jeton dans les logs ; un jeton expiré ou révoqué renvoie 401, et l'app vide le trousseau (comportement existant).

**Tests prévus** :
- web : rotation 200 (ancien jeton révoqué, nouveau valide), 401 sans jeton, capacités conservées ;
- mobile : service de rotation (moins de 7 jours : rien ; plus de 7 jours : rotation ; échec : ancien jeton gardé), plus les 4 tests réalignés.

**Décisions** : pas de jeton de rafraîchissement séparé, la rotation du jeton d'accès suffit pour une app personnelle ; pas de nouvelle dépendance.

**Questions** (hypothèse par défaut)
1. Chiffrer la base Drift avec SQLCipher (nouvelle dépendance native, migration de la base existante) ? → *Hypothèse : non dans ce module. La protection de données d'iOS chiffre déjà les fichiers de l'app tant que l'iPhone est verrouillé, et le jeton est au trousseau. SQLCipher ferait l'objet d'un ADR si vous le voulez.*
2. Délai de rotation de 7 jours ? → *Hypothèse : oui.*
3. CI mobile sur `ubuntu-latest` (analyse et tests, sans build iOS) ? → *Hypothèse : oui.*

**Hors module** : SQLCipher, build et signature iOS en CI, écrans mobiles 2 ter et 4 bis.

## Livré (7b)
- **Tests mobiles** : de 89 réussis et 4 en échec à **101 au vert**.
  - Réalignés : catégories (création refusée sur mobile, sans écriture), dette soldée (`recordPayment`), écran de démarrage (le logo porte le libellé « Morex » lu par VoiceOver).
  - Ajoutés : `TokenRotationService` (5) et `ApiClient` (4).
- **`flutter analyze`** : 9 avertissements → 0. Les 4 usages de `BuildContext` après un `await` sont maintenant gardés par `mounted`. Restent 12 informations de style, préexistantes et tolérées en CI.
- **`analysis_options.yaml`** : les exclusions des dossiers natifs sont commitées et le fichier ne bouge plus.
- **Rotation du jeton (P15)** :
  - Web : `POST /api/token/rotate` via `ApiTokenRotator`, avec limiteur `token-rotate` (6/min), refus d'un jeton de session (403) et 3 garde-fous.
    - **échéance absolue** : un jeton volé ne vit jamais plus de 30 jours après la connexion, même tourné ;
    - **délai de grâce de 2 minutes** pour l'ancien jeton, si la réponse se perd ;
    - **compare-and-set** : un rejeu est refusé (401) et ne crée pas de second jeton.
    - **déconnexion** : elle révoque aussi les jetons encore en délai de grâce (le jeton du Raccourci iOS, sans expiration, n'est pas touché).
  - iPhone : rotation au démarrage et au retour au premier plan au-delà de 7 jours. La date d'émission est gardée au trousseau, et un échec garde l'ancien jeton. Un 401 portant un ancien jeton ne vide plus la session, et les journaux de débogage masquent les jetons.
- **CI mobile** : `flutter analyze --no-fatal-infos` et `flutter test` sur chaque PR, avec Flutter 3.47.6, actions épinglées par SHA, lecture seule et Dependabot.
- **Tests web** : `TokenRotationTest` (7). Suite web : 560 au vert.
- **Revue de sécurité**, point haut corrigé : sans échéance absolue, la rotation prolongeait indéfiniment un jeton volé.
- **Revue de sécurité**, points moyens corrigés : déconnexion sur réponse perdue ou requête en vol ; double jeton en cas de rejeu.
- **Revue de sécurité**, points bas corrigés : jeton de session, journaux, nettoyage de la date, `persist-credentials`.
- **Décision maintenue (question 1)** : pas de SQLCipher (protection des données iOS et trousseau). Un ADR suivra si vous voulez chiffrer la base.
- **Non vérifié** :
  - premier passage des CI web et mobile sur GitHub ;
  - rotation sur l'iPhone réel.
