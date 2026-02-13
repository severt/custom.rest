<?php
/**
 * Модуль для переопределения REST методов Bitrix24
 * @package Custom\Rest
 */

use Bitrix\Main\Loader;
use Bitrix\Main\EventManager;

Loader::registerAutoLoadClasses(
    'custom.rest',
    [
        // Handlers
        'Custom\\Rest\\Handler\\ImRecentHandler' => 'lib/handler/imrecenthandler.php',
        
        // Services
        'Custom\\Rest\\Cache\\CacheManager' => 'lib/cache/cachemanager.php',
        'Custom\\Rest\\Logger\\Logger' => 'lib/logger/logger.php',
        'Custom\\Rest\\Config\\Config' => 'lib/config/config.php',
    ]
);

// Регистрация обработчиков событий
$eventManager = EventManager::getInstance();

$eventManager->addEventHandler(
    'rest',
    'OnRestServiceBuildDescription',
    ['Custom\\Rest\\Handler\\ImRecentHandler', 'onRestServiceBuildDescription']
);

// Регистрация агента для очистки устаревшего кэша
CAgent::AddAgent(
    "\\Custom\\Rest\\Cache\\CacheManager::clearExpiredCacheAgent();",
    "custom.rest",
    "N",
    86400, // раз в сутки
    "",
    "Y",
    "",
    100
);
