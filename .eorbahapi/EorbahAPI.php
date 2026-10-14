<?php

namespace Eorbahapi;

use Eorbahapi\Core\Application;
use Eorbahapi\Requests\Request;
use Eorbahapi\Responses\Response;
use Eorbahapi\Dependencies\DependencyResolver;

class EorbahAPI extends Application {
    public Request $request;
    public Response $response;

    private DependencyResolver $resolver;

    public function __construct(
        public ?string $title = null, 
        public ?string $description = null, 
        public bool $debug = false
    ) {
        $this->request = new Request();
        $this->response = new Response();
        $this->resolver = new DependencyResolver($this->request, $this->response);
        $this->registerDefaultExceptionHandlers();
    }

    /** 
     * Desactive une fonction
    */
    public function disable(string $name)
    {
        switch ($name) {
            case 'X-Powered-By':
                $this->response->removeHeader('X-Powered-By');
                break;

            default:
                break;
        }
    }

    private function registerDefaultExceptionHandlers(): void
    {
        //$handlers = new ExceptionHandlers($this->debug);
        //$handlers->overrideExceptionHandlers($this);
    }

    /**
     * Enregistre une dépendance dans le conteneur (ex: connexion BDD)
     */
    public function register(string $id, mixed $value): self
    {
        $this->resolver->set($id, $value);
        return $this;
    }
}