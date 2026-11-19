# Templates Twig

## Ce que vous allez apprendre

Rendre des pages HTML avec Twig : templates de fichiers, templates inline, cache
de compilation et injection dans les handlers.

## Rendre un template

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Responses\HTMLResponse;
use Eorbahapi\Templating\TwigTemplates;

$twig = new TwigTemplates(__DIR__ . '/templates', cacheDir: __DIR__ . '/cache/twig');

$app = new EorbahAPI();
$app->register(TwigTemplates::class, $twig);

$app->get('/', function (TwigTemplates $twig) {
    return new HTMLResponse($twig->TemplateResponse('home.html.twig', [
        'titre' => 'Accueil',
        'items' => ['Clavier', 'Souris'],
    ]));
});

$app->run();
```

`templates/home.html.twig` :

```twig
<h1>{{ titre }}</h1>
<ul>
    {% for item in items %}<li>{{ item }}</li>{% endfor %}
</ul>
```

## Templates inline

Pour un fragment court, sans fichier :

```php
<?php

$html = $twig->createTemplate('Total : {{ total }} €', ['total' => 42]);
```

## Cache de compilation

`new TwigTemplates($dir, $cacheDir, $debug)` :

| Paramètre | Défaut | Rôle |
| --- | --- | --- |
| `$templateDir` | `'templates'` | répertoire des templates |
| `$cacheDir` | `cache/twig` | cache de compilation Twig |
| `$debug` | `false` | mode debug Twig |

Le cache accélère les rendus suivants : Twig ne recompile un template que
lorsque la source change (`auto_reload`).

## Utiliser Twig sans le conteneur

```php
<?php

use Eorbahapi\Responses\HTMLResponse;

$app->get('/bonjour', function () use ($twig) {
    return new HTMLResponse($twig->TemplateResponse('bonjour.html.twig', [
        'nom' => 'Oumar',
    ]));
});
```

L'injection par type (`TwigTemplates $twig`) évite le `use` et profite du
conteneur de dépendances — voir [Dépendances](dependencies.md).

!!! tip "Templates et documentation"
    Les routes HTML n'ont pas besoin d'apparaître dans la documentation JSON ;
    si l'OpenAPI doit les ignorer ou les décrire, utilisez les métadonnées
    d'opération décrites dans [Documentation OpenAPI](openapi.md).

## Récapitulatif

- `TwigTemplates` charge un répertoire de templates et gère le cache ;
- `TemplateResponse($template, $data)` rend un fichier ;
- `createTemplate($source, $data)` rend une chaîne Twig ;
- le rendu doit être enveloppé dans une `HTMLResponse` pour le bon
  `Content-Type` ;
- l'instance se partage via `register()` et l'injection par type.

[Suivant : Documentation OpenAPI](openapi.md){ .md-button }
