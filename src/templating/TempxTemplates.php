<?php

namespace EorBah545\Eorbahapi\templating;

class TempxTemplates {
    private $templateDir;
    private $cacheEnabled;
    private $cacheDir;
    
    public function __construct($templateDir = 'templates', $cacheEnabled = true, $cacheDir = 'cache/tempx') {
        $this->templateDir = $templateDir;
        $this->cacheEnabled = $cacheEnabled;
        $this->cacheDir = $cacheDir;
    }
    
    public function render($file, $data = []): string {
        $fullPath = $this->templateDir . '/' . ltrim($file, '/');
        
        if (file_exists($fullPath)) {
            $comp = file_get_contents($fullPath);
            $fileVersion = filemtime($fullPath);
            $result = $this->processTemplate($data, $comp, $fileVersion);
            return $result;
        } else {
            return "Fichier non trouvé : $fullPath";
        }
    }
    
    public function tempx($comp, $data): string {
        $compVersion = md5($comp);
        return $this->processTemplate($data, $comp, $compVersion);
    }
    
    private function processTemplate($data, $comp, $version): string {
        if ($this->cacheEnabled) {
            $cacheKey = md5($comp . serialize($data) . $version) . '.cache';
            $cacheFile = $this->cacheDir . '/' . $cacheKey;
            
            if (file_exists($cacheFile)) {
                return file_get_contents($cacheFile);
            }
        }
        
        $result = $this->processConditions($comp, $data);
        $result = $this->processLoops($result, $data);
        $result = $this->processFilters($result, $data);
        $result = $this->processPartials($result, $data);
        
        $result = preg_replace_callback(
            '/{{\s*([\w.]+)\s*}}/',
            function($matches) use ($data) {
                $expr = trim($matches[1]);
                $result = $this->evaluateExpression($expr, $data);
                return $result;
            },
            $result
        );
        
        if ($this->cacheEnabled) {
            if (!is_dir($this->cacheDir)) {
                mkdir($this->cacheDir, 0755, true);
            }
            file_put_contents($cacheFile, $result);
        }
        
        return $result;
    }
    
    private function evaluateExpression($expr, $context): mixed {
        if (strpos($expr, '.') !== false) {
            $parts = explode('.', $expr);
            $current = $context;
            
            foreach ($parts as $part) {
                if (is_array($current) && isset($current[$part])) {
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
        
        return $context[$expr] ?? '{{' . $expr . '}}';
    }
    
    private function processConditions($content, $data): array|string|null {
        $content = preg_replace_callback(
            '/{{#if\s+([\w.]+)\s*}}(.*?){{\/if}}/s',
            function($matches) use ($data) {
                $variable = trim($matches[1]);
                $blockContent = $matches[2];
                $value = $this->evaluateExpression($variable, $data);
                if ($value && $value !== '' && $value !== false && $value !== 0) {
                    return $blockContent;
                }
                return '';
            },
            $content
        );
        
        $content = preg_replace_callback(
            '/{{#unless\s+([\w.]+)\s*}}(.*?){{\/unless}}/s',
            function($matches) use ($data): mixed {
                $variable = trim($matches[1]);
                $blockContent = $matches[2];
                $value = $this->evaluateExpression($variable, $data);
                if (!$value || $value === '' || $value === false || $value === 0) {
                    return $blockContent;
                }
                return '';
            },
            $content
        );
        
        return $content;
    }
    
    private function processLoops($content, $data): array|string|null {
        $content = preg_replace_callback(
            '/{{#loop\s+([\w.]+)\s*}}(.*?){{\/loop}}/s',
            function($matches) use ($data) {
                $countExpr = trim($matches[1]);
                $blockContent = $matches[2];
                $count = $this->evaluateExpression($countExpr, $data);
                $count = intval($count);
                
                $result = '';
                for ($i = 0; $i < $count; $i++) {
                    $itemContent = $blockContent;
                    $itemContent = str_replace('{{ $__n }}', $i, $itemContent);
                    
                    $itemContent = preg_replace_callback(
                        '/{{\s*([\w.]+)\$__n([\w.]*)\s*}}/',
                        function($matches) use ($data, $i) {
                            $beforeIndex = $matches[1];
                            $afterIndex = $matches[2];
                            $fullVar = $beforeIndex . $i . $afterIndex;
                            return $this->evaluateExpression($fullVar, $data);
                        },
                        $itemContent
                    );
                    
                    $result .= $itemContent;
                }
                
                return $result;
            },
            $content
        );
        
        $content = preg_replace_callback(
            '/{{#each\s+(\w+)\s*}}(.*?){{\/each}}/s',
            function($matches) use ($data) {
                $arrayName = trim($matches[1]);
                $blockContent = $matches[2];
                $array = $data[$arrayName] ?? [];
                $result = '';
                
                if (is_array($array)) {
                    foreach ($array as $index => $item) {
                        $itemContent = $blockContent;
                        
                        foreach ($item as $key => $value) {
                            $itemContent = str_replace('{{ ' . $key . ' }}', $value, $itemContent);
                        }
                        
                        $itemContent = str_replace('{{ $__n }}', $index, $itemContent);
                        $result .= $itemContent;
                    }
                }
                
                return $result;
            },
            $content
        );
        
        return $content;
    }
    
    private function processFilters($content, $data): array|string|null {
        $content = preg_replace_callback(
            '/{{\s*([\w.]+)\s*\|\s*(\w+)(?::([^}]+))?\s*}}/',
            function($matches) use ($data) {
                $variable = trim($matches[1]);
                $filter = trim($matches[2]);
                $parameter = isset($matches[3]) ? trim($matches[3]) : null;
                $value = $this->evaluateExpression($variable, $data);
                $result = $this->applyFilter($value, $filter, $parameter);
                return $result;
            },
            $content
        );
        
        return $content;
    }
    
    private function applyFilter($value, $filter, $parameter = null): mixed {
        switch ($filter) {
            case 'uppercase':
                return strtoupper($value);
            case 'lowercase':
                return strtolower($value);
            case 'length':
                return strlen($value);
            case 'format_number':
                return number_format($value, 0, ',', ' ');
            case 'format_currency':
                return number_format($value, 2, ',', ' ') . ' €';
            case 'truncate':
                $length = intval($parameter) ?: 10;
                if (strlen($value) > $length) {
                    return substr($value, 0, $length) . '...';
                }
                return $value;
            case 'default':
                if (empty($value) || $value === null || $value === '{{' . $parameter . '}}') {
                    return $parameter;
                }
                return $value;
            default:
                return $value;
        }
    }
    
    private function processPartials($content, $data): array|string|null {
        $content = preg_replace_callback(
            '/{{\s*>\s*([\w\/\.]+)(?:\s+data="([^"]+)")?\s*}}/',
            function($matches) use ($data) {
                $partialPath = trim($matches[1]);
                $dataString = isset($matches[2]) ? trim($matches[2]) : '';
                $partialData = $data;
                
                if (!empty($dataString)) {
                    $partialData = $this->parsePartialData($dataString, $data);
                }
                
                $partialFile = $this->resolvePartialPath($partialPath);
                
                if (file_exists($partialFile)) {
                    $partialContent = file_get_contents($partialFile);
                    $partialVersion = filemtime($partialFile);
                    return $this->processTemplate($partialData, $partialContent, $partialVersion);
                } else {
                    return "<!-- Partial non trouvé: {$partialPath} -->";
                }
            },
            $content
        );
        
        return $content;
    }
    
    private function parsePartialData($dataString, $parentData): mixed {
        $partialData = $parentData;
        preg_match_all('/(\w+):([\'"]?)([^\s\'"]+)\2/', $dataString, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $key = $match[1];
            $quote = $match[2];
            $value = $match[3];
            
            if ($quote === "'" || $quote === '"') {
                $partialData[$key] = $value;
            } else {
                $partialData[$key] = $parentData[$value] ?? $value;
            }
        }
        
        return $partialData;
    }
    
    private function resolvePartialPath($partialPath): string {
        if (strpos($partialPath, '/') === 0) {
            return $this->templateDir . $partialPath . '.tempx';
        }
        
        return $this->templateDir . '/' . $partialPath . '.tempx';
    }
}