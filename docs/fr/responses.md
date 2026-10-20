# Réponses

## Ce que vous allez apprendre

Choisir ce que renvoie un handler — tableau encodé en JSON, réponse typée,
HTML, redirection, flux — et piloter le statut, les en-têtes et le
`Content-Type` avec les helpers de `Eorbahapi\Response`.

## Le code

`docs_src/ResponsesExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Responses\HTMLResponse;
use Eorbahapi\Responses\JSONResponse;
use Eorbahapi\Responses\RedirectResponse;

/**
 * Réponses : statut, en-têtes, contenu, redirections.
 */
final class ResponsesExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Un tableau est encodé en JSON automatiquement.
        $app->get('/auto', fn () => ['ok' => true]);

        // Contrôle explicite du statut et des en-têtes.
        $app->post('/created', fn () => new JSONResponse(['created' => true], 201, ['X-Trace' => 'abc']));

        // Réponse HTML.
        $app->get('/html', fn () => new HTMLResponse('<h1>Bonjour</h1>'));

        // Redirection typée.
        $app->get('/go', fn () => new RedirectResponse('/auto', 307));

        // Redirection raccourcie : "redirect:code:url".
        $app->get('/short', fn () => 'redirect:301:/auto');

        // Texte brut.
        $app->get('/text', fn () => 'bonjour');

        return $app;
    }
}
```

Un handler n'est pas obligé de retourner un `Response` : le framework normalise
le retour. Un `array` ou un `object` devient un corps JSON, une chaîne devient un
corps texte, une chaîne préfixée par `redirect:` devient une redirection, et
`null` / `true` laissent la réponse en cours inchangée.

## Ce qui se passe

| Requête | Statut | Corps / en-têtes |
| --- | --- | --- |
| `GET /auto` | 200 | `{"ok":true}` |
| `POST /created` | 201 | `{"created":true}` + `X-Trace: abc` |
| `GET /html` | 200 | `<h1>Bonjour</h1>` + `Content-Type: text/html; charset=UTF-8` |
| `GET /go` | 307 | `Location: /auto` |
| `GET /short` | 301 | `Location: /auto` |
| `GET /text` | 200 | `bonjour` |

`JSONResponse` prend les données, un statut (200 par défaut) et un tableau
d'en-têtes ; il ajoute `Content-Type: application/json` sauf si vous le
fournissez déjà (`$headers['Content-Type'] ??= 'application/json'`).
`HTMLResponse` fait de même avec `text/html; charset=UTF-8`.
`RedirectResponse` enregistre l'URL dans `Location` et fixe le statut (302 par
défaut, `307` ici pour conserver la méthode et le corps).

La chaîne `redirect:code:url` produit le même résultat sans instancier de classe :
`'redirect:301:/auto'` découpe sur `:` en trois parties, applique le statut et
l'en-tête `Location`. Le corps, lui, reste la chaîne elle-même.

### Réponses en flux

Un `Generator` alimente `StreamingResponse` (`Eorbahapi\Responses\StreamingResponse`) :
chaque `yield` est envoyé au client puis vidé, ce qui convient aux gros contenus
et aux progressions. La signature est
`new StreamingResponse(\Generator $generator, int $status = 200, array $headers = [])`.

```php
$app->get('/chunks', fn () => new StreamingResponse((static function (): \Generator {
    yield 'a';
    yield 'b';
    yield 'c';
})()));
```

`EventStreamResponse` (`Eorbahapi\Responses\EventStreamResponse`) étend ce
comportement pour le Server-Sent Events (SSE) : il préremplit
`Content-Type: text/event-stream`, `Cache-Control: no-cache` et
`Connection: keep-alive`. Il ne reste qu'à produire des trames au format SSE
(`data: ...` suivi d'une ligne vide).

```php
$app->get('/events', fn () => new EventStreamResponse((static function (): \Generator {
    yield "data: ping\n\n";
})()));
```

Ces deux réponses n'ont pas de corps avant l'envoi : c'est `send()` — appelé par
`run()` — qui consomme le générateur et pousse chaque morceau. Sous `TestClient`,
`body()` reste donc vide ; un test vérifie la sortie, pas la réponse.

### Helpers de `Response`

| Méthode | Rôle |
| --- | --- |
| `status()` | statut HTTP courant |
| `body()` | corps brut (`string`, `array` ou `null`) |
| `headers()` | tableau des en-têtes |
| `text()` | corps en chaîne, les tableaux étant encodés en JSON |
| `set_content_type()` | `Content-Type` + `Cache-Control` en un appel |

`set_content_type('json')`, `'html'`, `'text'`, `'svg'`, `'png'`… acceptent un
nom court traduit en type MIME, avec `charset=UTF-8` ajouté pour les types
textuels. Sans âge de cache, l'appel pose `Cache-Control: no-store` et
`Pragma: no-cache` ; avec `set_content_type('pdf', 3600)`, il pose
`public, max-age=3600` et `Expires`. Les options `disposition`, `filename` et
`custom_headers` complètent le réglage.

!!! tip "Le plus court chemin d'abord"
    Tant que vous n'avez pas besoin d'un statut ou d'un en-tête particulier,
    retournez un tableau : l'encodage JSON et le corps sont pris en charge.
    Passez à `JSONResponse` dès qu'un statut (`201`) ou un en-tête maison peut
    être utile au client.

!!! warning "La redirection raccourcie ne transporte pas de corps"
    `'redirect:301:/auto'` sert uniquement à rediriger : le corps vaut la chaîne
    de redirection. Utilisez `new RedirectResponse($url, $status)` si vous voulez
    une réponse typée, et réservez les statuts `307` / `308` aux redirections
    qui doivent conserver la méthode et le corps de la requête.

## Récapitulatif

- un tableau ou un objet retourné est encodé en JSON automatiquement ;
- `JSONResponse($data, $status, $headers)` contrôle statut et en-têtes ;
- `HTMLResponse` fixe `text/html; charset=UTF-8` ;
- `RedirectResponse($url, $status)` et `'redirect:code:url'` posent `Location` ;
- `StreamingResponse` envoie un `Generator` par morceaux, `EventStreamResponse` en SSE ;
- `status()`, `body()`, `headers()`, `text()` et `set_content_type()` inspectent et ajustent la réponse.

[Suivant : Dépendances](dependencies.md){ .md-button }
