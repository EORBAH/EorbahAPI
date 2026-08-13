# TwigTemplates

`Eorbahapi\Templating\TwigTemplates` encapsule Twig pour rendre des templates depuis un dossier de vues.

## Exemple

```php
use Eorbahapi\Templating\TwigTemplates;

$twig = new TwigTemplates(__DIR__ . '/templates');
$html = $twig->render('home.html', ['name' => 'Alice']);
```

## Caractéristiques

- charge les templates depuis un dossier physique
- supporte le moteur Twig officiel
- permet d’utiliser les fonctions et filtres de Twig standard

## Exemple de template

```twig
<h1>Bonjour {{ name }}</h1>
```
