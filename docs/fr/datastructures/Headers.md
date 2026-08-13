# Headers

La classe `Eorbahapi\Datastructures\Headers` encapsule les en-têtes HTTP avec une clé insensible à la casse.

## Exemple

```php
use Eorbahapi\Datastructures\Headers;

$headers = new Headers([
    'Content-Type' => 'application/json',
    'X-Trace-Id' => 'abc-123',
]);

echo $headers->get('content-type'); // application/json

echo $headers->get('x-trace-id'); // abc-123
```

## Points importants

- Les clés sont normalisées en minuscules avec `-` à la place des `_`.
- L'objet est immuable : `offsetSet()` et `offsetUnset()` lèvent une exception.
- La liste peut être parcourue comme un tableau associatif.

```php
foreach ($headers as $name => $value) {
    echo "$name: $value\n";
}
```

## Construire depuis les variables globales

```php
$headers = Headers::fromGlobals();
$authorization = $headers->get('Authorization');
```
