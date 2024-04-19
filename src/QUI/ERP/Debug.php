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
     * @var Debug|null
     */
    protected static ?Debug $Instance = null;

    /**
     * @var ?QUI\Config
     */
    protected QUI\Config|null $Config = null;

    /**
     * @var int|bool
     */
    protected int|bool $debug = false;

    /**
     * @return Debug|null
     */
    public static function getInstance(): ?Debug
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
        try {
            $this->Config = QUI::getPackage('quiqqer/erp')->getConfig();

            if ($this->Config) {
                $this->debug = (int)$this->Config->getValue('general', 'debug');
            }
        } catch (QUI\Exception $Exception) {
            Log::writeException($Exception);
        }
    }

    /**
     * Enable the debugging
     */
    public function enable(): void
    {
        $this->debug = 1;
    }

    /**
     * Disable the debugging
     */
    public function disable(): void
    {
        $this->debug = 0;
    }

    /**
     * Send debug logs
     * only if debugging is true
     *
     * @param object|integer|array|string $value - debug data
     * @param bool|string $source - debug source
     */
    public function log(object|int|array|string $value, bool|string $source = false): void
    {
        if (!$this->debug) {
            return;
        }

        if ($value instanceof \Exception) {
            Log::writeException(
                $value,
                Log::LEVEL_DEBUG,
                [
                    'source' => $source
                ],
                'erp-debug',
                true
            );

            return;
        }

        Log::writeRecursive(
            $value,
            Log::LEVEL_DEBUG,
            [
                'source' => $source
            ],
            'erp-debug',
            true
        );
    }
}
