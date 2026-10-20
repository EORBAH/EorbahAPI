<?php

namespace Eorbahapi;

class Logger {
    private string $logLevel;
    private $levels = [
        'debug' => 1,
        'info' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5
    ];

    public function __construct(string $logLevel = 'info') {
        $this->logLevel = strtolower($logLevel);

    }

    public function log(string $level, string $message, array $context = []): void {
        $level = strtolower($level);

        // Vérifier le niveau de log
        if ($this->levels[$level] < $this->levels[$this->logLevel]) {
            return;
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'date' => date('Y-m-d'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        $logLine = json_encode($logEntry) . PHP_EOL;

        /**
         * Traitement deslogs
         */
        error_log($logLine);
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
