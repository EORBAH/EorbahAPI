# Address

La classe `Eorbahapi\Datastructures\Address` représente une adresse réseau simple avec un hôte et un port.

## Utilisation

```php
use Eorbahapi\Datastructures\Address;

$address = new Address('127.0.0.1', 8080);

printf("%s\n", (string) $address); // 127.0.0.1:8080
printf("%s\n", $address->getHost()); // 127.0.0.1
printf("%d\n", $address->getPort()); // 8080
```

## Méthodes principales

- `getHost(): ?string`
- `getPort(): ?int`
- `isComplete(): bool`
- `destructure(): array`
- `fromServerGlobal(): self`

## Exemple avec les variables du serveur

```php
$client = Address::fromServerGlobal();
if ($client->isComplete()) {
    echo $client->getHost();
}
```

La méthode `fromServerGlobal()` lit les variables PHP usuelles (`REMOTE_ADDR`, `HTTP_X_FORWARDED_FOR`, `REMOTE_PORT`) et reconstruit une adresse client.
