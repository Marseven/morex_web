# Module dette-qualite-mobile (7b)                  statut : en cours · 2026-11-12

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
