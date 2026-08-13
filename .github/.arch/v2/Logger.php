<?php

namespace Eorbahapi;

class Logger {
    private $logDir;
    private $logLevel;
    private $levels = [
        'debug' => 1,
        'info' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5
    ];
    
    public function __construct(string $logDir, string $logLevel = 'info') {
        $this->logDir = rtrim($logDir, '/') . '/';
        $this->logLevel = strtolower($logLevel);
        
        if (!file_exists($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    public function log(string $level, string $message, array $context = []): void {
        $level = strtolower($level);
        
        // Vérifier le niveau de log
        if ($this->levels[$level] < $this->levels[$this->logLevel]) {
            return;
        }
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $logFile = $this->logDir . date('Y-m-d') . '.log';
        $logLine = json_encode($logEntry) . PHP_EOL;
        
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    public function debug(string $message, array $context = []): void {
        $this->log('debug', $message, $context);
    }
    
    public function info(string $message, array $context = []): void {
        $this->log('info', $message, $context);
    }
    
    public function warning(string $message, array $context = []): void {
        $this->log('warning', $message, $context);
    }
    
    public function error(string $message, array $context = []): void {
        $this->log('error', $message, $context);
    }
    
    public function critical(string $message, array $context = []): void {
        $this->log('critical', $message, $context);
    }
}