<?php

namespace EorBah545\Eorbahapi\templating\tempx\Processor;

use EorBah545\Eorbahapi\templating\tempx\Filter\FilterInterface;

class FilterProcessor implements ProcessorInterface
{
    private array $filters = [];

    public function __construct(array $filters)
    {
        foreach ($filters as $filter) {
            $this->filters[$filter->name()] = $filter;
        }
    }

    public function process(string $content, array &$data): string
    {
        return preg_replace_callback(
            '/{{\s*([\w.]+)\s*\|\s*(\w+)(?::([^}]+))?\s*}}/',
            function ($matches) use ($data) {
                $varName   = trim($matches[1]);
                $filterName = trim($matches[2]);
                $param     = isset($matches[3]) ? trim($matches[3]) : null;

                $evaluator = new \EorBah545\Eorbahapi\templating\tempx\Helper\ExpressionEvaluator(); // on peut injecter
                $value = $evaluator->evaluate($varName, $data);

                if (isset($this->filters[$filterName])) {
                    return $this->filters[$filterName]->apply($value, $param);
                }
                return $value;
            },
            $content
        );
    }
}