# EorbahAPI — Servir une Single Page Application (SPA)

Cette section explique comment configurer EorbahAPI pour servir une application frontend de type **Single Page Application** (React, Vue, Svelte, etc.) : les fichiers statiques compilés (JS, CSS, assets) d'un côté, et une route de secours (*fallback*) qui renvoie systématiquement `index.html` pour laisser le routage côté client (React Router, Vue Router…) prendre le relais.

## Sommaire

- [EorbahAPI — Servir une Single Page Application (SPA)](#eorbahapi--servir-une-single-page-application-spa)
  - [Sommaire](#sommaire)
  - [1. Principe général](#1-principe-général)
  - [2. Servir les fichiers statiques avec StaticFiles](#2-servir-les-fichiers-statiques-avec-staticfiles)
  - [3. Route de secours (catch-all) pour le routage client](#3-route-de-secours-catch-all-pour-le-routage-client)
  - [4. Ordre de déclaration des routes](#4-ordre-de-déclaration-des-routes)
  - [5. Exemple complet](#5-exemple-complet)
  - [6. Le dossier public](#6-le-dossier-public)

---

## 1. Principe général

Une SPA repose sur deux catégories de ressources, que le backend doit distinguer :

- **Les fichiers statiques compilés** (`bundle.js`, `style.css`, images, fonts…), produits par l'outil de build du frontend (Vite, Webpack…) dans un répertoire comme `frontend/dist/`.
- **Les routes applicatives** (`/dashboard`, `/profil/42`, `/parametres`…), qui n'existent pas physiquement côté serveur : c'est le JavaScript chargé par `index.html` qui les interprète et affiche la bonne vue, une fois dans le navigateur.

EorbahAPI gère ce cas de figure avec deux mécanismes complémentaires : `mount()` avec `StaticFiles` pour les assets, et une route générique avec un paramètre de type `path` pour tout le reste.

---

## 2. Servir les fichiers statiques avec StaticFiles

`StaticFiles` est une classe montable (voir la documentation principale, section *Monter des sous-applications avec mount*) qui expose le contenu d'un répertoire sous un préfixe d'URL donné.

```php
use Eorbahapi\StaticFiles;

$app->mount("/static", new StaticFiles("frontend/dist/"), "frontend");
```

- Premier argument de `mount()` : le préfixe d'URL sous lequel les fichiers seront accessibles (ici `/static`).
- `StaticFiles("frontend/dist/")` : le répertoire physique servi, généralement le dossier de sortie du build frontend.
- Troisième argument : un nom identifiant le montage (utile pour le débogage ou pour référencer l'application montée ailleurs dans le code).

Ainsi, un fichier présent à `frontend/dist/manifest.json` devient accessible via `GET /static/manifest.json`.

---

## 3. Route de secours (catch-all) pour le routage client

Toute URL qui ne correspond ni à une route API explicite, ni à un fichier statique monté sous `/static`, doit renvoyer le même document HTML (`index.html`) : c'est ce document qui charge le bundle JavaScript, lequel prend alors en charge l'affichage en fonction de l'URL.

Cela se fait avec une route paramétrée dont le type est `path` — contrairement à un paramètre de route classique (`{item_id}`), qui ne capture qu'un seul segment d'URL, le type `path` capture **l'intégralité du chemin restant**, y compris les slashes :

```php
$app->get("{full_path:path}", function (Request $req, Response $res) {
    $res->HTMLResponse('
    <!DOCTYPE html>
    <html>
    <body>
        <h1>Hello world</h1>
    </body>
    </html>
    ');
});
```

Ainsi, aussi bien `GET /dashboard` que `GET /profil/42/parametres` seront capturés par cette route unique et recevront le même document HTML.

> **En production**, le contenu retourné par cette route correspond typiquement au `index.html` généré par le build frontend, et non à un contenu codé en dur comme dans l'exemple ci-dessus :
>
> ```php
> $app->get("{full_path:path}", function (Request $req, Response $res) {
>     $res->FileResponse(__DIR__ . '/frontend/dist/index.html');
> });
> ```

---

## 4. Ordre de déclaration des routes

L'ordre de déclaration est déterminant :

1. Déclarez d'abord vos **routes API** explicites (`/api/...`, `/items/{item_id}`, etc.).
2. Déclarez ensuite le **montage des fichiers statiques** (`mount("/static", ...)`).
3. Déclarez la **route catch-all** (`{full_path:path}`) **en tout dernier**.

Ce dernier point est rappelé explicitement dans le code source :

> **toujours mettre le `fullpath` en bas**

En effet, comme la route `{full_path:path}` capture n'importe quelle URL, toute route déclarée après elle deviendrait inatteignable — elle serait systématiquement interceptée en premier par le fallback.

---

## 5. Exemple complet

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\EorbahAPI;
use Eorbahapi\StaticFiles;
use Eorbahapi\ExceptionHandlers;

$app = new EorbahAPI();

$exceptionHandlers = new ExceptionHandlers();
$exceptionHandlers->overrideExceptionHandlers($app);

// Fichiers statiques compilés (ex. manifest.json, bundle.js, style.css...)
$app->mount("/static", new StaticFiles("frontend/dist/"), "frontend");

// Route de secours : renvoie index.html pour laisser le routage client s'exécuter
$app->get("{full_path:path}", function (Request $req, Response $res) {
    $res->HTMLResponse('
    <!DOCTYPE html>
    <html>
    <body>
        <h1>Hello world</h1>
    </body>
    </html>
    ');
});

$app->run();
```

Chargement du fichier `.env` avec `vlucas/phpdotenv` : cet exemple utilise `Dotenv::createImmutable()` pour charger les variables définies dans `.env` (voir la documentation principale, section *Structure du projet*) avant l'instanciation de `EorbahAPI`.

---

## 6. Le dossier public

Certains fichiers doivent être accessibles **directement à la racine du domaine** plutôt que sous un préfixe comme `/static` — c'est le cas notamment de :

- `manifest.json` (référencé de façon rigide par les navigateurs pour les PWA) ;
- `robots.txt` (attendu à la racine par les robots d'indexation) ;
- `favicon.ico`, `sitemap.xml`, ou tout autre fichier soumis à une convention d'URL fixe.

Pour ces fichiers, placez-les dans un dossier `public/` dédié plutôt que dans `frontend/dist/` monté sous `/static`, afin qu'ils soient servis à la racine (`/robots.txt`, `/manifest.json`) et non sous `/static/robots.txt`.

```
mon_projet/
├── public/          # Fichiers accessibles tels quels à la racine
│   ├── index.php
│   ├── robots.txt
│   ├── favicon.ico
│   └── manifest.json
├── frontend/
│   └── dist/        # Build compilé, monté sous /static
├── src/
├── main.php
└── .env
```