# EorbahAPI

**Framework web PHP moderne, rapide et typé, pour construire des API.**

EorbahAPI réunit trois idées simples :

- **des routes déclaratives** — attributs `#[Get]`, `#[Post]`, `#[Query]`, `#[Body]`… ou callbacks ;
- **des paramètres typés et validés** — la validation est celle de [`Svinx\Validator`](https://github.com/svinx-org/validator), inspirée de Pydantic (contraintes `ge`, `le`, `min_length`, `pattern`, `choices`, modèles imbriqués, sérialisation) ;
- **un pipeline testable** — `EorbahAPI::handle(Request): Response` construit la réponse sans l'envoyer, ce qui permet de brancher un serveur web, un runner CLI, un worker de file d'attente… ou le `TestClient` de la suite de tests.

```php
<?php

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\{Path, Query};

$app = new EorbahAPI();

$app->get('/items/{id}', function (#[Path(gte: 1)] int $id, #[Query(gte: 1, le: 100)] int $limit = 10) {
    return ['id' => $id, 'limit' => $limit];
});

$app->run();
```

```console
$ curl "http://localhost:8000/items/7?limit=3"
{"id":7,"limit":3}
```

```php
<?php

use Eorbahapi\EorbahAPI;

$app = new EorbahAPI()

$app->get("/", function () {
    return ["Hello" => "World"];
});

$app->get("/items/{item_id}", function (int $item_id, ?string $q) {
    return ["item_id" => $item_id, "q" => $q];
});

$app->run();
```

```console
$ curl "http://localhost:8000/items/7?q=query"
{"id":7,"q":"query"}
```

## Un cours, pas une liste de fonctions

Ce site est un **cours progressif**, dans l'esprit de la documentation de FastAPI. Chaque chapitre suit le même déroulé :

1. ce que vous allez apprendre ;
2. le code de l'exemple ;
3. ce qui se passe, requête par requête ;
4. un récapitulatif.

Chaque chapitre s'appuie sur un fichier réel de `docs_src/`. Ces fichiers sont **exécutés par la suite de tests** (`tests/ExamplesTest.php`) : si un exemple du cours ne fonctionne plus, les tests échouent. La documentation ne peut pas mentir.


## Par où commencer ?

| Si vous voulez… | Lisez |
| --- | --- |
| installer le framework | [Installation](installation.md) |
| écrire votre première API | [Premiers pas](first-steps.md) |
| comprendre le routage | [Routage](routing.md) |
| valider les paramètres | [Paramètres de requête](query-params.md) et [Validations](validations.md) |
| recevoir du JSON | [Corps de requête](request-body.md) |
| sécuriser une route | [Sécurité](security.md) |
| tester votre application | [Tests](testing.md) |
| lancer l'application autrement qu'en HTTP | [Runners personnalisés](runners.md) |

## Le sommaire complet

**Démarrage**

- [Installation](installation.md)
- [Premiers pas](first-steps.md)

**Guide du cours**

- [Routage](routing.md)
- [Paramètres de chemin](path-params.md)
- [Paramètres de requête](query-params.md)
- [Validations](validations.md)
- [Corps de requête](request-body.md)
- [Modèles imbriqués](nested-models.md)
- [Formulaires](forms.md)
- [Fichiers](files.md)
- [En-têtes](headers.md)
- [Cookies](cookies.md)
- [Réponses](responses.md)

**Aller plus loin**

- [Dépendances](dependencies.md)
- [Sécurité](security.md)
- [Middlewares](middlewares.md)
- [Contrôleurs](controllers.md)
- [Fichiers statiques](static-files.md)
- [Templates Twig](templates.md)
- [Gestion des erreurs](errors.md)
- [JSON-RPC](rpc.md)
- [Tests](testing.md)
- [Runners personnalisés](runners.md)

**Référence**

- [Référence API](reference.md)

## Prérequis

- PHP **8.1+** (le cours utilise PHP 8.4) ;
- Composer ;
- les extensions `mbstring` et `json` ;
- pour les tests : PHPUnit.

!!! tip "Convention du cours"
    Les exemples utilisent des fonctions anonymes pour aller au plus court. Les
    chapitres [Contrôleurs](controllers.md) et [Middlewares](middlewares.md)
    montrent la forme « classe + attributs », adaptée aux projets qui grossissent.
