<?php

namespace QUI\ERP\Dashboard;

use QUI;
use QUI\Dashboard\DashboardInterface;

/**
 * Class DashboardProvider
 *
 * @package QUI\LoginLogger
 */
class ErpDashboard implements DashboardInterface
{
    /**
     * @param null $Locale
     * @return string
     */
    public function getTitle($Locale = null): string
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/erp', 'dashboard.title');
    }

    /**
     * @return array
     */
    public function getCards(): array
    {
        return [
            'package/quiqqer/erp/bin/backend/controls/dashboard/cards/GlobalProcessIdList'
        ];
    }

    public function getJavaScriptControl(): string
    {
        return '';
    }
}
