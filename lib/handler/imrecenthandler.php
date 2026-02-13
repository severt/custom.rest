<?php
namespace Custom\Rest\Handler;

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Rest\RestException;
use Custom\Rest\Cache\CacheManager;
use Custom\Rest\Config\Config;
use Custom\Rest\Logger\Logger;

/**
 * Обработчик REST методов для модуля IM
 */
class ImRecentHandler
{
    /**
     * Регистрация кастомных REST методов
     */
    public static function onRestServiceBuildDescription(): array
    {
        Logger::debug('Registering custom REST methods');
        
        return [
            'im' => [
                'im.recent.list' => [
                    __CLASS__,
                    'recentList'
                ],
            ],
        ];
    }
    
    /**
     * Кастомный метод im.recent.list с фильтрацией
     */
    public static function recentList($params, $offset, \CRestServer $server)
    {
        $startTime = microtime(true);
        $requestId = uniqid('req_', true);
        
        Logger::info('REST request received', [
            'request_id' => $requestId,
            'method' => 'im.recent.list',
            'params' => $params,
            'user_id' => $server->getAuthUser()
        ]);
        
        try {
            // Проверка и загрузка модулей
            self::checkModules();
            
            // Добавляем параметры фильтрации OpenLines
            $params['SKIP_OPENLINES'] = 'Y';
            $params['SKIP_UNDISTRIBUTED_OPENLINES'] = 'Y';
            
            Logger::debug('Calling original im.recent.list method', [
                'request_id' => $requestId
            ]);
            
            // Вызываем оригинальный метод
            $result = \CIMRestService::recentList($params, $offset, $server);
            
            if (!is_array($result)) {
                throw new \Exception('Invalid response from original method');
            }
            
            Logger::debug('Original method returned data', [
                'request_id' => $requestId,
                'items_count' => count($result['items'] ?? [])
            ]);
            
            // Применяем фильтрацию, если включена
            if (Config::isFilterEnabled()) {
                $result = self::filterResults($result, $requestId);
            }
            
            $executionTime = microtime(true) - $startTime;
            
            Logger::info('REST request completed successfully', [
                'request_id' => $requestId,
                'execution_time' => round($executionTime, 4),
                'final_items_count' => count($result['items'] ?? [])
            ]);
            
            return $result;
            
        } catch (RestException $e) {
            Logger::error('REST exception occurred', [
                'request_id' => $requestId,
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ]);
            throw $e;
            
        } catch (\Exception $e) {
            Logger::critical('Unexpected error in REST handler', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new RestException(
                'Internal server error: ' . $e->getMessage(),
                'INTERNAL_ERROR',
                \CRestServer::STATUS_INTERNAL
            );
        }
    }
    
    /**
     * Проверка и загрузка необходимых модулей
     */
    private static function checkModules(): void
    {
        if (!Loader::includeModule('im')) {
            Logger::error('Module "im" not found');
            throw new RestException(
                'Module "im" is not installed',
                'MODULE_NOT_INSTALLED',
                \CRestServer::STATUS_WRONG_REQUEST
            );
        }
        
        if (!Loader::includeModule('gpi.general')) {
            Logger::error('Module "gpi.general" not found');
            throw new RestException(
                'Module "gpi.general" is not installed',
                'MODULE_NOT_INSTALLED',
                \CRestServer::STATUS_WRONG_REQUEST
            );
        }
    }
    
    /**
     * Фильтрация результатов по разрешенным пользователям
     */
    private static function filterResults(array $result, string $requestId): array
    {
        if (empty($result['items']) || !is_array($result['items'])) {
            Logger::debug('No items to filter', ['request_id' => $requestId]);
            return $result;
        }
        
        $originalCount = count($result['items']);
        
        try {
            // Получаем список разрешенных пользователей с кэшированием
            $allowedUserIds = self::getAllowedUserIds();
            
            Logger::debug('Filtering results', [
                'request_id' => $requestId,
                'allowed_users_count' => count($allowedUserIds),
                'original_items_count' => $originalCount
            ]);
            
            // Фильтруем результаты
            $result['items'] = array_values(array_filter(
                $result['items'],
                function($item) use ($allowedUserIds, $requestId) {
                    $userId = $item['user']['id'] ?? null;
                    
                    if ($userId === null) {
                        Logger::warning('Item without user ID found', [
                            'request_id' => $requestId,
                            'item' => $item
                        ]);
                        return false;
                    }
                    
                    $isAllowed = in_array($userId, $allowedUserIds);
                    
                    if (!$isAllowed) {
                        Logger::debug('User filtered out', [
                            'request_id' => $requestId,
                            'user_id' => $userId
                        ]);
                    }
                    
                    return $isAllowed;
                }
            ));
            
            $filteredCount = count($result['items']);
            
            Logger::info('Filtering completed', [
                'request_id' => $requestId,
                'original_count' => $originalCount,
                'filtered_count' => $filteredCount,
                'removed_count' => $originalCount - $filteredCount
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Error during filtering', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // В случае ошибки фильтрации возвращаем оригинальные данные
            // чтобы не сломать функциональность полностью
        }
        
        return $result;
    }
    
    /**
     * Получение списка разрешенных пользователей с кэшированием
     */
    private static function getAllowedUserIds(): array
    {
        return CacheManager::remember(
            'allowed_user_ids',
            function() {
                Logger::debug('Fetching allowed user IDs from database');
                
                try {
                    $gpiChat = new \Gpi\General\Utils\Chat();
                    $userIds = $gpiChat->getUsersBotIds();
                    
                    if (!is_array($userIds)) {
                        Logger::warning('getUsersBotIds returned non-array', [
                            'type' => gettype($userIds)
                        ]);
                        return [];
                    }
                    
                    // Приводим к int для корректного сравнения
                    $userIds = array_map('intval', $userIds);
                    
                    Logger::info('Allowed user IDs fetched', [
                        'count' => count($userIds),
                        'ids' => $userIds
                    ]);
                    
                    return $userIds;
                    
                } catch (\Exception $e) {
                    Logger::error('Failed to get allowed user IDs', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    return [];
                }
            }
        );
    }
    
    /**
     * Публичный метод для очистки кэша разрешенных пользователей
     * Вызывать при изменении списка пользователей
     */
    public static function clearAllowedUsersCache(): bool
    {
        Logger::info('Clearing allowed users cache manually');
        return CacheManager::forget('allowed_user_ids');
    }
}
