# Eorbahapi

## Example
`main.php`
```php
<?php
use Eorbahapi\EorbahAPI;
use Svinx\Validator\BaseModel;

$app = new EorbahAPI();

class Item extends BaseModel {
    public string $name;
    public float $price;
    public ?bool $is_offer = null;
}

$app->get("/", function () {
    return ["Hello" => "World"];
});

$app->get("/items/{item_id:int}", function(int $item_id, ?string $q = null) {
    return ["item_id" => $item_id, "q" => $q];
});

$app->put("/items/{item_id}", function(int $item_id, Item $item) {
    return ["item_name" => $item->name, "item_id" => $item_id];
});
```

## Runing your app
### With server
```bash
# eorbahapi server <entry_file>:<objet_application> --host <host> --port <port> --workers <nombre_worker>
eorbahapi server main:app --host 0.0.0.0 --port 8000 --workers 4 --reload --adapter 'swoole|default'
```

### With script
```php
if (PHP_SAPI === 'cli') {
    // Sevreur alive avec swoole
    use Eorbahapi\ESGIApp\Server\ESGIAppRunner;
    use Eorbahapi\ESGIApp\Adapter\SwooleAdapter;  // RoadRunner, FrakenPHP 

    $runner = new ESGIAppRunner(app: $app, adapter: new SwooleAdapter());
    $runner->run(
        port: 8080,
        host: '127.0.0.1',
        reload: true,
        workers: 4
    );
} else {
    // utilisation non alive avec SAPIs classiquea
    $app->run(); // default PHP-FPM
}
```

Executer le script:
```bash
php main.php
```