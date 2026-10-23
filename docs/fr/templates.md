# Templates Twig

## Ce que vous allez apprendre

Rendre du HTML avec Twig depuis une route EorbahAPI : créer un moteur
`Eorbahapi\Templating\TwigTemplates`, le rendre injectable par le conteneur,
rendre un fichier `.twig` avec `TemplateResponse()`, rendre une chaîne de
template à la volée avec `createTemplate()`, et comprendre ce que le framework
fait — ou ne fait pas — du `Content-Type` d'une réponse HTML.

## Le code

`docs_src/TemplatingExample.php` prépare un répertoire de templates dans le
dossier temporaire, puis l'expose à l'application :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Templating\TwigTemplates;

final class TemplatingExample extends DocsExample
{
    public static function templateDir(): string
    {
        $dir = sys_get_temp_dir() . '/eorbah_docs_templates';
        $cache = sys_get_temp_dir() . '/eorbah_docs_templates_cache';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (!is_dir($cache)) {
            mkdir($cache, 0777, true);
        }

        file_put_contents(
            $dir . '/index.html.twig',
            '<h1>{{ title }}</h1><ul>{% for item in items %}<li>{{ item }}</li>{% endfor %}</ul>'
        );

        return $dir;
    }

    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $templates = new TwigTemplates(
            self::templateDir(),
            sys_get_temp_dir() . '/eorbah_docs_templates_cache'
        );

        $app->register(TwigTemplates::class, $templates);

        // Le moteur de templates est injecté par son type.
        $app->get('/', function (TwigTemplates $twig) {
            return $twig->TemplateResponse('index.html.twig', [
                'title' => 'EorbahAPI',
                'items' => ['routes', 'params', 'tests'],
            ]);
        });

        // Rendu d'un template à la volée.
        $app->get('/inline', function (TwigTemplates $twig) {
            return $twig->createTemplate('Bonjour {{ name }}', ['name' => 'Oumar']);
        });

        return $app;
    }
}
```

Le constructeur est `new TwigTemplates($templateDir, $cacheDir, $debug)` :
`$templateDir` vaut `'templates'` par défaut, le cache est calculé à partir du
dossier courant (`cache/twig`) si vous omettez `$cacheDir`, et `$debug` (`false`)
active les informations de débogage de Twig. `auto_reload` reste actif : un
template modifié est rechargé à la requête suivante.

Les deux méthodes de rendu renvoient une **chaîne HTML** :

| Méthode | Ce qu'elle rend |
| --- | --- |
| `TemplateResponse(string $template, array $data = [])` | un fichier du répertoire de templates (`index.html.twig`) |
| `createTemplate(string $content, array $data = [])` | un template écrit dans la chaîne (`'Bonjour {{ name }}'`) |

## Ce qui se passe

```php
<?php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/', contains: '<h1>EorbahAPI</h1><ul><li>routes</li><li>params</li><li>tests</li></ul>'),
        self::scenario('GET', '/inline', contains: 'Bonjour Oumar'),
    ];
}
```

| Requête | Statut | Corps |
| --- | --- | --- |
| `GET /` | 200 | `<h1>EorbahAPI</h1><ul><li>routes</li><li>params</li><li>tests</li></ul>` |
| `GET /inline` | 200 | `Bonjour Oumar` |

Dans les deux cas, le handler retourne une chaîne : le framework la place dans
le corps de la réponse, garde le statut `200` et **ne force aucun
`Content-Type`** — la liste des en-têtes de la réponse reste vide. Twig échappe
les variables (`{{ title }}`) selon son `autoescape` HTML par défaut, mais ce
n'est pas le framework qui décide du type MIME.

Pour l'annoncer explicitement, retournez un `Eorbahapi\Responses\HTMLResponse`
(il pose `Content-Type: text/html; charset=UTF-8`, sauf si vous passez
`setContentType: false`) :

```php
<?php
use Eorbahapi\Responses\HTMLResponse;

$app->get('/page', function (TwigTemplates $twig) {
    $html = $twig->TemplateResponse('index.html.twig', ['title' => 'EorbahAPI', 'items' => []]);

    return new HTMLResponse($html);
});
```

!!! tip "`register()` fixe la configuration, l'injection par type la transporte"
    `$app->register(TwigTemplates::class, $templates)` associe votre instance au
    conteneur de dépendances : tout paramètre typé `TwigTemplates $twig` reçoit
    exactement ce moteur, avec son répertoire et son cache. C'est ce qui rend le
    rendu disponible dans plusieurs routes sans variable globale. Pensez à créer
    le répertoire de cache : Twig écrit dedans.

!!! warning "Une chaîne retournée n'est pas une réponse HTML typée"
    Retourner `$twig->TemplateResponse(...)` donne un `200` avec un corps HTML
    mais sans `Content-Type`. Un navigateur s'en accommode, un client strict ou
    un test qui vérifie l'en-tête, non. Utilisez `HTMLResponse` (ou votre propre
    `Response` avec l'en-tête) dès que le type MIME compte.

## Récapitulatif

- `new TwigTemplates($templateDir, $cacheDir, $debug)` encapsule un
  `Twig\Environment` sur un répertoire de fichiers `.twig` ;
- `$app->register(TwigTemplates::class, $templates)` rend le moteur injectable
  dans les handlers par simple typage ;
- `TemplateResponse($template, $data)` rend un fichier,
  `createTemplate($content, $data)` rend une chaîne ;
- les deux renvoient une chaîne : statut `200`, `Content-Type` non forcé ;
- `HTMLResponse` pose `Content-Type: text/html; charset=UTF-8` ;
- Twig échappe les variables et recharge les templates modifiés.

[Suivant : Gestion des erreurs](errors.md){ .md-button }
