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

$app->get('/items/{id}', function (
    #[Path(ge: 1)] int $id,
    #[Query(ge: 1, le: 100)] int $limit = 10,
) {
    return ['id' => $id, 'limit' => $limit];
});

$app->run();
```

```console
$ curl "http://localhost:8000/items/7?limit=3"
{"id":7,"limit":3}
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
| installer le framework | [Installation](#/fr/installation) |
| écrire votre première API | [Premiers pas](#/fr/first-steps) |
| comprendre le routage | [Routage](#/fr/routing) |
| valider les paramètres | [Paramètres de requête](#/fr/query-params) et [Validations](#/fr/validations) |
| recevoir du JSON | [Corps de requête](#/fr/request-body) |
| sécuriser une route | [Sécurité](#/fr/security) |
| tester votre application | [Tests](#/fr/testing) |
| lancer l'application autrement qu'en HTTP | [Runners personnalisés](#/fr/runners) |

## Le sommaire complet

**Démarrage**

- [Installation](#/fr/installation)
- [Premiers pas](#/fr/first-steps)

**Guide du cours**

- [Routage](#/fr/routing)
- [Paramètres de chemin](#/fr/path-params)
- [Paramètres de requête](#/fr/query-params)
- [Validations](#/fr/validations)
- [Corps de requête](#/fr/request-body)
- [Modèles imbriqués](#/fr/nested-models)
- [Formulaires](#/fr/forms)
- [Fichiers](#/fr/files)
- [En-têtes](#/fr/headers)
- [Cookies](#/fr/cookies)
- [Réponses](#/fr/responses)

**Aller plus loin**

- [Dépendances](#/fr/dependencies)
- [Sécurité](#/fr/security)
- [Middlewares](#/fr/middlewares)
- [Contrôleurs](#/fr/controllers)
- [Fichiers statiques](#/fr/static-files)
- [Templates Twig](#/fr/templates)
- [Gestion des erreurs](#/fr/errors)
- [JSON-RPC](#/fr/rpc)
- [Tests](#/fr/testing)
- [Runners personnalisés](#/fr/runners)

**Référence**

- [Référence API](#/fr/reference)

## Prérequis

- PHP **8.1+** (le cours utilise PHP 8.4) ;
- Composer ;
- les extensions `mbstring` et `json` ;
- pour les tests : PHPUnit.

:::callout{type="success" title="Convention du cours"}
Les exemples utilisent des fonctions anonymes pour aller au plus court. Les
chapitres [Contrôleurs](#/fr/controllers) et [Middlewares](#/fr/middlewares)
montrent la forme « classe + attributs », adaptée aux projets qui grossissent.
:::
