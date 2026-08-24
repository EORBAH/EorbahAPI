<?php

namespace Eorbahapi\Testing;

use Eorbahapi\Response;
use Eorbahapi\Responses\JSONResponse;

final class TestResponse {
    public function __construct(
        private readonly Response $response,
    ) {}

    public function status(): int {
        return 0;
    }

    public function headers(): array {
        return [];
    }

    public function header(): ?string {
        return null;
    }

    public function body(): string {
        return "";
    }

    public function text(): string {
        return $this->body();
    }

    public function json(): mixed {
        return new JSONResponse(
            $this->body(),
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function raw(): Response {
        return  $this->response;
    }

}