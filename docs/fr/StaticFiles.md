# EorbahAPI — Classe `StaticFiles`

La classe `Eorbahapi\StaticFiles` permet de servir des fichiers statiques depuis un répertoire local et peut être montée dans une application EorbahAPI.

## Instanciation

```php
$static = new StaticFiles(__DIR__ . '/public', [
    'index' => 'index.html',
    'cache_control' => 'public, max-age=3600',
    'compression' => true,
]);
```

### Options disponibles

- `index` : nom du fichier par défaut pour les répertoires (`index.html` par défaut)
- `cache_control` : valeur de l'en-tête `Cache-Control`
- `compression` : active ou désactive la compression des fichiers textuels
- `allowed_extensions` : tableau d'extensions autorisées

## Méthodes

### `serve(string $path): bool`

Tente de servir le fichier correspondant à `$path`. Retourne `true` si le fichier a été envoyé, sinon `false`.

### `handle(Request $request, Response $response): void`

Gestionnaire compatible avec `mount()` et `run()`.

### `run($http_code = "404", $handler = null): void`

Version compatible avec l'appel de sous-applications depuis `EorbahAPI::run()`.

## Utilisation avec `mount()`

```php
$app = new EorbahAPI();
$app->mount('/static', new StaticFiles(__DIR__ . '/public'));
$app->run();
```

Dans cet exemple, toutes les requêtes vers `/static/*` sont servies depuis le dossier `public`.

## Exemples pratiques

### Fichier unique

```php
$static = new StaticFiles(__DIR__ . '/public', [
    'allowed_extensions' => ['html', 'css', 'js', 'png', 'jpg'],
]);
$app->mount('/assets', $static);
```

### Index de répertoire

Si le chemin cible un dossier, `StaticFiles` recherche automatiquement le fichier `index.html`.

### Protection contre le path traversal

La méthode `sanitizePath()` supprime les séquences `../` et `..\`, ce qui empêche l'accès aux fichiers en dehors du répertoire configuré.
