# EorbahAPI — Classe `Logger`

NB: pour automatiser et rendre les logging plus intelligent un logging middleware sera mis en place et gereras automatioquement la journalisation au niveau global.

La classe `Logger` (`EorBah545\Eorbahapi\Logger`) fournit un système de journalisation (logging) simple, avec niveaux de sévérité, écriture au format JSON dans des fichiers quotidiens, et enrichissement automatique du contexte (IP, user-agent).

## Sommaire

1. [Instanciation](#1-instanciation)
2. [Niveaux de log](#2-niveaux-de-log)
3. [Méthode générique — log()](#3-méthode-générique--log)
4. [Méthodes raccourcies par niveau](#4-méthodes-raccourcies-par-niveau)
5. [Format et emplacement des fichiers de log](#5-format-et-emplacement-des-fichiers-de-log)
6. [Exemple d'intégration avec EorbahAPI](#6-exemple-dintégration-avec-eorbahapi)

---

## 1. Instanciation

### `__construct(string $logDir, string $logLevel = 'info')`

- `$logDir` : répertoire dans lequel les fichiers de log seront écrits. Il est créé automatiquement (récursivement, permissions `0755`) s'il n'existe pas encore.
- `$logLevel` : niveau minimal de sévérité à partir duquel un message est effectivement écrit (`'info'` par défaut). Insensible à la casse.

```php
use EorBah545\Eorbahapi\Logger;

$logger = new Logger(__DIR__ . '/storage/logs', 'debug');
```

---

## 2. Niveaux de log

Les niveaux sont ordonnés par sévérité croissante :

| Niveau     | Priorité |
|------------|----------|
| `debug`    | 1        |
| `info`     | 2        |
| `warning`  | 3        |
| `error`    | 4        |
| `critical` | 5        |

Un message n'est écrit que si la priorité de son niveau est **supérieure ou égale** à celle du `$logLevel` défini à la construction. Par exemple, avec un logger configuré en `'warning'`, les appels à `debug()` et `info()` sont silencieusement ignorés — seuls `warning()`, `error()` et `critical()` produisent une entrée.

> **Point d'attention :** la comparaison utilise `<` strict (`$this->levels[$level] < $this->levels[$this->logLevel]`) et non `<=`. Le niveau exactement égal au seuil configuré est donc bien conservé, ce qui correspond au comportement attendu — aucune action n'est requise de votre part, cette précision sert simplement à clarifier la logique.

---

## 3. Méthode générique — `log()`

### `log(string $level, string $message, array $context = []): void`

Écrit une entrée de log si le niveau `$level` est suffisant par rapport au `logLevel` configuré.

- `$level` : l'un des niveaux listés ci-dessus (`debug`, `info`, `warning`, `error`, `critical`).
- `$message` : le message à journaliser.
- `$context` : tableau associatif de données additionnelles (facultatif), inclus tel quel dans l'entrée JSON.

```php
$logger->log('warning', 'Tentative de connexion échouée', ['user' => 'amadou']);
```

> **Prérequis :** `$level` doit être une clé valide du tableau des niveaux. Un niveau inconnu (ex. `'trace'`) déclenchera une erreur PHP (clé indéfinie), la classe ne validant pas actuellement cette entrée.

---

## 4. Méthodes raccourcies par niveau

Chaque niveau dispose d'une méthode dédiée, équivalente à un appel à `log()` avec le niveau correspondant :

### `debug(string $message, array $context = []): void`
### `info(string $message, array $context = []): void`
### `warning(string $message, array $context = []): void`
### `error(string $message, array $context = []): void`
### `critical(string $message, array $context = []): void`

```php
$logger->debug('Requête reçue', ['route' => '/items/5']);
$logger->info('Utilisateur connecté', ['user_id' => 42]);
$logger->warning('Quota API bientôt atteint', ['restant' => 5]);
$logger->error('Échec de connexion à la base de données');
$logger->critical('Service de paiement indisponible', ['code' => 503]);
```

---

## 5. Format et emplacement des fichiers de log

Chaque entrée est écrite en une ligne JSON, ajoutée à la fin du fichier correspondant à la date du jour (rotation quotidienne automatique) :

```
{logDir}/AAAA-MM-JJ.log
```

Structure d'une entrée :

```json
{
    "timestamp": "2026-08-04 14:32:10",
    "level": "WARNING",
    "message": "Tentative de connexion échouée",
    "context": {"user": "amadou"},
    "ip": "192.168.1.10",
    "user_agent": "Mozilla/5.0 ..."
}
```

- `timestamp` : date et heure locales du serveur au format `Y-m-d H:i:s`.
- `level` : le niveau, mis en majuscules.
- `ip` : `$_SERVER['REMOTE_ADDR']`, ou `"unknown"` si indisponible (ex. exécution en ligne de commande).
- `user_agent` : `$_SERVER['HTTP_USER_AGENT']`, ou `"unknown"` si absent.

L'écriture utilise `FILE_APPEND | LOCK_EX`, ce qui garantit un ajout atomique et évite les écritures concurrentes corrompues en cas de requêtes simultanées.

---

## 6. Exemple d'intégration avec EorbahAPI

```php
use EorBah545\Eorbahapi\EorbahAPI;
use EorBah545\Eorbahapi\Logger;
use EorBah545\Eorbahapi\Request;
use EorBah545\Eorbahapi\Response;

$app = new EorbahAPI();
$logger = new Logger(__DIR__ . '/storage/logs', 'info');

$app->get('/items/{item_id}', function (Request $req, Response $res, $item_id) use ($logger) {
    $logger->info('Consultation d\'un item', ['item_id' => $item_id]);
    $res->json(['item_id' => $item_id]);
});
```

> **Astuce :** enregistrez le logger dans le conteneur de dépendances via `$app->register(Logger::class, $logger)` (voir la documentation principale, section *Injection de dépendances*) pour l'injecter directement par type-hint dans vos routes et middlewares, sans passer par `use (...)`.