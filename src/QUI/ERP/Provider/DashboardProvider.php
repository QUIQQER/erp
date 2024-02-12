<?php

namespace QUI\ERP\Provider;

use QUI;
use QUI\Dashboard\DashboardProviderInterface;

class DashboardProvider implements DashboardProviderInterface
{
    public function getTitle($Locale = null): string
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/erp', 'dashboard.provider.title');
    }

    /**
     * @return array
     */
    public static function getBoards(): array
    {
        return [
            new QUI\ERP\Dashboard\ErpDashboard()
        ];
    }

    /**
     * @return array
     */
    public static function getCards(): array
    {
        return [];
    }
}
