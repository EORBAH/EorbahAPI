<?php

namespace EorBah545\Eorbahapi\Templating;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigTemplates {
    private Environment $twig;
    
    public function __construct(string $templateDir = 'templates', $cacheDir = 'cache/twig', bool $debug = false) {
        $loader = new FilesystemLoader($templateDir);
        $this->twig = new Environment($loader, [
            'cache' => $cacheDir,
            'debug' => $debug,
            'auto_reload' => true,
        ]);
    }
    
    public function render(string $template, array $data = []): string {
        return $this->twig->render($template, $data);
    }
    
    public function temp(string $content, array $data = []): string {
        $template = $this->twig->createTemplate($content);
        return $template->render($data);
    }
}