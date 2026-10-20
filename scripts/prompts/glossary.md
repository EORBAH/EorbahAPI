# Glossaire partagé

Terminologie de référence. Chaque langue peut avoir son propre fichier
`scripts/prompts/<code>.md` ajouté à ce glossaire ; il est injecté dans le prompt
à la place de `{{glossary}}`.

## Français → anglais

| Français | English |
| --- | --- |
| application | application |
| route / routage | route / routing |
| routeur | router |
| contrôleur | controller |
| handler | handler |
| middleware | middleware |
| paramètre de chemin | path parameter |
| paramètre de requête | query parameter |
| en-tête | header |
| cookie | cookie |
| corps de requête | request body |
| réponse | response |
| statut | status |
| redirection | redirect |
| dépendance | dependency |
| conteneur | container |
| schéma de sécurité | security scheme |
| jeton | token |
| clé d'API | API key |
| fichier téléversé | uploaded file |
| fichiers statiques | static files |
| modèle | model |
| champ | field |
| contrainte | constraint |
| validation | validation |
| valeur par défaut | default value |
| valeur brute | raw value |
| coercition | coercion |
| mode laxiste | lax mode |
| sérialisation | serialization |
| imbriqué / imbrication | nested / nesting |
| gestionnaire d'exceptions | exception handler |
| suite de tests | test suite |
| client de test | test client |
| runner personnalisé | custom runner |
| sous-application montée | mounted sub-application |
| traversée de répertoire | directory traversal |
| compression | compression |
| cache | cache |
| gabarit / template | template |
| détail des erreurs | error details |
| requis | required |
| facultatif / optionnel | optional |
| exemple | example |
| récapitulatif | summary |
| aller plus loin | going further |
| premiers pas | first steps |

## Termes à ne jamais traduire

`EorbahAPI`, `APIRouter`, `Request`, `Response`, `JSONResponse`, `HTMLResponse`,
`RedirectResponse`, `StaticFiles`, `JsonRPC`, `TwigTemplates`, `TestClient`,
`TestResponse`, `UploadFile`, `DependencyResolver`, `Svinx\Validator`,
`BaseModel`, `Field`, `FieldValidator`, `ValidationError`, `HTTPException`,
`ValidationException`, `RequestValidationError`, `ParamTypes`, `HTTPBearer`,
`HTTPBasic`, `APIKeyHeader`, `APIKeyQuery`, `APIKeyCookie`, `OAuth2`, `JWT`,
`CORSMiddleware`, `RateLimitingMiddleware`, et tous les chemins de fichiers.
