# Cache

## Ce que vous allez apprendre

Activer le cache de l'application, choisir son dossier et sa durée de vie,
mémoriser vos propres calculs et comprendre ce que le framework met déjà en
cache.

## Activer le cache

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;

$app = new EorbahAPI(
    title: 'Catalogue',
    cache: true,
    cache_dir: __DIR__ . '/cache/eorbahapi',
    cache_ttl: 3600,
);
```

| Option | Défaut | Rôle |
| --- | --- | --- |
| `cache` | `false` | active le cache fichier |
| `cache_dir` | `cache/eorbahapi` | dossier des entrées |
| `cache_ttl` | `0` | durée de vie en secondes (`0`/`null` : pas d'expiration) |

Sans cache (`false`), l'application utilise `NullCache` : tout est recalculé,
aucun fichier n'est écrit.

## Mémoriser un calcul

`cache_manager()` expose la façade :

```php
<?php

$app->get('/rapport', function () use ($app) {
    return $app->cache_manager()->remember('rapport', fn () => [
        'genere' => date('Y-m-d'),
        'lignes' => 3,
    ], 600);
});
```

Le calcul n'a lieu qu'au premier appel ; les suivants lisent le fichier.

Opérations disponibles :

```php
$cache = $app->cache_manager();

$cache->set('clé', 'valeur', ttl: 60);
$cache->get('clé', 'défaut');
$cache->has('clé');
$cache->delete('clé');
$cache->clear();
$cache->remember('clé', fn () => calculer());
```

`remember()` exécute le calcul si la clé est absente ou périmée, puis le stocke.

## Le magasin fichier

`FileCache` écrit des fichiers PHP atomiques (fichier temporaire puis `rename`) :

```php
use Eorbahapi\Cache\FileCache;

$cache = new FileCache(__DIR__ . '/cache/eorbahapi', ttl: 3600);
$cache->remember('clé', fn () => ['ok' => true]);
```

Chaque fichier `return` sa valeur : un `include` est très rapide et profite de
l'opcache, comme les templates Twig compilés. Les valeurs exportables
(tableaux, scalaires, `stdClass` vide) sont acceptées ; un objet complet est
refusé et sera simplement recalculé.

Autres magasins : `ArrayCache` (mémoire du processus) et `NullCache`
(désactivé).

## Ce que le framework met en cache

- **le schéma OpenAPI** : invalidé par l'empreinte des routes, des options et des
  fichiers des handlers ;
- **les routes déjà appariées** : dans un runner persistant, les regex ne sont
  pas relancées à chaque requête (cache mémoire borné) ;
- **la réflexion des paramètres** : les descripteurs d'un handler sont construits
  une fois par processus.

Rien de tout cela ne change la réponse HTTP : seul le temps de calcul varie.

## Magasins externes

Les adaptateurs permettent de brancher Redis ou la mémoire sans changer le code
applicatif :

```php
<?php

use Eorbahapi\Adapter\Cache\RedisCacheAdapter;
use Eorbahapi\Cache\CacheManager;

$adapter = new RedisCacheAdapter(['host' => '127.0.0.1', 'port' => 6379]);
$app->set_cache_manager(new CacheManager(true, store: $adapter));
```

`RedisCacheAdapter` sérialise les valeurs et préfixe les clés
(`eorbahapi:` par défaut).

## Invalidation

Le cache ne connaît pas vos données métier : supprimez explicitement les clés
concernées quand une donnée change.

```php
<?php

$app->post('/articles', function (#[Body] Article $article) use ($app) {
    $app->cache_manager()->delete('rapport');

    return ['created' => true];
});
```

Le changement d'un fichier source invalidant le schéma OpenAPI est automatique.

!!! warning "Un cache mal invalidé est un bug métier"
    Préférez des clés précises (`articles:42`) et des TTL courts aux grands
    caches fourre-tout. `clear()` vide tout le magasin.

## Récapitulatif

- `new EorbahAPI(cache: true)` active le cache fichier ;
- `cache_dir` et `cache_ttl` le configurent ;
- `cache_manager()->remember()` mémorise un calcul ; `get`, `set`, `delete` et
  `clear` composent le contrat ;
- le framework cache le schéma OpenAPI, les routes et la réflexion ;
- les adaptateurs Redis/mémoire/fichier sont interchangeables.

[Suivant : Contrats et adaptateurs](contracts.md){ .md-button }
