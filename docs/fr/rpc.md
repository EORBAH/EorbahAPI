# JSON-RPC

## Ce que vous allez apprendre

Exposer des fonctions PHP en JSON-RPC 2.0 avec `Eorbahapi\RPC\JsonRPC` :
enregistrer des méthodes avec `add_method()`, monter le serveur sous un préfixe,
comprendre comment les paramètres JSON sont résolus (nom, type, valeur par
défaut), valider `params` avec un modèle Svinx, lire le format des réponses —
succès, `-32600`, `-32601`, `-32602`, `-32603` — et
savoir pourquoi le statut HTTP reste `200` même quand la réponse est une erreur.

## Le code

`docs_src/RpcExample.php` déclare un modèle décrivant les entrées, puis monte un
serveur JSON-RPC sous `/rpc` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\RPC\JsonRPC;
use Eorbahapi\Params\Body;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

/**
 * Paramètres de la méthode JSON-RPC.
 */
final class RpcSumParams extends BaseModel
{
    #[Field(gte: 0)]
    public int $a = 0;

    #[Field(gte: 0)]
    public int $b = 0;
}

/**
 * Serveur JSON-RPC 2.0 monté sous /rpc.
 */
final class RpcExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $rpc = new JsonRPC();

        // Une méthode JSON-RPC est un callable dont les paramètres sont résolus
        // comme ceux d'une route (Params, Depends, modèles Svinx...).
        $rpc->add_method('math.sum', fn (int $a, int $b) => $a + $b);
        $rpc->add_method('greet', fn (string $name = 'monde') => 'Bonjour ' . $name);

        $app = new EorbahAPI();
        $app->mount('/rpc', $rpc);

        return $app;
    }
}
```

`JsonRPC` est une application montée, comme `StaticFiles` : `mount('/rpc', $rpc)`
lui injecte la requête et la réponse partagées, `process()` lit le corps de la
requête, choisit la méthode, appelle le callable et remplit la réponse. Rien n'est
envoyé : le pipeline décide.

`add_method(string $method, callable $callback)` associe un nom public (`math.sum`,
`greet`) à un callable, avec exactement les paramètres que vous voulez. Les
valeurs sont cherchées **par nom** dans `params` :

| Cas | Résultat |
| --- | --- |
| `params: ['a' => 2, 'b' => 3]` | `2` et `3` passés à la méthode |
| `params: ['n' => '7']` pour `int $n` | coercition en `7` |
| paramètre absent avec valeur par défaut | la valeur par défaut (`'monde'`) |
| paramètre absent sans défaut | erreur `-32603` (`Impossible de résoudre le paramètre …`) |
| clé présente dans `params` mais pas dans la signature | ignorée |
| paramètre typé `Request` / `Response` | injecté automatiquement |
| `#[Body] RpcSumParams $params` | `params` est validé par le modèle Svinx |
| validation en échec | erreur `-32602` (`Invalid params: …`) |

Les `params` de l'enveloppe deviennent le **corps** de la requête transmise au
résolveur (`Request::withBody()`) : les attributs de paramètre se comportent donc
comme sur une route — `#[Body] RpcSumParams $params` valide `params` avec les
contraintes de `Svinx\Validator`, et `Request::body()` expose les mêmes valeurs.
Seul `#[Query]` continue de lire la chaîne de requête HTTP, pas `params`.

## Ce qui se passe

```php
<?php
public static function scenarios(): array
{
    return [
        self::scenario('POST', '/rpc', ['json' => [
            'jsonrpc' => '2.0',
            'method' => 'math.sum',
            'params' => ['a' => 2, 'b' => 3],
            'id' => 1,
        ]], json: ['jsonrpc' => '2.0', 'result' => 5, 'id' => 1]),

        self::scenario('POST', '/rpc', ['json' => [
            'jsonrpc' => '2.0',
            'method' => 'greet',
            'params' => ['name' => 'Oumar'],
            'id' => 2,
        ]], json: ['jsonrpc' => '2.0', 'result' => 'Bonjour Oumar', 'id' => 2]),

        self::scenario('POST', '/rpc', ['json' => [
            'jsonrpc' => '2.0',
            'method' => 'math.validated',
            'params' => ['a' => 4, 'b' => 6],
            'id' => 4,
        ]], json: ['jsonrpc' => '2.0', 'result' => 10, 'id' => 4]),

        self::scenario('POST', '/rpc', ['json' => [
            'jsonrpc' => '2.0',
            'method' => 'math.validated',
            'params' => ['a' => -1],
            'id' => 5,
        ]], json: ['jsonrpc' => '2.0', 'error' => ['code' => -32602, 'message' => "Invalid params: Le champ 'a' doit être >= 0"], 'id' => 5]),

        self::scenario('POST', '/rpc', ['json' => [
            'jsonrpc' => '2.0',
            'method' => 'inconnue',
            'id' => 3,
        ]], json: ['jsonrpc' => '2.0', 'error' => ['code' => -32601, 'message' => 'Method not found'], 'id' => 3]),
    ];
}
```

| Requête | Statut | Corps |
| --- | --- | --- |
| `POST /rpc` · `{"jsonrpc":"2.0","method":"math.sum","params":{"a":2,"b":3},"id":1}` | 200 | `{"jsonrpc":"2.0","result":5,"id":1}` |
| `POST /rpc` · `{"jsonrpc":"2.0","method":"greet","params":{"name":"Oumar"},"id":2}` | 200 | `{"jsonrpc":"2.0","result":"Bonjour Oumar","id":2}` |
| `POST /rpc` · `{"jsonrpc":"2.0","method":"math.validated","params":{"a":4,"b":6},"id":4}` | 200 | `{"jsonrpc":"2.0","result":10,"id":4}` |
| `POST /rpc` · `{"jsonrpc":"2.0","method":"math.validated","params":{"a":-1},"id":5}` | 200 | `{"jsonrpc":"2.0","error":{"code":-32602,"message":"Invalid params: Le champ 'a' doit être >= 0"},"id":5}` |
| `POST /rpc` · `{"jsonrpc":"2.0","method":"inconnue","id":3}` | 200 | `{"jsonrpc":"2.0","error":{"code":-32601,"message":"Method not found"},"id":3}` |

Le corps de la réponse contient toujours `jsonrpc`, `id` et soit `result`, soit
`error`. Les codes d'erreur produits par le serveur :

| Code | Message | Déclencheur |
| --- | --- | --- |
| `-32600` | `Invalid Request` | corps sans `method`, ou `jsonrpc` différent de `"2.0"`, ou tableau (les lots ne sont pas pris en charge), ou requête sans corps JSON |
| `-32601` | `Method not found` | nom de méthode absent de `add_method()` |
| `-32602` | `Invalid params: …` | `params` rejetés par la validation Svinx (`#[Body]` sur un modèle) |
| `-32603` | `Internal error: …` | exception pendant la résolution des paramètres ou dans la méthode |

L'`id` de l'erreur reprend celui de la requête ; il vaut `null` quand la requête
est inexploitable — y compris lorsqu'aucune requête JSON n'a été envoyée (un
`GET /rpc`).

!!! warning "Le statut HTTP est `200`, même pour une erreur"
    JSON-RPC 2.0 distingue le transport et le protocole : l'appel a bien été
    traité, c'est la méthode qui a échoué. Le client doit donc lire le champ
    `error` du corps, pas se contenter du code HTTP. Une exception levée dans
    votre méthode devient un `-32603` dont le message reprend
    `Internal error: ` suivi du message d'origine — attention à ne pas y laisser
    fuiter de détail interne en production. Les erreurs de validation, elles,
    sont des `-32602` qui reprennent le premier message de Svinx.

!!! tip "Montage et préfixe"
    Le préfixe du montage sélectionne le serveur ; le chemin situé sous ce
    préfixe est ignoré, tout comme les paramètres de l'URL. Un serveur monté
    sous `/api/rpc` répond de la même façon sur `/api/rpc` et `/api/rpc/x`.
    Les `params` deviennent le corps de la requête pour le résolveur : `#[Body]`
    et `Request::body()` les voient. `#[Query]`, lui, lit toujours la chaîne de
    requête HTTP.

## Récapitulatif

- `new JsonRPC()` puis `$rpc->add_method($nom, $callable)`, et
  `$app->mount('/rpc', $rpc)` pour l'exposer ;
- les paramètres viennent de `params` par nom, avec coercition de type, valeur
  par défaut et injection de `Request`/`Response` ;
- `params` sert aussi de corps (`Request::withBody()`), donc `#[Body]` valide un
  modèle Svinx ;
- succès : `{"jsonrpc":"2.0","result":…,"id":…}` ;
- erreur : `{"jsonrpc":"2.0","error":{"code":…,"message":…},"id":…}` ;
- `-32600` requête invalide, `-32601` méthode inconnue, `-32602` paramètres
  invalides, `-32603` erreur interne ;
- le statut HTTP reste `200` : c'est le corps qui porte le résultat ou l'erreur.

[Suivant : Tests](testing.md){ .md-button }
