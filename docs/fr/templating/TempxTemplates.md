# TempxTemplates

`Eorbahapi\Templating\TempxTemplates` est un moteur de templates léger inspiré des syntaxes simples de type Handlebars.

## Exemple

```php
use Eorbahapi\Templating\TempxTemplates;

$tpl = new TempxTemplates(__DIR__ . '/templates');
$html = $tpl->render('welcome.tempx', ['user' => ['name' => 'Alice']]);
```

## Exemples de syntaxe

```html
<h1>{{ user.name }}</h1>

{{#if user}}
  <p>Connecté</p>
{{/if}}

{{#each items}}
  <li>{{ name }}</li>
{{/each}}
```

Le moteur supporte :
- interpolation simple de variables
- conditions `if` / `unless`
- boucles `each` / `loop`
- filtres comme `uppercase`, `lowercase`, `length` et `truncate`

Il est agréable pour des templates simples ou des vues sans dépendre de Twig.
