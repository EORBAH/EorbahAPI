# FormData

`Eorbahapi\Datastructures\FormData` permet de manipuler des données structurées, de les nettoyer et de les valider rapidement.

## Utilisation basique

```php
use Eorbahapi\Datastructures\FormData;

$form = new FormData([
    'name' => '<b>Alice</b>',
    'email' => 'alice@example.com',
]);

print_r($form->all());
print_r($form->get('name'));
```

## Nettoyage XSS

```php
$clean = $form->xssClean();
```

## Sanitisation avec règles

```php
$rules = [
    'name' => ['type' => 'string', 'max_length' => 50, 'xss_clean' => true],
    'email' => ['type' => 'email', 'xss_clean' => true],
];

$sanitized = $form->sanitizeInput($rules);
```

Cette méthode applique :
- conversion en chaîne pour `string`
- coupe à `max_length`
- validation d'email
- échappement HTML avec `htmlspecialchars()` si `xss_clean` est activé
