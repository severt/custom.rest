<?php
namespace Custom\Rest\Config;

use Bitrix\Main\Config\Option;

/**
 * Класс для управления настройками модуля
 */
class Config
{
    const MODULE_ID = 'custom.rest';
    
    // Настройки кэширования
    const CACHE_TTL = 'cache_ttl';
    const CACHE_ENABLED = 'cache_enabled';
    
    // Настройки логирования
    const LOG_ENABLED = 'log_enabled';
    const LOG_LEVEL = 'log_level';
    const LOG_FILE_PATH = 'log_file_path';
    
    // Настройки фильтрации
    const FILTER_ENABLED = 'filter_enabled';
    
    /**
     * Получить значение опции
     */
    public static function get(string $name, $default = '')
    {
        return Option::get(self::MODULE_ID, $name, $default);
    }
    
    /**
     * Установить значение опции
     */
    public static function set(string $name, $value): void
    {
        Option::set(self::MODULE_ID, $name, $value);
    }
    
    /**
     * Получить TTL кэша (в секундах)
     */
    public static function getCacheTtl(): int
    {
        return (int)self::get(self::CACHE_TTL, 3600);
    }
    
    /**
     * Проверка, включено ли кэширование
     */
    public static function isCacheEnabled(): bool
    {
        return self::get(self::CACHE_ENABLED, 'Y') === 'Y';
    }
    
    /**
     * Проверка, включено ли логирование
     */
    public static function isLogEnabled(): bool
    {
        return self::get(self::LOG_ENABLED, 'Y') === 'Y';
    }
    
    /**
     * Получить уровень логирования
     */
    public static function getLogLevel(): string
    {
        return self::get(self::LOG_LEVEL, 'ERROR');
    }
    
    /**
     * Получить путь к файлу логов
     */
    public static function getLogFilePath(): string
    {
        return self::get(
            self::LOG_FILE_PATH,
            $_SERVER['DOCUMENT_ROOT'] . '/local/logs/custom_rest.log'
        );
    }
    
    /**
     * Проверка, включена ли фильтрация
     */
    public static function isFilterEnabled(): bool
    {
        return self::get(self::FILTER_ENABLED, 'Y') === 'Y';
    }
}
