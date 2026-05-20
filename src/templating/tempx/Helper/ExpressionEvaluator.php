<?php

namespace EorBah545\Eorbahapi\templating\tempx\Helper;

class ExpressionEvaluator
{
    public function evaluate(string $expr, array $context): mixed
    {
        if (strpos($expr, '.') !== false) {
            $parts = explode('.', $expr);
            $current = $context;
            foreach ($parts as $part) {
                if (is_array($current) && array_key_exists($part, $current)) {
                    $current = $current[$part];
                } elseif (is_string($current) && $part === 'length') {
                    $current = strlen($current);
                } elseif (is_array($current) && $part === 'length') {
                    $current = count($current);
                } else {
                    return '{{' . $expr . '}}';
                }
            }
            return $current;
        }

        return array_key_exists($expr, $context) ? $context[$expr] : '{{' . $expr . '}}';
    }
}