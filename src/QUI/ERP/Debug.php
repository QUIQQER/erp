<?php

/**
 * This file contains QUI\ERP\EventHandler
 */
namespace QUI\ERP;

use QUI;
use QUI\System\Log;

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
     * @var int
     */
    protected $debug;

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
     * Debug constructor
     */
    public function __construct()
    {
        $this->Config = QUI::getPackage('quiqqer/erp')->getConfig();
        $this->debug  = (int)$this->Config->getValue('general', 'debug');
    }

    /**
     * Send debug logs
     * only if debugging is true
     *
     * @param object|string|integer|array $value - debug data
     * @param string|bool $source - debug source
     */
    public function log($value, $source = false)
    {
        if (!$this->debug) {
            return;
        }

        if ($value instanceof \Exception) {
            Log::writeException(
                $value,
                Log::LEVEL_DEBUG,
                array(
                    'source' => $source
                )
            );
            return;
        }

        Log::writeRecursive(
            $value,
            Log::LEVEL_DEBUG,
            array(
                'source' => $source
            )
        );
    }
}
