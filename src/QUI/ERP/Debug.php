<?php

/**
 * This file contains QUI\ERP\EventHandler
 */
namespace QUI\ERP;

use QUI;
use QUI\Package\Package;

/**
 * Class EventHandler
 *
 * @package QUI\ERP
 */
class Debug
{
    /**
     * @var
     */
    protected static $Instance = null;

    /**
     * @var QUI\Config
     */
    protected $Config;

    /**
     * @return Debug
     */
    public static function getInstance()
    {
        if (self::$Instance === null) {
            self::$Instance = new self();
        }

        return self::$Instance;
    }

    /**
     * Debug constructor.
     */
    public function __construct()
    {
        $this->Config = QUI::getPackage('quiqqer/erp')->getConfig();
    }

    /**
     * Send debug logs
     * only if debugging is true
     *
     * @param object|string|integer|array $value - debug data
     */
    public function log($value)
    {
        if ($this->Config->getValue('general', 'debug')) {
            QUI\System\Log::writeRecursive($value);
        }
    }
}
