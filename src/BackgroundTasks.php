<?php

namespace Eorbahapi;

/**
 * Tâches exécutées après la réponse HTTP.
 *
 * Le handler ajoute des tâches ; le middleware
 * `Eorbahapi\Middlewares\BackgroundTasksMiddleware` les exécute une fois la
 * réponse produite :
 *
 *     $tasks = new BackgroundTasks();
 *     $app->register(BackgroundTasks::class, $tasks);
 *     $app->add_middleware(BackgroundTasksMiddleware::class, ['tasks' => $tasks]);
 *
 *     $app->post('/users', function (#[Body] User $user, BackgroundTasks $tasks) {
 *         $tasks->add(send_welcome_email(...), [$user->email]);
 *         return ['created' => true];
 *     });
 */
class BackgroundTasks
{
    /** @var list<array{task: callable, args: array}> */
    private array $tasks = [];

    /** @var list<\Throwable> */
    private array $errors = [];

    /**
     * Ajoute une tâche.
     *
     * @param array<int, mixed> $args Arguments positionnels passés à la tâche.
     */
    public function add(callable $task, array $args = []): self
    {
        $this->tasks[] = ['task' => $task, 'args' => $args];

        return $this;
    }

    /**
     * Alias de `add()`.
     */
    public function add_task(callable $task, array $args = []): self
    {
        return $this->add($task, $args);
    }

    /**
     * Exécute toutes les tâches, dans l'ordre d'ajout.
     *
     * Une tâche en échec n'interrompt pas les suivantes : l'erreur est
     * conservée dans `errors()` et peut être journalisée par l'appelant.
     */
    public function run(): void
    {
        $tasks = $this->tasks;
        $this->tasks = [];

        foreach ($tasks as $entry) {
            try {
                ($entry['task'])(...$entry['args']);
            } catch (\Throwable $error) {
                $this->errors[] = $error;
            }
        }
    }

    public function count(): int
    {
        return count($this->tasks);
    }

    public function is_empty(): bool
    {
        return $this->tasks === [];
    }

    public function clear(): void
    {
        $this->tasks = [];
    }

    /** @return list<\Throwable> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function clear_errors(): void
    {
        $this->errors = [];
    }
}
