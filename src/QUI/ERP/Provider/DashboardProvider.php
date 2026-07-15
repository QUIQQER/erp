<?php

namespace QUI\ERP\Provider;

use QUI;
use QUI\Dashboard\DashboardProviderInterface;

class DashboardProvider implements DashboardProviderInterface
{
    /**
     * @param QUI\Locale|null $Locale
     */
    public function getTitle(?QUI\Locale $Locale = null): string
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/erp', 'dashboard.provider.title');
    }

    /**
     * @return array<mixed>
     */
    public static function getBoards(): array
    {
        return [
            new QUI\ERP\Dashboard\ErpDashboard()
        ];
    }

    /**
     * @return array<mixed>
     */
    public static function getCards(): array
    {
        return [];
    }
}
