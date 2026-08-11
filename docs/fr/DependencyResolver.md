# EorbahAPI — Classe `DependencyResolver`

La classe `Eorbahapi\DependencyResolver` résout automatiquement les arguments des callbacks de route en fonction du type-hint, des attributs de dépendance, des paramètres fournis et du conteneur.

## Objectif

`DependencyResolver` permet de :

- injecter automatiquement `Request` et `Response`
- résoudre les paramètres de route ou de body
- instancier des classes sans constructeur obligatoire
- utiliser des services enregistrés via `$app->register()`
- appliquer l'attribut `#[Depends]`

## Ordre de résolution

1. Attribut `#[Depends]`
2. Paramètres fournis par la route ou le corps de la requête
3. Injection automatique de `Request` et `Response`
4. Services enregistrés dans le conteneur
5. Instanciation automatique de classes sans paramètres obligatoires
6. Valeurs par défaut du paramètre
7. `null` si le type le permet

## Exemple : injection de paramètres de route

```php
$app->get('/items/{item_id}', function (string $item_id, Response $res) {
    $res->json(['item_id' => $item_id]);
});
```

`DependencyResolver` récupère automatiquement la valeur de `{item_id}` dans les paramètres fournis par la route.

## Exemple : injection de services

```php
use PDO;

$pdo = new PDO('sqlite::memory:');
$app->register(PDO::class, $pdo);

$app->get('/db', function (PDO $pdo, Response $res) {
    $res->json(['status' => 'connected']);
});
```

## Exemple : instanciation automatique

Les classes sans constructeur obligatoire peuvent être instanciées automatiquement si elles ne sont pas présentes dans le conteneur.

```php
class Mailer {}

$app->get('/mail', function (Mailer $mailer, Response $res) {
    $res->json(['mailer' => get_class($mailer)]);
});
```

## Exemple : utilisation de l'attribut `#[Depends]`

```php
use Eorbahapi\Attributes\Depends;

class AuthProvider
{
    public function resolve(Request $request, Response $response)
    {
        return $request->getBearerToken();
    }
}

$app->get('/secure', function (#[Depends(class: AuthProvider::class)] $token, Response $res) {
    $res->json(['token' => $token]);
});
```

> `#[Depends]` permet de préciser la classe à instancier ou à résoudre pour un paramètre, quel que soit son nom ou son type.
