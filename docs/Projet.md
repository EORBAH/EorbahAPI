# Projet

```
mon_projet/
├── packages/        # Dépendances locales (si installation via GitHub)
├── vendor/          # Dépendances Composer
├── public/index.php
├── src/             # Code source de l'application
├── main.php         # Point d'entrée de l'application
└── .env             # Variables d'environnement
```

`public/index.php`
```php
<?php

require_once dirname(__DIR__).'/main.php';
```
Exemple de fichier `.env` :

```
APP_DEBUG=true
APP_ENV=development
```

## pour le test
```bash
php -S localhost:8000 -t public/
```

## Deployer
pour le deployement sur les serveur mutualiser limiter vous avez ce choix

```
├── index.php
├── robots.txt
├── .htaccess.json
└── .eorbahapp/
    ├── .htaccess # deny all
    ├── ...
    └── main.php
```

`index.php`
```php
<?php

require_once __DIR__.'/main.php';
```