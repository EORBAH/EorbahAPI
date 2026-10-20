<?php

namespace Eorbahapi\Templating;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigTemplates {
    private Environment $twig;
    private string $rootPath;

    public function __construct(string $templateDir = 'templates', ?string $cacheDir = null, bool $debug = false) {
        $loader = new FilesystemLoader($templateDir);
        $this->rootPath = ($rootPath ?? getcwd()).\DIRECTORY_SEPARATOR;
        $this->twig = new Environment($loader, [
            'cache' => $cacheDir ?? $this->rootPath . '/../cache/twig',
            'debug' => $debug,
            'auto_reload' => true,
        ]);
    }

    public function TemplateResponse(string $template, array $data = []): string {
        return $this->twig->render($template, $data);
    }

    public function createTemplate(string $content, array $data = []): string {
        $template = $this->twig->createTemplate($content);
        return $template->render($data);
    }
}
