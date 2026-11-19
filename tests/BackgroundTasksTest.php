<?php

namespace Eorbahapi\Tests;

use Eorbahapi\BackgroundTasks;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Middlewares\BackgroundTasksMiddleware;
use Eorbahapi\Testing\TestClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BackgroundTasksTest extends TestCase
{
    #[Test]
    public function tasks_run_in_registration_order(): void
    {
        $tasks = new BackgroundTasks();
        $log = [];

        $tasks->add(static function () use (&$log): void {
            $log[] = 'première';
        });
        $tasks->add(static function (string $valeur) use (&$log): void {
            $log[] = $valeur;
        }, ['deuxième']);

        $tasks->run();

        $this->assertSame(['première', 'deuxième'], $log);
        $this->assertTrue($tasks->is_empty());
        $this->assertSame(0, $tasks->count());
    }

    #[Test]
    public function add_task_is_an_alias_and_count_reflects_the_queue(): void
    {
        $tasks = new BackgroundTasks();
        $tasks->add_task(static fn () => null)->add(static fn () => null);

        $this->assertSame(2, $tasks->count());
        $this->assertFalse($tasks->is_empty());

        $tasks->clear();
        $this->assertSame(0, $tasks->count());
    }

    #[Test]
    public function a_failing_task_does_not_stop_the_others(): void
    {
        $tasks = new BackgroundTasks();
        $ran = false;

        $tasks->add(static function (): void {
            throw new \RuntimeException('échec');
        });
        $tasks->add(static function () use (&$ran): void {
            $ran = true;
        });

        $tasks->run();

        $this->assertTrue($ran);
        $this->assertCount(1, $tasks->errors());
        $this->assertSame('échec', $tasks->errors()[0]->getMessage());
    }

    #[Test]
    public function background_tasks_are_executed_after_the_handler(): void
    {
        $tasks = new BackgroundTasks();
        $executed = [];

        $app = new EorbahAPI('Tâches');
        $app->register(BackgroundTasks::class, $tasks);
        $app->add_middleware(BackgroundTasksMiddleware::class, ['tasks' => $tasks]);

        $app->post('/users', function (BackgroundTasks $background) use (&$executed) {
            $background->add(static function () use (&$executed): void {
                $executed[] = 'email envoyé';
            });

            return ['created' => true];
        });

        $response = (new TestClient($app))->post('/users', ['json' => []]);

        $this->assertSame(['created' => true], $response->json());
        $this->assertSame(['email envoyé'], $executed);
    }
}
