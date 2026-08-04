# EorbahAPI — Classe `Response`

La classe `Response` (`EorBah545\Eorbahapi\Response`) représente la réponse HTTP renvoyée au client. Elle est injectée automatiquement dans vos routes et propose des méthodes pour répondre en JSON, en HTML, en fichier, en flux (streaming), en redirection, ainsi que pour gérer les en-têtes et les cookies.

## Sommaire

1. [Réponses JSON](#1-réponses-json)
2. [Réponse générique — send()](#2-réponse-générique--send)
3. [Code de statut HTTP — status()](#3-code-de-statut-http--status)
4. [Réponse HTML — HTMLResponse()](#4-réponse-html--htmlresponse)
5. [Réponse fichier — FileResponse()](#5-réponse-fichier--fileresponse)
6. [Réponse en flux — StreamingResponse()](#6-réponse-en-flux--streamingresponse)
7. [Type de contenu et en-têtes — set_content_type()](#7-type-de-contenu-et-en-têtes--set_content_type)
8. [Redirections — RedirectResponse() / redirect()](#8-redirections--redirectresponse--redirect)
9. [En-têtes personnalisés — setHeader()](#9-en-têtes-personnalisés--setheader)
10. [Cookies — cookie() / clearCookie()](#10-cookies--cookie--clearcookie)

---

## 1. Réponses JSON

### `JSONResponse($data, $header = true): void`
### `json($data, $header = true): void`

Envoie `$data` encodé en JSON. `json()` est un simple alias de `JSONResponse()`.

- `$data` : valeur PHP à encoder (tableau, objet, scalaire…).
- `$header` : si `true` (par défaut), envoie l'en-tête `Content-Type: application/json`. Passez `false` si l'en-tête a déjà été défini ailleurs.

```php
$app->get('/', function (Response $res) {
    $res->json(["hello" => "world"]);
});
```

---

## 2. Réponse générique — `send()`

### `send($message = null, $type = null): void`

Envoie une réponse brute au client.

- Si `$message` est une chaîne, elle est d'abord passée à `trim()`.
- Si `$type === "json"`, le message est envoyé via `JSONResponse()`.
- Si `$message` est `null`, une chaîne vide est envoyée.
- Sinon, `$message` est affiché tel quel (`echo`).

```php
$res->send("Opération réussie");
$res->send(["ok" => true], "json");
```

> Utilisée en interne par `status()->send(...)`, notamment dans les middlewares (voir la documentation principale du framework, section *Middlewares*).

---

## 3. Code de statut HTTP — `status()`

### `status(int $code): self`

Définit le code de statut HTTP de la réponse, uniquement si les en-têtes n'ont pas déjà été envoyés (`headers_sent()`). Retourne `$this`, ce qui permet de chaîner les appels.

```php
$res->status(401)->send('Non autorisé');
$res->status(201)->json(['id' => 42]);
```

---

## 4. Réponse HTML — `HTMLResponse()`

### `HTMLResponse(string $content, int $statusCode = 200, array $headers = []): void`

Envoie une réponse HTML.

- `$content` : le contenu HTML à envoyer.
- `$statusCode` : code de statut HTTP (`200` par défaut).
- `$headers` : en-têtes additionnels sous forme `['Nom-En-Tête' => 'valeur']`.

Comportement :
- Si les en-têtes ont déjà été envoyés, la méthode se contente d'appliquer le statut `200` et d'afficher le contenu (sans pouvoir modifier les en-têtes).
- Sinon, elle applique `$statusCode`, définit `Content-Type: text/html; charset=utf-8`, ajoute les en-têtes personnalisés fournis, puis envoie le contenu.

```php
$app->get('/', function (Response $res) {
    $res->HTMLResponse('
        <!DOCTYPE html>
        <html>
            <body><h1>Bonjour</h1></body>
        </html>
    ');
});
```

---

## 5. Réponse fichier — `FileResponse()`

### `FileResponse(string $filePath, array $options = []): void`

Envoie le contenu d'un fichier au client, avec détection automatique du type MIME selon l'extension.

- `$filePath` : chemin du fichier sur le serveur. Si le fichier est introuvable ou illisible, une réponse `404 File not found` est renvoyée.
- `$options` :
  - `disposition` : `'inline'` (par défaut) ou `'attachment'`.
  - `filename` : nom de fichier proposé au téléchargement (par défaut, le nom du fichier source).
  - `custom_headers` : en-têtes additionnels sous forme de tableau associatif.

Extensions reconnues et types associés : `json`, `html`, `jpg`/`jpeg`, `png`, `gif`, `css`, `js`, `pdf`, `mp4`, `mp3`. Toute autre extension est envoyée en `application/octet-stream`.

```php
$app->get('/download/{name}', function (Response $res, $name) {
    $res->FileResponse("storage/files/{$name}", [
        'disposition' => 'attachment',
        'filename'    => $name
    ]);
});
```

---

## 6. Réponse en flux — `StreamingResponse()`

### `StreamingResponse($gen, $ContentType = 'text/event-stream'): void`

Envoie une réponse progressive (streaming), typiquement pour du **Server-Sent Events (SSE)**. `$gen` doit être itérable — un générateur PHP (`yield`) est l'usage recommandé.

- Définit l'en-tête `Content-Type` (par défaut `text/event-stream`) et `Cache-Control: no-cache`.
- Parcourt `$gen` et, pour chaque élément, l'affiche puis force l'envoi immédiat au client via `ob_flush()` et `flush()`.

### Exemple complet — flux SSE consommé par une page HTML

```php
function event_generator() {
    // Générateur synchrone qui produit des messages SSE
    $counter = 1;
    while ($counter <= 10) {
        yield "data: Message numéro {$counter}";
        $counter += 1;
        sleep(1);
    }
}

$app->get("/events", function (Response $res) {
    $res->StreamingResponse(
        event_generator(),
        "text/event-stream"
    );
});

$app->get("/", function (Response $res) {
    $res->HTMLResponse('
    <!DOCTYPE html>
    <html>
    <body>
        <h1>Test SSE</h1>
        <ul id="messages"></ul>
        <script>
            const source = new EventSource("/events");
            const messages = document.getElementById("messages");
            source.onmessage = function(event) {
                const li = document.createElement("li");
                li.textContent = event.data;
                messages.appendChild(li);
            };
        </script>
    </body>
    </html>
    ');
});
```

> **Note :** chaque message émis par le générateur doit respecter le format attendu par le protocole SSE (préfixe `data: `, terminé par une fin de ligne).

---

## 7. Type de contenu et en-têtes — `set_content_type()`

### `set_content_type(string $contentType = 'html', ?int $cacheMaxAge = null, array $options = []): void`

Définit le type de contenu de la réponse ainsi que les en-têtes de cache et de disposition associés. Utilisée en interne par `FileResponse()`, mais peut aussi être appelée directement.

- `$contentType` : alias reconnu (`json`, `manifest`, `html`, `image`, `javascript`, `css`, `woff2`, `text`, `video`, `pdf`, `xml`, `png`, `gif`, `svg`, `mp3`) ou type MIME arbitraire. Un `charset` est ajouté automatiquement pour `html`, `json`, `text`, `css`, `javascript` et `xml`.
- `$cacheMaxAge` :
  - si fourni, envoie `Cache-Control: public, max-age=...` et un en-tête `Expires` calculé en conséquence ;
  - si `null` (par défaut), désactive explicitement le cache (`no-store, no-cache, must-revalidate`).
- `$options` :
  - `charset` : jeu de caractères (`UTF-8` par défaut).
  - `disposition` : `'inline'` ou `'attachment'` (combiné à `filename` pour l'en-tête `Content-Disposition`).
  - `filename` : nom de fichier utilisé si `disposition` vaut `'attachment'`.
  - `custom_headers` : tableau associatif d'en-têtes supplémentaires.

```php
$res->set_content_type('pdf', 3600, [
    'disposition' => 'attachment',
    'filename'    => 'rapport.pdf'
]);
```

---

## 8. Redirections — `RedirectResponse()` / `redirect()`

### `RedirectResponse(string $url, int $statusCode = 302): void`
### `redirect(string $url, int $statusCode = 302): void`

Redirige le client vers `$url` avec le code de statut `$statusCode` (`302` par défaut). `redirect()` est un alias de `RedirectResponse()`.

> **Attention :** cette méthode appelle `exit` après l'envoi de l'en-tête `Location`, ce qui interrompt immédiatement l'exécution du script — aucun code placé après ne sera exécuté.

```php
$app->get('/ancienne-route', function (Response $res) {
    $res->redirect('/nouvelle-route', 301);
});
```

---

## 9. En-têtes personnalisés — `setHeader()`

### `setHeader($name, $value): void`

Envoie un en-tête HTTP arbitraire (`header("$name: $value")`).

```php
$res->setHeader('X-Powered-By', 'EorbahAPI');
```

---

## 10. Cookies — `cookie()` / `clearCookie()`

### `cookie(string $name, $value, array $options = []): void`

Définit un cookie côté client.

Options disponibles (fusionnées avec les valeurs par défaut) :

| Option     | Valeur par défaut |
|------------|--------------------|
| `expires`  | `0` (cookie de session) |
| `path`     | `/` |
| `domain`   | `''` |
| `secure`   | `true` |
| `httponly` | `true` |
| `samesite` | `'Strict'` |

```php
$res->cookie('session_token', $token, [
    'expires' => time() + 3600,
    'samesite' => 'Lax'
]);
```

> **Point d'attention :** l'implémentation actuelle assigne `$_COOKIE[$name] = $name` après l'appel à `setcookie()`, plutôt que `$value`. Le cookie envoyé au navigateur contient bien la bonne valeur, mais la variable superglobale `$_COOKIE` en mémoire pour la requête en cours ne reflète pas cette valeur avant le prochain rechargement de page — à garder en tête si vous relisez `$_COOKIE[$name]` immédiatement après l'avoir défini dans le même script.

### `clearCookie($name): void`

Supprime un cookie existant en le faisant expirer dans le passé (`time() - 3600`), avec les mêmes attributs de sécurité que `cookie()` (`path: /`, `secure: true`, `httponly: true`, `samesite: 'Strict'`). N'agit que si le cookie est présent dans `$_COOKIE`.

```php
$res->clearCookie('session_token');
```