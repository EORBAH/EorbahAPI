# EorbahAPI — Classe `Request`

La classe `Request` (`Eorbahapi\Request`) représente la requête HTTP entrante. Elle est injectée automatiquement dans vos routes et middlewares, et donne accès aux paramètres de route, au corps de la requête, à la query string, aux en-têtes, aux cookies, aux fichiers uploadés et à la session.

## Sommaire

- [EorbahAPI — Classe `Request`](#eorbahapi--classe-request)
  - [Sommaire](#sommaire)
  - [1. Paramètres de route — `params()`](#1-paramètres-de-route--params)
    - [`params($value = null)`](#paramsvalue--null)
  - [2. Corps de la requête — `body()`](#2-corps-de-la-requête--body)
    - [`body(?string $key = null, $default = null)`](#bodystring-key--null-default--null)
  - [3. Paramètres de requête (query string) — `query()` / `query_string()`](#3-paramètres-de-requête-query-string--query--query_string)
    - [`query(?string $key = null, $default = null)`](#querystring-key--null-default--null)
    - [`query_string()`](#query_string)
  - [4. Données POST — `post()` / `FormData()`](#4-données-post--post--formdata)
    - [`post()`](#post)
    - [`FormData()`](#formdata)
  - [5. Authentification — `getBearerToken()`](#5-authentification--getbearertoken)
    - [`getBearerToken()`](#getbearertoken)
  - [6. Informations sur la requête — `method()` / `uri()` / `path()`](#6-informations-sur-la-requête--method--uri--path)
    - [`method()`](#method)
    - [`uri()`](#uri)
    - [`path()`](#path)
  - [7. En-têtes HTTP — `getHeader()` / `header()`](#7-en-têtes-http--getheader--header)
    - [`getHeader($key = null)`](#getheaderkey--null)
    - [`header($key = null)`](#headerkey--null)
  - [8. Cookies — `cookie()`](#8-cookies--cookie)
    - [`cookie($key = null)`](#cookiekey--null)
  - [9. Entrée combinée — `input()`](#9-entrée-combinée--input)
    - [`input($key, $default = null)`](#inputkey-default--null)
  - [10. Fichiers uploadés — `File()`](#10-fichiers-uploadés--file)
    - [`File($key = null)`](#filekey--null)
  - [11. Session — `getSession()` / `session()` / `setSessionValue()`](#11-session--getsession--session--setsessionvalue)
    - [`getSession(): array`](#getsession-array)
    - [`session(string $key, $default = null)`](#sessionstring-key-default--null)
    - [`setSessionValue(string $key, $value): void`](#setsessionvaluestring-key-value-void)

---

## 1. Paramètres de route — `params()`

### `params($value = null)`

Accède aux paramètres extraits de la route (ex. `{item_id}` dans `/items/{item_id}`), stockés dans la propriété publique `$segments`.

- Sans argument : retourne le tableau complet des paramètres.
- Avec une chaîne (`$value`) : retourne la valeur du paramètre correspondant.
- Avec un tableau : remplace `$segments` par ce tableau (utilisé en interne par le routeur pour injecter les paramètres résolus).

```php
$app->get('/items/{item_id}', function (Request $req, Response $res) {
    $itemId = $req->params('item_id');
    $res->json(['item_id' => $itemId, 'all' => $req->params()]);
});
```

> **Note :** `params('cle_inexistante')` déclenche un avertissement PHP si la clé n'existe pas dans `$segments`, car la valeur n'est pas protégée par `??`. Préférez une clé dont vous êtes certain de la présence dans la route.

---

## 2. Corps de la requête — `body()`

### `body(?string $key = null, $default = null)`

Récupère le corps de la requête (payload), en tentant d'abord un décodage JSON. Si le corps n'est pas du JSON valide, la méthode se rabat sur `$_POST` (utile pour les formulaires HTML classiques, `application/x-www-form-urlencoded`).

- Le corps est mis en cache après le premier appel (`bodyCache`) : `file_get_contents("php://input")` n'est donc lu qu'une seule fois par requête, quel que soit le nombre d'appels à `body()`.
- Sans clé : retourne le tableau complet du corps.
- Avec une clé : retourne la valeur correspondante, ou `$default` si absente.

```php
$app->post('/items', function (Request $req, Response $res) {
    $name = $req->body('name', 'Sans nom');
    $payload = $req->body(); // tableau complet
    $res->json(['name' => $name]);
});
```

---

## 3. Paramètres de requête (query string) — `query()` / `query_string()`

### `query(?string $key = null, $default = null)`

Accède aux paramètres de l'URL (`$_GET`).

- Sans clé : retourne le tableau complet.
- Avec une clé : retourne la valeur correspondante, ou `$default` si absente.

```php
$app->get('/items/{item_id}', function (Request $req, Response $res, $item_id) {
    $q = $req->query('q', 'valeur_par_defaut');
    $res->json(['item_id' => $item_id, 'q' => $q]);
});
```

### `query_string()`

Retourne la chaîne de requête brute (`$_SERVER['QUERY_STRING']`), telle quelle (non parsée).

```php
$req->query_string(); // ex. "q=chaussure&page=2"
```

---

## 4. Données POST — `post()` / `FormData()`

### `post()`

Retourne directement `$_POST`.

### `FormData()`

Alias de `post()`.

```php
$app->post('/contact', function (Request $req, Response $res) {
    $data = $req->FormData(); // équivalent à $req->post()
    $res->json($data);
});
```

---

## 5. Authentification — `getBearerToken()`

### `getBearerToken()`

Extrait le jeton d'un en-tête `Authorization: Bearer <token>`. Retourne le jeton (`string`) s'il est présent et correctement formé, sinon `null`.

```php
$app->get('/protected', function (Request $req, Response $res) {
    $token = $req->getBearerToken();
    if ($token === null) {
        $res->status(401)->send('Jeton manquant');
        return;
    }
    $res->json(['token' => $token]);
});
```

---

## 6. Informations sur la requête — `method()` / `uri()` / `path()`

### `method()`

Retourne la méthode HTTP de la requête (`$_SERVER['REQUEST_METHOD']`), ex. `"GET"`, `"POST"`.

### `uri()`

Retourne l'URI brute de la requête, y compris la query string (`$_SERVER['REQUEST_URI']`), ex. `"/items/5?q=chaussure"`.

### `path()`

Retourne uniquement le chemin de l'URI, sans la query string, et sans slash final (`rtrim(..., '/')`).

```php
$req->method(); // "GET"
$req->uri();    // "/items/5?q=chaussure"
$req->path();   // "/items/5"
```

---

## 7. En-têtes HTTP — `getHeader()` / `header()`

### `getHeader($key = null)`

Retourne les en-têtes HTTP de la requête via `getallheaders()`.

- Sans clé : retourne le tableau complet des en-têtes.
- Avec une clé : retourne la valeur de l'en-tête correspondant, ou `null` si absent.

### `header($key = null)`

Alias de `getHeader()`.

```php
$contentType = $req->header('Content-Type');
$allHeaders = $req->header();
```

> **Prérequis :** `getallheaders()` dépend de l'environnement (nativement disponible sous Apache/PHP-FPM avec certaines configurations ; peut nécessiter un polyfill selon le serveur utilisé, ex. le serveur de développement intégré de PHP).

---

## 8. Cookies — `cookie()`

### `cookie($key = null)`

Accède aux cookies envoyés par le client (`$_COOKIE`).

- Sans clé : retourne le tableau complet des cookies.
- Avec une clé : retourne la valeur correspondante, ou `null` si absente.

```php
$sessionToken = $req->cookie('session_token');
```

---

## 9. Entrée combinée — `input()`

### `input($key, $default = null)`

Recherche une valeur dans le corps de la requête (`body()`), puis dans `$_POST`, puis dans `$_GET`, dans cet ordre de priorité (les clés de `$_GET` écrasent celles de `$_POST`, qui écrasent celles du corps, du fait de l'ordre de fusion par `array_merge`).

```php
$name = $req->input('name', 'Invité');
```

> **Point d'attention :** contrairement à `body()`, `query()` ou `post()` pris isolément, `input()` fusionne trois sources différentes. Si une même clé peut provenir de plusieurs sources avec des valeurs différentes, préférez une méthode dédiée (`body()`, `query()` ou `post()`) pour éviter toute ambiguïté sur l'origine de la donnée.

---

## 10. Fichiers uploadés — `File()`

### `File($key = null)`

Accède aux fichiers envoyés via un formulaire `multipart/form-data` (`$_FILES`).

- Sans clé : retourne le tableau complet des fichiers.
- Avec une clé : retourne les informations du fichier correspondant (`name`, `type`, `tmp_name`, `error`, `size`), ou `null` si absent.

```php
$app->post('/upload', function (Request $req, Response $res) {
    $file = $req->File('avatar');
    if ($file === null) {
        $res->status(400)->send('Aucun fichier reçu');
        return;
    }
    move_uploaded_file($file['tmp_name'], "storage/{$file['name']}");
    $res->json(['uploaded' => $file['name']]);
});
```

---

## 11. Session — `getSession()` / `session()` / `setSessionValue()`

Ces méthodes nécessitent que la session PHP soit démarrée (voir `SessionMiddleware` dans la documentation principale du framework).

### `getSession(): array`

Retourne l'ensemble des données de session (`$_SESSION`).

### `session(string $key, $default = null)`

Retourne la valeur associée à `$key` dans la session, ou `$default` si absente.

### `setSessionValue(string $key, $value): void`

Définit une valeur dans la session.

```php
$app->post('/login', function (Request $req, Response $res) {
    $req->setSessionValue('user', ['id' => 1, 'name' => 'Amadou']);
    $res->json(['status' => 'connecté']);
});

$app->get('/profil', function (Request $req, Response $res) {
    $user = $req->session('user');
    if ($user === null) {
        $res->status(401)->send('Non connecté');
        return;
    }
    $res->json($user);
});
```