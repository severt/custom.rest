<?php
namespace Custom\Rest\Logger;

use Custom\Rest\Config\Config;
use Bitrix\Main\Type\DateTime;

/**
 * Класс для логирования событий модуля
 */
class Logger
{
    // Уровни логирования
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';
    
    private static $levelPriority = [
        self::LEVEL_DEBUG => 1,
        self::LEVEL_INFO => 2,
        self::LEVEL_WARNING => 3,
        self::LEVEL_ERROR => 4,
        self::LEVEL_CRITICAL => 5,
    ];
    
    /**
     * Логирование сообщения
     */
    public static function log(string $message, string $level = self::LEVEL_INFO, array $context = []): void
    {
        if (!Config::isLogEnabled()) {
            return;
        }
        
        // Проверка уровня логирования
        if (!self::shouldLog($level)) {
            return;
        }
        
        try {
            $logFile = Config::getLogFilePath();
            $logDir = dirname($logFile);
            
            // Создаем директорию, если не существует
            if (!file_exists($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            // Формируем сообщение
            $timestamp = (new DateTime())->toString();
            $contextString = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
            $logMessage = sprintf(
                "[%s] [%s] %s%s%s",
                $timestamp,
                $level,
                $message,
                $contextString,
                PHP_EOL
            );
            
            // Записываем в файл
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
            
            // Ротация логов (если файл больше 10 МБ)
            self::rotateLogIfNeeded($logFile);
            
        } catch (\Exception $e) {
            // Если не можем записать в файл, пишем в стандартный лог Bitrix
            \Bitrix\Main\Diag\Debug::writeToFile(
                'Custom Rest Logger Error: ' . $e->getMessage(),
                '',
                '/bitrix/modules/custom.rest/error.log'
            );
        }
    }
    
    /**
     * Проверка, нужно ли логировать сообщение данного уровня
     */
    private static function shouldLog(string $level): bool
    {
        $currentLevel = Config::getLogLevel();
        
        $currentPriority = self::$levelPriority[$currentLevel] ?? 2;
        $messagePriority = self::$levelPriority[$level] ?? 2;
        
        return $messagePriority >= $currentPriority;
    }
    
    /**
     * Ротация лог-файла
     */
    private static function rotateLogIfNeeded(string $logFile): void
    {
        $maxSize = 10 * 1024 * 1024; // 10 МБ
        
        if (file_exists($logFile) && filesize($logFile) > $maxSize) {
            $backupFile = $logFile . '.' . date('Y-m-d_H-i-s') . '.bak';
            rename($logFile, $backupFile);
            
            // Удаляем старые бэкапы (старше 30 дней)
            self::cleanOldBackups(dirname($logFile), 30);
        }
    }
    
    /**
     * Очистка старых бэкапов
     */
    private static function cleanOldBackups(string $dir, int $days): void
    {
        $files = glob($dir . '/*.bak');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $days) {
                    unlink($file);
                }
            }
        }
    }
    
    // Вспомогательные методы для разных уровней
    
    public static function debug(string $message, array $context = []): void
    {
        self::log($message, self::LEVEL_DEBUG, $context);
    }
    
    public static function info(string $message, array $context = []): void
    {
        self::log($message, self::LEVEL_INFO, $context);
    }
    
    public static function warning(string $message, array $context = []): void
    {
        self::log($message, self::LEVEL_WARNING, $context);
    }
    
    public static function error(string $message, array $context = []): void
    {
        self::log($message, self::LEVEL_ERROR, $context);
    }
    
    public static function critical(string $message, array $context = []): void
    {
        self::log($message, self::LEVEL_CRITICAL, $context);
    }
}
