<?php

namespace EorBah545\Eorbahapi\middlewares;

class CORSMiddleware
{
    private $allowOrigins;
    private $allowCredentials;
    private $allowMethods;
    private $allowHeaders;
    
    public function __construct(
        array $allow_origins = ["*"],
        bool $allow_credentials = false,
        array $allow_methods = ["*"],
        array $allow_headers = ["*"]
    ) {
        $this->allowOrigins = $allow_origins;
        $this->allowCredentials = $allow_credentials;
        $this->allowMethods = $allow_methods;
        $this->allowHeaders = $allow_headers;
    }
    
    public function process($request, $response, $next)
    {
        
        // Ajouter les headers CORS
        return $next();
    }
}