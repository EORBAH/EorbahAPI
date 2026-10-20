---
name: eorbahapi
description: Carte du framework EorbahAPI — architecture, pipeline d'une requête, invariants, commandes, conventions de tests et de documentation. À charger avant d'ajouter une route, un paramètre, un middleware, un exemple ou une page de doc.
---

# EorbahAPI — carte du projet

Framework web PHP 8.1+ pour construire des API : attributs de route, paramètres
typés validés par `Svinx\Validator`, pipeline `handle()` testable.
Paquet Composer, namespace racine `Eorbahapi`.

## Graphe des dépendances

```
EorbahAPI
 ├── APIRouter (routes statiques + paramétrées, middlewares, dispatch)
 ├── Request / Response (HTTP, sources injectables)
 ├── DependencyResolver ──uses──> Params\* ──extends──> Svinx\Validator\FieldInfo
 │        │                        │
 │        │                        └──uses──> Svinx\Validator\Typing\Type (lax + withConstraints)
 │        ├──uses──> Exceptions\RequestValidationError
 │        └──uses──> Dependency\DependencyInterface
 ├── ExceptionHandlers ──uses──> Exceptions\* + Responses\JSONResponse
 ├── Middlewares\* (MiddlewareInterface : __invoke(Request, Response, $next))
 └── mountedApps : BaseMountedApplication
         ├── StaticFiles  (process() remplit la réponse)
         └── RPC\JsonRPC  (process() remplit la réponse)
```

## Flux d'une requête

```
run() / handle(Request)
  → setRequestResponse() (réassocie le resolver sans perdre le conteneur)
  → executeMiddlewares()            middlewares globaux, enveloppés dans l'ordre d'ajout
      → dispatch()
          → sous-applications montées  (Request::withUri(), préfixe retiré → handle())
          → router->match($uri, $method) → runRoute()
                → middlewares de route
                → DependencyResolver::resolve($callback, $params)
                      → #[Depends] / #[Security]   → DependencyInterface, callable, conteneur
                      → #[Path|Query|Header|Cookie|Body|Form|File]
                            → extraction brute → Type::fromReflection()->lax()->withConstraints()
                            → Svinx ValidationError → RequestValidationError (422)
                      → Request/Response → conteneur → autowiring → défaut → null
          → default_route() → 404 « Not Found »
  → APIRouter::toResponse()  (array → JSON, 'redirect:code:url', scalar, Response)
  → (en-têtes des middlewares reportés sur la réponse finale)
  → catch \Throwable → handleExceptionAsResponse() → Response
```

`run()` envoie la réponse ; `handle()` la retourne. **Aucun `send()` ni `exit`
dans le pipeline** : c'est ce qui rend l'ensemble testable.

## Invariants à ne pas casser

1. **`handle()` ne fait aucun envoi.** Tout ce qui est monté (`StaticFiles`,
   `JsonRPC`, sous-`EorbahAPI`) remplit `$this->response` via `process()` et
   retourne une `Response`.
2. **Un paramètre HTTP est validé par Svinx**, jamais à la main :
   `Type::fromReflection($type)->lax()->withConstraints($attr)` — les attributs
   `Eorbahapi\Params\*` étendent `FieldInfo` et portent donc les contraintes.
3. **Les erreurs de validation sont des `RequestValidationError` (422)** avec des
   `details` de la forme `{loc: [emplacement, nom], type, msg}`.
4. **Les middlewares gardent leur signature** `(Request $request, Response
   $response, callable $next)` ; `$next()` retourne une valeur normalisable en
   `Response`. `process()`, `__invoke()` et `handle()` sont acceptés.
5. **Les exemples de `docs_src/` sont la source de vérité** : ils sont exécutés
   par `tests/ExamplesTest.php`. Toute page de doc cite un fichier réel.
6. **Le français est la langue source de la documentation** ; les traductions
   sont générées par `scripts/translate.py`, jamais éditées à la main.
7. **Compatibilité** : les anciens attributs `Eorbahapi\Routing\{Route,Get,...}`
   et le paramètre `middlewares:` des routes restent fonctionnels.

## Arborescence

| Chemin | Contenu |
| --- | --- |
| `src/EorbahAPI.php` | Application : `handle()`, `run()`, montage, middlewares, contrôleurs |
| `src/APIRouter.php` | Routeur autonome : `match()`, `dispatch()`, `toResponse()`, `normalizeMiddleware()` |
| `src/Request.php` | Requête, avec sources injectables (`server`, `query`, `postData`, `cookies`, `files`, `headers`, `body`) |
| `src/Response.php` | Réponse de base (`body`, `status`, `headers`, `send()`) |
| `src/Responses/` | `JSONResponse`, `HTMLResponse`, `RedirectResponse`, `StreamingResponse`, `EventStreamResponse`, `FileResponse` |
| `src/Dependency/` | `DependencyResolver`, `DependencyInterface` |
| `src/Params/` | Attributs de paramètre + `ParamTypes` (enum) |
| `src/Routing/Attributes/` | `Route`, `Get`, `Post`, `Put`, `Patch`, `Delete`, `Head`, `Options`, `Middleware` |
| `src/Middlewares/` | `MiddlewareInterface`, `CORSMiddleware`, `SessionMiddleware`, `TrustedHostMiddleware`, `RateLimitingMiddleware` |
| `src/Security/` | `HTTP\{HTTPBearer,HTTPBasic}`, `APIKey\*`, `OAuth2\*`, `JWTAuth\JWT`, `Nonce` |
| `src/Testing/` | `TestClient`, `TestResponse`, `TestClientInterface` |
| `src/StaticFiles.php` | Application montée qui sert un répertoire |
| `src/RPC/JsonRPC.php` | Serveur JSON-RPC 2.0 monté |
| `src/Templating/TwigTemplates.php` | Rendu Twig |
| `docs_src/` | 22 exemples exécutables (`Eorbahapi\Examples\*Example`) + `registry.php` |
| `docs/fr/` | Cours source (français) |
| `tests/` | PHPUnit : un fichier par brique + `ExamplesTest` |
| `scripts/` | `translate.py`, `prompts/`, `README.md` |

## Commandes

```bash
composer install
composer dump-autoload                                   # après ajout d'un fichier dans docs_src/ ou src/
./vendor/bin/phpunit                                     # toute la suite
./vendor/bin/phpunit --filter ExamplesTest               # valide les exemples de la doc
./vendor/bin/phpunit --filter StaticFilesTest            # une brique
find src -name '*.php' -exec php -l {} \;                # vérif de syntaxe

python scripts/translate.py --lang all --check           # CI : traductions manquantes
python scripts/translate.py --lang en                    # traduire (LLM_API_KEY)
python scripts/translate.py --lang en --provider copy    # amorcer sans API
mkdocs serve                                             # site de documentation
```

## Ajouter une brique (recette)

1. **Route** : `$app->get('/x', $handler)` ou contrôleur `#[Get('/x')]` +
   `$app->include_controller(new X())`.
2. **Paramètre** : attribut `Eorbahapi\Params\*` sur un paramètre typé ; les
   contraintes viennent de `FieldInfo` (`ge`, `min_length`, `pattern`, `choices`…).
3. **Modèle** : classe `extends Svinx\Validator\BaseModel`, dans `docs_src/` si
   elle sert la doc, sinon dans `src/`.
4. **Middleware** : implémenter `MiddlewareInterface` puis
   `#[Middleware(Classe::class, args: [...])]` ou `$app->add_middleware(...)`.
5. **Exemple** : créer `docs_src/<Brique>Example.php`, l'ajouter au registre
   `docs_src/registry.php`, écrire `scenarios()`, puis lancer
   `./vendor/bin/phpunit --filter ExamplesTest`.
6. **Doc** : créer la page `docs/fr/<brique>.md`, l'ajouter au `nav` de
   `mkdocs.yml`, citer le fichier `docs_src/...`.

## Conventions de tests

- Un fichier par brique : `ApplicationTest`, `RoutingTest`, `ParamsTest`,
  `RequestTest`, `ResponsesTest`, `DependencyTest`, `ExceptionsTest`,
  `StaticFilesTest`, `TemplatingTest`, `JsonRpcTest`, `SecurityTest`,
  `TestClientTest`, `ExamplesTest`.
- Toujours passer par `TestClient` et `$app->handle()` : jamais de `send()`,
  jamais de socket, jamais d'`exit`.
- Isoler les superglobales : `TestClient` injecte tout (`headers`, `query`,
  `json`, `form`, `cookies`, `files`, `server`).
- Un test qui fige un message vérifie une sous-chaîne stable, pas la phrase entière.
- Les classes d'exemple de `docs_src/` ne sont **jamais** redéclarées dans `tests/`.

## Pièges connus

| Piège | Détail |
| --- | --- |
| `getallheaders()` | Absent en CLI : `Request::readHeaders()` reconstruit les en-têtes depuis `$_SERVER`. Ne jamais appeler `getallheaders()` directement. |
| `Response::header()` | C'est un **setter** (`header($nom, $valeur)`), pas un lecteur. Pour lire : `$response->headers['X']`. |
| En-têtes de middleware | Les middlewares écrivent dans `$this->response->headers` ; `handle()` reporte ces en-têtes sur la réponse finale si le handler en a produit une autre. |
| ETag | Doit être entre guillemets doubles : `'"' . $etag . '"'` (RFC 9110). |
| `StreamingResponse` | `send()` fait `ob_flush()` : dans un test, utiliser `expectOutputString()` et non `ob_start()`. |
| `Response::$body` | Type `string\|array\|null` : caster les scalaires en chaîne. |
| `DependencyResolver` | Ne pas hériter de `Request` ; `setRequestResponse()` conserve le conteneur entre deux `handle()`. |
| Doublons historiques | `src/Security/Http/HTTPAuthorizationCredentials.php` (casse) et `src/Security/HTTP/HTTPBase.php` (vide) sont morts : ne pas les importer. |
| `RateLimitingMiddleware` | Exige Redis (connexion paresseuse, échec à l'exécution). Ne pas l'utiliser dans un test sans Redis. |
| JWT | `firebase/php-jwt` v7 exige une clé HS256 ≥ 32 octets. |

## Références à charger au besoin

- `references/api.md` — surface publique (classes, méthodes, attributs).
- `references/patterns.md` — recettes prêtes à l'emploi (CRUD, auth, upload, SSE).
- `references/testing.md` — écrire des tests avec `TestClient`.
- `references/docs.md` — conventions de rédaction et de traduction.
