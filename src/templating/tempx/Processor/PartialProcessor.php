<?php

namespace EorBah545\Eorbahapi\templating\tempx\Processor;

use EorBah545\Eorbahapi\templating\tempx\TemplateEngine;
use EorBah545\Eorbahapi\templating\tempx\Helper\ExpressionEvaluator;

class PartialProcessor implements ProcessorInterface
{
    private TemplateEngine $engine;
    private FilterProcessor $filterProcessor;
    private ExpressionEvaluator $evaluator;

    public function __construct(TemplateEngine $engine, FilterProcessor $filterProcessor, ExpressionEvaluator $evaluator)
    {
        $this->engine = $engine;
        $this->filterProcessor = $filterProcessor;
        $this->evaluator = $evaluator;
    }

    public function process(string $content, array &$data): string
    {
        return preg_replace_callback(
            '/{{\s*>\s*([\w\/\.]+)(?:\s+data="([^"]+)")?\s*}}/',
            function ($matches) use ($data) {
                $partialPath = trim($matches[1]);
                $dataString  = $matches[2] ?? '';
                $partialData = $data;

                if (!empty($dataString)) {
                    $partialData = $this->parseDataString($dataString, $data);
                }

                $partialFile = $this->resolvePath($partialPath);
                if (!file_exists($partialFile)) {
                    return "<!-- Partial non trouvé: {$partialPath} -->";
                }

                $template = file_get_contents($partialFile);
                $version  = filemtime($partialFile);

                // On passe par la méthode centrale de notre moteur (avec cache)
                return $this->engine->tempx($template, $partialData); // réutilise le cache
            },
            $content
        );
    }

    private function parseDataString(string $dataString, array $parentData): array
    {
        $data = $parentData;
        preg_match_all('/(\w+):([\'"]?)([^\s\'"]+)\2/', $dataString, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $key   = $match[1];
            $quote = $match[2];
            $value = $match[3];
            if ($quote === "'" || $quote === '"') {
                $data[$key] = $value;
            } else {
                $data[$key] = $parentData[$value] ?? $value;
            }
        }
        return $data;
    }

    private function resolvePath(string $partialPath): string
    {
        $dir = $this->engine->getTemplateDir();
        if (strpos($partialPath, '/') === 0) {
            return $dir . $partialPath . '.tempx';
        }
        return $dir . '/' . $partialPath . '.tempx';
    }
}