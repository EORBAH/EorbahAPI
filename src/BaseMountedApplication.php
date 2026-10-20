<?php

namespace Eorbahapi;

use Eorbahapi\Request;
use Eorbahapi\Response;

class BaseMountedApplication {
    public Request $request;
    public Response $response;

    /**
     * Permet à EorbahAPI d'injecter les instances Request/Response partagées.
     * Appelé automatiquement par mount().
     */
    public function setRequestResponse(Request $request, Response $response): void {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Compatibilité avec la signature run($http_code, $handler) de EorbahAPI.
     * Utilisée si l'application montée est appelée via run().
     */
    public function run(): void {
        if ($this->request && $this->response) {
            $this->handle($this->request, $this->response);
        } else {
            $this->handle(new Request(), new Response());
        }
    }

    /**
     * Point d'entrée principal appelé par mount().
     *
     * @param Request  $request
     * @param Response $response
     */
    public function handle($request, $response): void {
        $this->setRequestResponse($request, $response);
        $this->process();
        $this->response->send();
    }

    public function process() {}
}
