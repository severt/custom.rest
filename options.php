<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Custom\Rest\Config\Config;
use Custom\Rest\Cache\CacheManager;
use Custom\Rest\Logger\Logger;

if (!$USER->IsAdmin()) {
    return;
}

Loader::includeModule('custom.rest');

$moduleId = Config::MODULE_ID;

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    
    if (isset($_POST['clear_cache'])) {
        CacheManager::flush();
        Logger::info('Cache cleared from admin panel');
        CAdminMessage::ShowMessage([
            'MESSAGE' => 'Кэш успешно очищен',
            'TYPE' => 'OK'
        ]);
    }
    
    if (isset($_POST['save'])) {
        // Кэширование
        Option::set($moduleId, Config::CACHE_ENABLED, $_POST['cache_enabled'] ?? 'N');
        Option::set($moduleId, Config::CACHE_TTL, (int)$_POST['cache_ttl'] ?? 3600);
        
        // Логирование
        Option::set($moduleId, Config::LOG_ENABLED, $_POST['log_enabled'] ?? 'N');
        Option::set($moduleId, Config::LOG_LEVEL, $_POST['log_level'] ?? 'ERROR');
        Option::set($moduleId, Config::LOG_FILE_PATH, $_POST['log_file_path'] ?? '');
        
        // Фильтрация
        Option::set($moduleId, Config::FILTER_ENABLED, $_POST['filter_enabled'] ?? 'N');
        
        Logger::info('Module settings updated from admin panel');
        
        CAdminMessage::ShowMessage([
            'MESSAGE' => 'Настройки сохранены',
            'TYPE' => 'OK'
        ]);
    }
}

// Получаем текущие значения
$cacheEnabled = Config::isCacheEnabled() ? 'Y' : 'N';
$cacheTtl = Config::getCacheTtl();
$logEnabled = Config::isLogEnabled() ? 'Y' : 'N';
$logLevel = Config::getLogLevel();
$logFilePath = Config::getLogFilePath();
$filterEnabled = Config::isFilterEnabled() ? 'Y' : 'N';

// Информация о кэше
$cacheInfo = CacheManager::getInfo();

$tabControl = new CAdminTabControl('tabControl', [
    ['DIV' => 'edit1', 'TAB' => 'Кэширование', 'TITLE' => 'Настройки кэширования'],
    ['DIV' => 'edit2', 'TAB' => 'Логирование', 'TITLE' => 'Настройки логирования'],
    ['DIV' => 'edit3', 'TAB' => 'Фильтрация', 'TITLE' => 'Настройки фильтрации'],
]);
?>

<form method="POST" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialchars($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    
    <?php $tabControl->Begin(); ?>
    
    <?php $tabControl->BeginNextTab(); ?>
    
    <tr>
        <td width="40%">Включить кэширование:</td>
        <td>
            <input type="checkbox" name="cache_enabled" value="Y" <?= $cacheEnabled === 'Y' ? 'checked' : '' ?>>
        </td>
    </tr>
    
    <tr>
        <td>Время жизни кэша (секунды):</td>
        <td>
            <input type="number" name="cache_ttl" value="<?= htmlspecialchars($cacheTtl) ?>" min="60" max="86400">
            <br><small>От 60 секунд до 86400 (24 часа)</small>
        </td>
    </tr>
    
    <tr class="heading">
        <td colspan="2">Информация о кэше</td>
    </tr>
    
    <tr>
        <td>Размер кэша:</td>
        <td>
            <strong><?= $cacheInfo['size_formatted'] ?? '0 B' ?></strong>
            (<?= $cacheInfo['files_count'] ?? 0 ?> файлов)
        </td>
    </tr>
    
    <tr>
        <td>Директория:</td>
        <td><code><?= htmlspecialchars($cacheInfo['directory'] ?? 'N/A') ?></code></td>
    </tr>
    
    <tr>
        <td></td>
        <td>
            <input type="submit" name="clear_cache" value="Очистить кэш" class="adm-btn-save">
        </td>
    </tr>
    
    <?php $tabControl->BeginNextTab(); ?>
    
    <tr>
        <td width="40%">Включить логирование:</td>
        <td>
            <input type="checkbox" name="log_enabled" value="Y" <?= $logEnabled === 'Y' ? 'checked' : '' ?>>
        </td>
    </tr>
    
    <tr>
        <td>Уровень логирования:</td>
        <td>
            <select name="log_level">
                <option value="DEBUG" <?= $logLevel === 'DEBUG' ? 'selected' : '' ?>>DEBUG (все)</option>
                <option value="INFO" <?= $logLevel === 'INFO' ? 'selected' : '' ?>>INFO</option>
                <option value="WARNING" <?= $logLevel === 'WARNING' ? 'selected' : '' ?>>WARNING</option>
                <option value="ERROR" <?= $logLevel === 'ERROR' ? 'selected' : '' ?>>ERROR</option>
                <option value="CRITICAL" <?= $logLevel === 'CRITICAL' ? 'selected' : '' ?>>CRITICAL (только критические)</option>
            </select>
        </td>
    </tr>
    
    <tr>
        <td>Путь к файлу логов:</td>
        <td>
            <input type="text" name="log_file_path" value="<?= htmlspecialchars($logFilePath) ?>" size="60">
            <br><small>Абсолютный путь к файлу логов</small>
        </td>
    </tr>
    
    <?php if (file_exists($logFilePath)): ?>
    <tr class="heading">
        <td colspan="2">Информация о логах</td>
    </tr>
    
    <tr>
        <td>Размер файла:</td>
        <td><?= \CFile::FormatSize(filesize($logFilePath)) ?></td>
    </tr>
    
    <tr>
        <td>Последнее изменение:</td>
        <td><?= date('d.m.Y H:i:s', filemtime($logFilePath)) ?></td>
    </tr>
    
    <tr>
        <td></td>
        <td>
            <a href="<?= str_replace($_SERVER['DOCUMENT_ROOT'], '', $logFilePath) ?>" target="_blank" class="adm-btn">Открыть файл логов</a>
        </td>
    </tr>
    <?php endif; ?>
    
    <?php $tabControl->BeginNextTab(); ?>
    
    <tr>
        <td width="40%">Включить фильтрацию пользователей:</td>
        <td>
            <input type="checkbox" name="filter_enabled" value="Y" <?= $filterEnabled === 'Y' ? 'checked' : '' ?>>
            <br><small>Фильтровать результаты im.recent.list по разрешенным пользователям</small>
        </td>
    </tr>
    
    <?php $tabControl->Buttons(); ?>
    
    <input type="submit" name="save" value="Сохранить" class="adm-btn-save">
    <input type="reset" value="Сбросить">
    
    <?php $tabControl->End(); ?>
</form>
