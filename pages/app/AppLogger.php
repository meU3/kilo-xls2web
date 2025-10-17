<?php
// /app/AppLogger.php

class AppLogger {
    private bool $echoToConsole;
    private ?string $logFilePath;

    public function __construct(bool $echoToConsole = false, ?string $logFilePath = null) {
        $this->echoToConsole = $echoToConsole;
        $this->logFilePath = $logFilePath;
    }

    public function add(string $message): void {
        // Убираем все символы, которые могут быть нежелательны в текстовом логе
        $cleanMessage = preg_replace('/[^\p{L}\p{N}\p{P}\p{S}\s]/u', '', $message);
        
        $logEntry = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $cleanMessage);

        if ($this->echoToConsole) {
            echo $logEntry;
        }

        if ($this->logFilePath) {
            // Убедимся, что директория для лога существует
            $logDir = dirname($this->logFilePath);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            file_put_contents($this->logFilePath, $logEntry, FILE_APPEND);
        }
    }
}