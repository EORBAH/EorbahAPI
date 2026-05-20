<?php

namespace EorBah545\Eorbahapi\templating\tempx;

use EorBah545\Eorbahapi\templating\tempx\Processor;
use EorBah545\Eorbahapi\templating\tempx\Filter;

class TemplateEngine
{
    private string $templateDir;
    private CacheManager $cache;
    private array $processors = [];
    private FilterProcessor $filterProcessor; // centralise les filtres

    public function __construct(
        string   $templateDir = 'templates',
        bool     $cacheEnabled = true,
        string   $cacheDir = 'cache/tempx',
        array    $additionalFilters = [],
        array    $cacheIgnoredKeys = []
    ) {
        $this->templateDir = rtrim($templateDir, '/');
        $this->cache = new CacheManager($cacheDir, $cacheEnabled, $cacheIgnoredKeys);

        // Filtres par défaut
        $defaultFilters = [
            new Filter\UppercaseFilter(),
            new Filter\LowercaseFilter(),
            new Filter\LengthFilter(),
            new Filter\FormatNumberFilter(),
            new Filter\FormatCurrencyFilter(),
            new Filter\TruncateFilter(),
            new Filter\DefaultFilter(),
        ];

        $allFilters = array_merge($defaultFilters, $additionalFilters);
        $this->filterProcessor = new FilterProcessor($allFilters);

        // Pipeline de processeurs
        $this->processors = [
            new ConditionProcessor(new Helper\ExpressionEvaluator()),
            new LoopProcessor(new Helper\ExpressionEvaluator()),
            $this->filterProcessor,
            new PartialProcessor($this, $this->filterProcessor, new Helper\ExpressionEvaluator()),
            new ExpressionProcessor(new Helper\ExpressionEvaluator()),
        ];
    }

    public function render(string $file, array $data = []): string
    {
        $fullPath = $this->templateDir . '/' . ltrim($file, '/');
        if (!file_exists($fullPath)) {
            return "Fichier non trouvé : $fullPath";
        }

        $template = file_get_contents($fullPath);
        $version  = filemtime($fullPath);

        return $this->processTemplate($template, $data, $version);
    }

    public function tempx(string $template, array $data = []): string
    {
        $version = md5($template); // version pour cache
        return $this->processTemplate($template, $data, $version);
    }

    private function processTemplate(string $template, array $data, string $version): string
    {
        return $this->cache->get(
            $template,
            $data,
            $version,
            function () use ($template, $data) {
                $result = $template;
                foreach ($this->processors as $processor) {
                    $result = $processor->process($result, $data);
                }
                return $result;
            }
        );
    }

    public function getTemplateDir(): string
    {
        return $this->templateDir;
    }
}