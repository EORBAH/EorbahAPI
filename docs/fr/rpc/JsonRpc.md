# JsonRpc

`Eorbahapi\RPC\JsonRPC` fournit un serveur JSON-RPC minimal compatible avec le modèle de routeur du framework.

## Utilisation

```php
use Eorbahapi\RPC\JsonRPC;

$rpc = new JsonRPC();
$rpc->add_method('ping', function () {
    return ['pong' => true];
});

// Le serveur répond dans le flux HTTP normal.
```

## Exemple complet

```php
$app = new Eorbahapi\EorbahAPI();
$rpc = new Eorbahapi\RPC\JsonRPC();

$rpc->add_method('user.get', function (int $id) {
    return ['id' => $id, 'name' => 'Alice'];
});

$app->mount('/rpc', $rpc);
$app->run();
```

### Requête typique

```http
POST /rpc
Content-Type: application/json

{
  "jsonrpc": "2.0",
  "method": "user.get",
  "params": {"id": 42},
  "id": 1
}
```

## Réponse

```json
{
  "jsonrpc": "2.0",
  "result": {"id": 42, "name": "Alice"},
  "id": 1
}
```

Le serveur valide la présence de `method`, le format JSON-RPC et renvoie une erreur structurée sur les cas invalides.
