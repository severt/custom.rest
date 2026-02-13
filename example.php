<?php
// Очистка кэша разрешенных пользователей при их изменении
\Custom\Rest\Handler\ImRecentHandler::clearAllowedUsersCache();

// Полная очистка кэша модуля
\Custom\Rest\Cache\CacheManager::flush();

// Получение информации о кэше
$info = \Custom\Rest\Cache\CacheManager::getInfo();

// Логирование
\Custom\Rest\Logger\Logger::info('Custom message', ['data' => 123]);
\Custom\Rest\Logger\Logger::error('Error occurred', ['error' => $e->getMessage()]);
