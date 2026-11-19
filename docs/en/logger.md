# Journalisation

## Ce que vous allez apprendre

Utiliser `Eorbahapi\Logger` pour écrire des journaux structurés (JSON), filtrer
par niveau et partager le logger via le conteneur.

## Créer un logger

```php
use Eorbahapi\Logger;

$logger = new Logger('info');
```

Niveaux reconnus, du plus verbeux au plus critique :

| Niveau | Usage |
| --- | --- |
| `debug` | détail de développement |
| `info` | événement normal |
| `warning` | anomalie récupérable |
| `error` | échec d'une opération |
| `critical` | service dégradé |

Le niveau du constructeur est le **seuil** : un message de niveau inférieur est
ignoré (`new Logger('warning')` n'écrit ni `debug` ni `info`).

## Écrire un message

```php
$logger->info('Utilisateur connecté', ['user_id' => 42]);
$logger->warning('Quota presque atteint', ['quota' => 950, 'limite' => 1000]);
$logger->error('Échec du paiement', ['commande' => 'A-120', 'code' => 502]);
$logger->critical('Base de données indisponible');

// niveau dynamique
$logger->log('debug', 'Payload reçu', ['body' => $payload]);
```

Chaque entrée est écrite avec `error_log()` au format JSON :

```json
{"timestamp":"2024-05-21 10:32:01","date":"2024-05-21","level":"INFO","message":"Utilisateur connecté","context":{"user_id":42},"ip":"127.0.0.1","user_agent":"curl/8.5.0"}
```

Le contexte est libre : identifiants, durée, chemin, identifiant de requête.
L'adresse IP et le `User-Agent` sont ajoutés automatiquement depuis `$_SERVER`.

## Utiliser dans les handlers

Le logger se partage via le conteneur :

```php
use Eorbahapi\EorbahAPI;
use Eorbahapi\Logger;

$app = new EorbahAPI();
$app->register(Logger::class, new Logger('debug'));

$app->get('/items/{id}', function (int $id, Logger $logger) {
    $logger->info('Lecture article', ['id' => $id]);

    return ['id' => $id];
});
```

## Bonnes pratiques

- **un identifiant de requête** pour relier les lignes :
  ```php
  $logger->info('requête', ['request_id' => $request->getHeader('X-Request-Id')]);
  ```
- **jamais de secrets** : mots de passe, jetons et numéros de carte ne doivent
  pas entrer dans le contexte ;
- **niveau par environnement** : `debug` en développement, `info` ou `warning`
  en production ;
- **destination** : `error_log()` écrit selon la configuration `php.ini`
  (`error_log`, `syslog`). Pour un fichier dédié, configurez PHP ou branchez un
  handler personnalisé autour de `Logger`.

!!! warning "Niveau seuil"
    Le filtre compare le niveau du message à celui du logger : avec `info`,
    `debug` est ignoré ; avec `error`, seuls `error` et `critical` passent.

## Récapitulatif

- `new Logger($seuil = 'info')` crée un journaliseur JSON ;
- `debug()`, `info()`, `warning()`, `error()`, `critical()` et `log()` écrivent ;
- le seuil filtre les niveaux inférieurs ;
- le contexte est un tableau libre, enrichi de l'IP et du `User-Agent` ;
- `register(Logger::class, …)` le partage dans les handlers.

[Suivant : Utilitaires et statuts](utilities.md){ .md-button }
