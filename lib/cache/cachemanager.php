<?php
namespace Custom\Rest\Cache;

use Bitrix\Main\Data\Cache;
use Bitrix\Main\Application;
use Custom\Rest\Config\Config;
use Custom\Rest\Logger\Logger;

/**
 * Менеджер кэширования
 */
class CacheManager
{
    const CACHE_DIR = '/custom/rest/';
    const CACHE_TAG = 'custom_rest';
    
    /**
     * Получить данные из кэша или выполнить callback
     */
    public static function remember(string $key, callable $callback, ?int $ttl = null)
    {
        if (!Config::isCacheEnabled()) {
            Logger::debug('Cache disabled, executing callback directly', ['key' => $key]);
            return $callback();
        }
        
        $cache = Cache::createInstance();
        $ttl = $ttl ?? Config::getCacheTtl();
        $cacheId = self::getCacheId($key);
        
        try {
            if ($cache->initCache($ttl, $cacheId, self::CACHE_DIR)) {
                $data = $cache->getVars();
                Logger::debug('Cache hit', ['key' => $key]);
                return $data;
            }
            
            Logger::debug('Cache miss', ['key' => $key]);
            
            if ($cache->startDataCache()) {
                $taggedCache = Application::getInstance()->getTaggedCache();
                $taggedCache->startTagCache(self::CACHE_DIR);
                $taggedCache->registerTag(self::CACHE_TAG);
                $taggedCache->registerTag(self::CACHE_TAG . '_' . $key);
                
                try {
                    $data = $callback();
                    
                    $taggedCache->endTagCache();
                    $cache->endDataCache($data);
                    
                    Logger::info('Data cached successfully', [
                        'key' => $key,
                        'ttl' => $ttl
                    ]);
                    
                    return $data;
                    
                } catch (\Exception $e) {
                    $taggedCache->abortTagCache();
                    $cache->abortDataCache();
                    
                    Logger::error('Error during cache callback execution', [
                        'key' => $key,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    throw $e;
                }
            }
            
        } catch (\Exception $e) {
            Logger::error('Cache operation failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            // В случае ошибки кэша выполняем callback напрямую
            return $callback();
        }
        
        return null;
    }
    
    /**
     * Очистить кэш по ключу
     */
    public static function forget(string $key): bool
    {
        try {
            $taggedCache = Application::getInstance()->getTaggedCache();
            $taggedCache->clearByTag(self::CACHE_TAG . '_' . $key);
            
            Logger::info('Cache cleared for key', ['key' => $key]);
            return true;
            
        } catch (\Exception $e) {
            Logger::error('Failed to clear cache', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Очистить весь кэш модуля
     */
    public static function flush(): bool
    {
        try {
            $taggedCache = Application::getInstance()->getTaggedCache();
            $taggedCache->clearByTag(self::CACHE_TAG);
            
            Logger::info('All module cache cleared');
            return true;
            
        } catch (\Exception $e) {
            Logger::error('Failed to flush cache', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Получить информацию о кэше
     */
    public static function getInfo(): array
    {
        $cacheDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/cache' . self::CACHE_DIR;
        
        if (!is_dir($cacheDir)) {
            return [
                'exists' => false,
                'size' => 0,
                'files_count' => 0
            ];
        }
        
        $size = 0;
        $count = 0;
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
                $count++;
            }
        }
        
        return [
            'exists' => true,
            'size' => $size,
            'size_formatted' => self::formatBytes($size),
            'files_count' => $count,
            'directory' => $cacheDir
        ];
    }
    
    /**
     * Агент для очистки устаревшего кэша
     */
    public static function clearExpiredCacheAgent(): string
    {
        try {
            Logger::info('Running cache cleanup agent');
            
            $cache = Cache::createInstance();
            $cache->cleanDir(self::CACHE_DIR);
            
            Logger::info('Cache cleanup completed successfully');
            
        } catch (\Exception $e) {
            Logger::error('Cache cleanup agent failed', [
                'error' => $e->getMessage()
            ]);
        }
        
        return "\\Custom\\Rest\\Cache\\CacheManager::clearExpiredCacheAgent();";
    }
    
    /**
     * Генерация ID кэша
     */
    private static function getCacheId(string $key): string
    {
        return md5(self::CACHE_TAG . '_' . $key);
    }
    
    /**
     * Форматирование размера в читаемый вид
     */
    private static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
