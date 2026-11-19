# Cache

## Ce que vous allez apprendre

Activer le cache d'EorbahAPI avec `new EorbahAPI(cache: true)`, choisir son
dossier et sa durée de vie, utiliser `cache_manager()` pour mémoriser vos
propres calculs, et comprendre ce que le framework met déjà en cache pour
accélérer les requêtes.

## Le code

`docs_src/CacheExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;

/**
 * Cache de l'application, activé par `new EorbahAPI(cache: true)`.
 *
 * Le cache fichier reprend le principe du cache de Twig : la valeur calculée
 * est écrite dans un fichier PHP, relu ensuite par un simple `include`. Il sert
 * au schéma OpenAPI et à l'accélération des requêtes (routes déjà appariées,
 * réflexion des paramètres).
 */
final class CacheExample extends DocsExample
{
    /**
     * Dossier de cache de l'exemple (en production : `cache/eorbahapi`).
     */
    public static function cache_dir(): string
    {
        return sys_get_temp_dir() . '/eorbahapi_example_cache';
    }

    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI(
            title: 'Catalogue',
            cache: true,
            cache_dir: self::cache_dir(),
            cache_ttl: 3600,
        );

        $app->get('/articles/{id}', fn (int $id) => ['id' => $id]);

        // La valeur n'est calculée qu'une fois, puis servie depuis le fichier.
        $app->get('/rapport', function () use ($app) {
            return $app->cache_manager()->remember('rapport', fn () => [
                'genere' => '2024-01-01',
                'lignes' => 3,
            ]);
        });

        // Le schéma OpenAPI est mis en cache fichier dès le premier appel.
        $app->get('/spec', fn () => $app->openapi());

        return $app;
    }
}
```

## Ce qui se passe

| Requête | Statut | Corps |
| --- | --- | --- |
| `GET /articles/7` | 200 | `{"id": 7}` |
| `GET /rapport` | 200 | `{"genere": "2024-01-01", "lignes": 3}` |
| `GET /rapport` (bis) | 200 | valeur servie depuis le cache |
| `GET /spec` | 200 | spécification OpenAPI |

La configuration tient en trois arguments :

- `cache: true` active un magasin fichier ; `false` (défaut) utilise `NullCache`,
  qui recalcule tout et ne change rien au comportement de l'application ;
- `cache_dir` choisit le dossier (`cache/eorbahapi` par défaut, relatif au
  répertoire courant) ;
- `cache_ttl` fixe une durée de vie en secondes (`null` ou `0` : pas d'expiration).

Chaque entrée est un fichier PHP qui `return` sa valeur : un `include` est très
rapide et profite de l'opcache, exactement comme les gabarits Twig compilés.
L'écriture est atomique (fichier temporaire puis `rename`) et une entrée
expirée est supprimée à la lecture.

## Ce que le framework met déjà en cache

Quand `cache: true` est actif, EorbahAPI l'utilise pour ses propres calculs :

- **le schéma OpenAPI** : la spécification n'est régénérée que si les routes,
  les options ou les fichiers des handlers changent (empreinte SHA-256). Les
  pages Swagger UI et ReDoc servies automatiquement lisent ce même schéma ;
- **les routes déjà appariées** : un runner persistant (Swoole, RoadRunner)
  évite de relancer les expressions régulières à chaque requête ; ce cache
  mémoire est borné pour ne pas grandir sans limite ;
- **la réflexion des paramètres** : les descripteurs de chaque handler sont
  construits une seule fois par processus, puis réutilisés par le résolveur de
  dépendances et par le générateur OpenAPI.

!!! tip "Cache activé, comportement identique"
    Le cache ne change jamais ce que l'API répond, seulement le temps de calcul.
    Vous pouvez donc l'activer en production sans adapter votre code : les
    valeurs manquantes sont recalculées, et une entrée corrompue ou illisible est
    ignorée.

!!! warning "Invalidez explicitement vos propres données"
    EorbahAPI ne connaît pas vos données métier : si vous mettez en cache un
    résultat qui dépend d'une table, appelez `delete()` quand la table change.
    Le changement de fichier source d'un handler invalide, lui, automatiquement
    le schéma OpenAPI.

## Utiliser `cache_manager()`

L'accès au magasin passe par `cache_manager()` :

```php
$articles = $app->cache_manager()->remember('articles', fn () => $blog->articles(), 600);

$app->cache_manager()->set('cle', 'valeur', ttl: 60);
$app->cache_manager()->get('cle', défaut: 'inconnu');
$app->cache_manager()->delete('cle');
$app->cache_manager()->clear();
```

`remember()` exécute le calcul uniquement lorsque la clé est absente ou périmée.
Sans cache activé, la valeur est recalculée à chaque appel : le code reste le
même dans les deux environnements.

Un magasin injecté est également accepté, par exemple un cache en mémoire pour
les tests ou un client Redis :

```php
use Eorbahapi\Cache\ArrayCache;
use Eorbahapi\Cache\CacheManager;

$app = new EorbahAPI();
$app->set_cache_manager(new CacheManager(true, store: new ArrayCache()));
```

## Récapitulatif

- `new EorbahAPI(cache: true)` active un cache fichier ; `cache_dir` et
  `cache_ttl` le configurent ;
- `cache_manager()->remember()` mémorise un calcul ; `get`, `set`, `delete` et
  `clear` composent le contrat ;
- le schéma OpenAPI, l'appariement des routes et la réflexion sont mis en cache
  par le framework ;
- le cache fichier écrit des fichiers PHP atomiques, relus par `include` ;
- `NullCache` est le comportement par défaut : aucun cache, aucune surprise.

[Suivant : Gestion des erreurs](errors.md){ .md-button }
