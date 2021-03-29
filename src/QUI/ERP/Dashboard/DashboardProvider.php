<?php

namespace QUI\ERP\Dashboard;

use QUI\Dashboard\DashboardProviderInterface;

/**
 * Class DashboardProvider
 *
 * @package QUI\LoginLogger
 */
class DashboardProvider implements DashboardProviderInterface
{
    /**
     * @inheritDoc
     *
     * @return array
     */
    public static function getCards(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public static function getBoards(): array
    {
        return [
            new Dashboard()
        ];
    }
}
