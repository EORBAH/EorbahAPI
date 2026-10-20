# CLI

Exemple de documentation pour une interface en ligne de commande.

```bash
docs build ./content --out ./dist
docs serve --port 4000
docs new page "Ma nouvelle page" --group guide
```

**PHP languages:**
```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Testing\TestClient;

$app = new EorbahAPI();

$app->get('/users', function () {
    return ['Hello' => 'world'];
});

$client = new TestClient($app);

$response = $client->get('/users');
print_r($response->text());
```

| Commande | Description |
|---|---|
| `docs build` | Génère les pages statiques |
| `docs serve` | Lance un serveur local |
| `docs new page` | Crée un nouveau fichier markdown et l'ajoute au nav |

:::callout{type="warning" title="En développement"}
Cette CLI est un exemple illustratif — remplace cette page par la doc de
ton propre outil.
:::
