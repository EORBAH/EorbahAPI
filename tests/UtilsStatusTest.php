<?php

namespace Eorbahapi\Tests;

use Eorbahapi\Status;
use Eorbahapi\Utils;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UtilsStatusTest extends TestCase
{
    #[Test]
    public function route_templates_can_be_filled_with_parameters(): void
    {
        $this->assertSame('/users/7', Utils::getRoutePath('/users/{id}', ['id' => 7]));
        $this->assertSame('/users/7/posts', Utils::getRoutePath('/users/{id}/posts', ['id' => 7]));
        $this->assertSame('/files/a%20b', Utils::getRoutePath('/files/{chemin:path}', ['chemin' => 'a b']));
    }

    #[Test]
    public function missing_route_parameters_are_left_untouched(): void
    {
        $this->assertSame('/users/{id}', Utils::getRoutePath('/users/{id}'));
        $this->assertSame('/users/{id}/posts', Utils::getRoutePath('/users/{id}/posts', ['autre' => 1]));
    }

    #[Test]
    public function paths_are_normalized_without_trailing_slash(): void
    {
        $this->assertSame('/users', Utils::normalizePath('/users/'));
        $this->assertSame('/users', Utils::normalizePath('/users'));
        $this->assertSame('', Utils::normalizePath('/'));
    }

    #[Test]
    public function async_callables_are_detected(): void
    {
        $generator = static function (): \Generator {
            yield 1;
        };

        $plain = static fn (): int => 1;

        $this->assertTrue(Utils::isAsyncCallable($generator));
        $this->assertFalse(Utils::isAsyncCallable($plain));
    }

    #[Test]
    public function status_constants_and_helpers(): void
    {
        $this->assertSame(401, Status::HTTP_401_UNAUTHORIZED);
        $this->assertSame(404, Status::HTTP_404_NOT_FOUND);
        $this->assertSame('Not Found', Status::text(404));
        $this->assertSame('Inconnu', Status::text(999, 'Inconnu'));

        $this->assertTrue(Status::isSuccess(204));
        $this->assertTrue(Status::isRedirect(301));
        $this->assertTrue(Status::isClientError(422));
        $this->assertTrue(Status::isServerError(503));
        $this->assertTrue(Status::isError(404));
        $this->assertFalse(Status::isError(200));
    }
}
