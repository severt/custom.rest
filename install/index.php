<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Custom\Rest\Config\Config;

Loc::loadMessages(__FILE__);

class custom_rest extends CModule
{
    public $MODULE_ID = 'custom.rest';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    
    public function __construct()
    {
        $arModuleVersion = [];
        include(__DIR__ . '/version.php');
        
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = 'Custom REST методы';
        $this->MODULE_DESCRIPTION = 'Модуль для переопределения REST API методов Bitrix24';
        $this->PARTNER_NAME = 'Custom Developer';
        $this->PARTNER_URI = '';
    }
    
    public function DoInstall()
    {
        global $APPLICATION;
        
        if (!$this->checkRequirements()) {
            return false;
        }
        
        ModuleManager::registerModule($this->MODULE_ID);
        
        $this->InstallEvents();
        $this->InstallDefaults();
        
        $APPLICATION->IncludeAdminFile(
            'Установка модуля ' . $this->MODULE_NAME,
            __DIR__ . '/step.php'
        );
        
        return true;
    }
    
    public function DoUninstall()
    {
        global $APPLICATION;
        
        $this->UnInstallEvents();
        
        Option::delete($this->MODULE_ID);
        
        ModuleManager::unRegisterModule($this->MODULE_ID);
        
        $APPLICATION->IncludeAdminFile(
            'Удаление модуля ' . $this->MODULE_NAME,
            __DIR__ . '/unstep.php'
        );
        
        return true;
    }
    
    private function checkRequirements(): bool
    {
        global $APPLICATION;
        
        if (!ModuleManager::isModuleInstalled('rest')) {
            $APPLICATION->ThrowException('Требуется модуль "rest"');
            return false;
        }
        
        if (!ModuleManager::isModuleInstalled('im')) {
            $APPLICATION->ThrowException('Требуется модуль "im"');
            return false;
        }
        
        return true;
    }
    
    private function InstallEvents()
    {
        // События регистрируются в include.php
    }
    
    private function UnInstallEvents()
    {
        // Очистка обработчиков событий
    }
    
    private function InstallDefaults()
    {
        // Устанавливаем значения по умолчанию
        Option::set($this->MODULE_ID, Config::CACHE_ENABLED, 'Y');
        Option::set($this->MODULE_ID, Config::CACHE_TTL, '3600');
        Option::set($this->MODULE_ID, Config::LOG_ENABLED, 'Y');
        Option::set($this->MODULE_ID, Config::LOG_LEVEL, 'ERROR');
        Option::set($this->MODULE_ID, Config::LOG_FILE_PATH, $_SERVER['DOCUMENT_ROOT'] . '/local/logs/custom_rest.log');
        Option::set($this->MODULE_ID, Config::FILTER_ENABLED, 'Y');
    }
}
