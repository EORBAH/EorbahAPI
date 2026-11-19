# Tâches d'arrière-plan

## Ce que vous allez apprendre

Exécuter du code après avoir produit la réponse — notifications, écritures
différées, traitements légers — sans faire attendre le client.

## Pourquoi des tâches d'arrière-plan

Certaines opérations n'ont pas besoin d'être terminées avant la réponse :
envoyer un e-mail de bienvenue, journaliser, recalculer un cache, notifier un
webhook. Les exécuter après la réponse réduit la latence perçue.

## Mettre en place les tâches

Contrairement à FastAPI, EorbahAPI fournit un objet `BackgroundTasks` et un
middleware qui l'exécute après le handler :

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\BackgroundTasks;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Middlewares\BackgroundTasksMiddleware;
use Eorbahapi\Params\Body;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

final class Inscription extends BaseModel
{
    #[Field(min_length: 3)]
    public string $nom;

    #[Field]
    public string $email;
}

$tasks = new BackgroundTasks();

$app = new EorbahAPI('Inscriptions');
$app->register(BackgroundTasks::class, $tasks);
$app->add_middleware(BackgroundTasksMiddleware::class, ['tasks' => $tasks]);

$app->post('/inscriptions', function (#[Body] Inscription $inscription) use ($tasks) {
    $tasks->add(static function () use ($inscription): void {
        // Exemple : écriture différée
        file_put_contents(
            __DIR__ . '/cache/emails.log',
            "Bienvenue {$inscription->nom} <{$inscription->email}>\n",
            FILE_APPEND
        );
    });

    return ['created' => true];
});

$app->run();
```

1. l'instance `BackgroundTasks` est créée et enregistrée dans le conteneur ;
2. le middleware `BackgroundTasksMiddleware` reçoit cette instance ;
3. le handler reçoit `BackgroundTasks` par injection de type et y ajoute des
   tâches ;
4. après le handler, le middleware exécute la file.

## Ajouter des tâches

```php
$tasks->add($callable);                     // sans argument
$tasks->add($callable, ['oumar@example.com']); // arguments positionnels
$tasks->add_task($callable, [$email]);         // alias
```

Les tâches s'exécutent dans l'ordre d'ajout.

## Comportement

| Situation | Résultat |
| --- | --- |
| tâche normale | exécutée après le handler, avant l'envoi de la réponse |
| tâche qui lève une exception | les suivantes s'exécutent quand même ; l'erreur est conservée |
| aucune tâche | le middleware ne fait rien |
| réponse en erreur (exception du handler) | les tâches déjà ajoutées sont exécutées (bloc `finally`) |

Les erreurs sont disponibles pour la journalisation :

```php
foreach ($tasks->errors() as $error) {
    error_log($error->getMessage());
}
```

## Files d'attente

`BackgroundTasks` reste dans le processus : pour des traitements lourds ou
distribués, préférez une vraie file (Redis, RabbitMQ, worker dédié). Ce
mécanisme convient aux petites tâches rapides.

!!! tip "Injection dans les dépendances"
    Un callable résolu par `#[Depends]` peut aussi recevoir `BackgroundTasks`
    par type : le conteneur retourne la même instance, donc les tâches ajoutées
    dans une dépendance sont exécutées avec celles du handler.

!!! warning "Après la réponse, avant l'envoi"
    Le middleware s'exécute à la fin du pipeline, avant `send()`. Une tâche
    lente retarde donc l'envoi de la réponse ; gardez-les courtes, ou déléguez à
    une file externe.

## Récapitulatif

- `new BackgroundTasks()` + `register()` + `BackgroundTasksMiddleware`
  composent le mécanisme ;
- le handler reçoit l'instance par injection de type et appelle `add()` ;
- les tâches s'exécutent après le handler, dans l'ordre ;
- une tâche en échec n'empêche pas les suivantes ;
- pour du lourd, utilisez une file d'attente externe.

[Suivant : Dépendances](dependencies.md){ .md-button }
