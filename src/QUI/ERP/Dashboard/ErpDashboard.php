<?php

namespace QUI\ERP\Dashboard;

use QUI;
use QUI\Dashboard\DashboardInterface;
use QUI\Locale;

/**
 * Class DashboardProvider
 *
 * @package QUI\LoginLogger
 */
class ErpDashboard implements DashboardInterface
{
    /**
     * @param Locale|null $Locale
     * @return string
     */
    public function getTitle(null | QUI\Locale $Locale = null): string
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/erp', 'dashboard.erp.title');
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
