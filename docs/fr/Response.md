# EorbahAPI — Réponses HTTP modulaires

Le framework expose désormais des fonctions de réponse dans le module `Eorbahapi\Responses`, inspirées du style FastAPI et compatibles avec la logique de retour du core dans `EorbahAPI`.

## Sommaire

1. [JSONResponse](#1-jsonresponse)
2. [HTMLResponse](#2-htmlresponse)
3. [FileResponse](#3-fileresponse)
4. [StreamingResponse](#4-streamingresponse)
5. [RedirectResponse](#5-redirectresponse)
6. [Compatibilité avec le core](#6-compatibilite-avec-le-core)

---

## 1. JSONResponse

### `JSONResponse(mixed $content, int $statusCode = 200, array $headers = [], bool $setContentType = true): string`

Retourne une chaîne JSON encodée avec les bons en-têtes et le bon code HTTP.

```php
use function Eorbahapi\Responses\JSONResponse;

$app->get('/users', function () {
    return JSONResponse([
        'users' => ['alice', 'bob'],
    ]);
});
```

Comportement :
- encode en JSON UTF-8 sans échappement excessif ;
- pose `Content-Type: application/json; charset=UTF-8` par défaut ;
- accepte des en-têtes supplémentaires au format `['X-Trace' => 'abc']` ;
- est compatible avec le noyau qui détecte automatiquement les tableaux et objets renvoyés par la route.

---

## 2. HTMLResponse

### `HTMLResponse(string $content, int $statusCode = 200, array $headers = []): string`

Retourne un document HTML prêt à être affiché.

```php
use function Eorbahapi\Responses\HTMLResponse;

$app->get('/', function () {
    return HTMLResponse('<h1>Bonjour</h1>');
});
```

Le helper applique le code de statut et le type MIME HTML, puis renvoie le contenu brut. La stricte compatibilité avec le mécanisme de retour du framework permet de retourner directement la chaîne depuis la route.

---

## 3. FileResponse

### `FileResponse(string $filePath, array $options = []): string`

Serre un fichier sur le client avec une gestion de type MIME et de téléchargement.

```php
use function Eorbahapi\Responses\FileResponse;

$app->get('/download', function () {
    return FileResponse(__DIR__ . '/files/report.pdf', [
        'disposition' => 'attachment',
        'filename' => 'report.pdf',
    ]);
});
```

Options principales :
- `disposition` : `inline` ou `attachment`
- `filename` : nom optionnel proposé au navigateur
- `custom_headers` : en-têtes HTTP additionnels

---

## 4. StreamingResponse

### `StreamingResponse(iterable $generator, string $contentType = 'text/event-stream'): string`

Permet d’émettre un flux d’informations progressivement.

```php
use function Eorbahapi\Responses\StreamingResponse;

$app->get('/events', function () {
    $generator = function () {
        foreach (['bonjour', 'monde'] as $message) {
            yield "data: {$message}\n\n";
        }
    };

    return StreamingResponse($generator(), 'text/event-stream');
});
```

Le helper active le bon `Content-Type`, envoie le flux itératif et force le flush côté serveur pour que le client reçoive les données au fur et à mesure.

---

## 5. RedirectResponse

### `RedirectResponse(string $url, int $statusCode = 302): string`

Crée une redirection compatible avec le pipeline principal du framework.

```php
use function Eorbahapi\Responses\RedirectResponse;

$app->get('/old', function () {
    return RedirectResponse('/new', 301);
});
```

Important : la fonction ne fait pas `exit()`. Elle retourne une chaîne spéciale de la forme `redirect:<code>:<url>`, que le core interprète et transforme en en-tête HTTP `Location`.

---

## 6. Compatibilité avec le core

Le cœur du framework détecte automatiquement les types de retour suivant :
- tableau PHP → JSON encodé avec `JSONResponse()`
- objet PHP → JSON encodé avec `JSONResponse()`
- chaîne commençant par `redirect:` → redirection HTTP
- chaîne HTML / texte brut → sortie directe
- fichier / flux / réponse structurée → selon le helper appelé

Cela permet d’écrire des routes de cette forme :

```php
$app->get('/ping', function () {
    return ['ok' => true];
});

$app->get('/go', function () {
    return \Eorbahapi\Responses\RedirectResponse('/home');
});
```

Cette approche est compatible avec la logique de `EorbahAPI::applyReturn()` et évite de mélanger la logique de réponse directement dans la classe `Response`.

---

## API complémentaire du core

La classe `Response` reste utile pour les réglages HTTP de bas niveau :
- `status()`
- `setHeader()`
- `header()`
- `set_content_type()`
- `cookie()`
- `clearCookie()`

Les fonctions du module `Responses` sont conçues pour être plus lisibles, plus modulaires et plus proches d’un style `FastAPI` ou `Starlette`.
