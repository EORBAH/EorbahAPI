# Utilitaires et statuts

## Ce que vous allez apprendre

Utiliser `Status` (constantes et tests HTTP), `Utils` (chemins, asynchronisme) et
les structures de `Eorbahapi\Datastructures`.

## `Eorbahapi\Status`

Constantes pour les codes HTTP courants :

```php
use Eorbahapi\Status;

Status::HTTP_200_OK;          // 200
Status::HTTP_201_CREATED;     // 201
Status::HTTP_204_NO_CONTENT;  // 204
Status::HTTP_400_BAD_REQUEST; // 400
Status::HTTP_401_UNAUTHORIZED;// 401
Status::HTTP_404_NOT_FOUND;   // 404
Status::HTTP_422_UNPROCESSABLE_ENTITY; // 422
Status::HTTP_500_INTERNAL_SERVER_ERROR; // 500
```

Helpers :

```php
Status::text(404);            // 'Not Found'
Status::text(999, 'Inconnu'); // 'Inconnu'
Status::isSuccess(204);       // true
Status::isRedirect(301);      // true
Status::isClientError(422);   // true
Status::isServerError(503);   // true
Status::isError(404);         // true
```

Utilisation typique :

```php
use Eorbahapi\Responses\JSONResponse;
use Eorbahapi\Status;

$app->get('/health', fn () => new JSONResponse(
    ['status' => 'ok'],
    Status::HTTP_200_OK
));
```

## `Eorbahapi\Utils`

### Reconstruire un chemin

```php
use Eorbahapi\Utils;

Utils::getRoutePath('/users/{id}', ['id' => 7]);                 // '/users/7'
Utils::getRoutePath('/files/{chemin:path}', ['chemin' => 'a b']); // '/files/a%20b'
```

Les placeholders manquants restent intacts.

### Normaliser un chemin

```php
Utils::normalizePath('/users/'); // '/users'
Utils::normalizePath('/');       // ''
```

Même règle que `APIRouter::normalizePath()`.

### Détecter un callable asynchrone

```php
$generator = static fn (): \Generator => (function () { yield 1; })();

Utils::isAsyncCallable($generator); // true
Utils::isAsyncCallable(static fn (): int => 1); // false
```

Utile aux adaptateurs qui doivent savoir si un handler retourne un générateur
(ou une promesse) avant de choisir leur boucle d'exécution.

## Structures de données

### `UploadFile`

Déjà couvert dans [Fichiers](files.md) : `filename`, `content_type`, `read()`,
`write()`, `seek()`, `close()`.

### `Headers`

Représentation des en-têtes, insensible à la casse, itérable et comptable :

```php
use Eorbahapi\Datastructures\Headers;

$headers = new Headers(['Content-Type' => 'application/json', 'X-Trace' => 'abc']);

$headers->get('content-type');   // 'application/json'
$headers->has('X-Trace');        // true
$headers->getAll('x-trace');     // ['abc']
$headers->toArray();             // en-têtes normalisés
count($headers);                 // 2
```

`Headers` est immuable : `setHeader()` retourne une nouvelle instance.

### `Address`

Adresse `host:port` simple :

```php
use Eorbahapi\Datastructures\Address;

$address = new Address('127.0.0.1', 8000);

$address->getHost();      // '127.0.0.1'
$address->getPort();      // 8000
$address->isComplete();   // true
(string) $address;        // '127.0.0.1:8000'
```

### `FormData`

Petit conteneur clé/valeur pour les formulaires :

```php
use Eorbahapi\Datastructures\FormData;

$form = new FormData(['nom' => 'Oumar']);
$form->get('nom');   // 'Oumar'
$form->set('age', 27);
$form->all();        // ['nom' => 'Oumar', 'age' => 27]
```

`State`, `URL` et `QueryParams` sont des espaces réservés : ils existent pour
stabiliser les types futurs et ne proposent pas encore d'API publique.

## Récapitulatif

- `Status` fournit les constantes HTTP, libellés et tests de plage ;
- `Utils::getRoutePath()` remplit un gabarit, `normalizePath()` le normalise,
  `isAsyncCallable()` détecte les générateurs/promesses ;
- `Headers`, `Address` et `FormData` outillent les cas courants ;
- `UploadFile` est la structure des téléversements ;
- les espaces réservés (`State`, `URL`, `QueryParams`) ne sont pas encore
  utilisables.

[Suivant : Référence API](reference.md){ .md-button }
