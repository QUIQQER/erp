<?php

namespace QUITests\ERP;

use QUI\Controls\Sitemap\Item;
use QUI\Controls\Sitemap\Map;
use QUI\ERP\Api\AbstractErpProvider;

class AjaxFixtureProvider extends AbstractErpProvider
{
    public static function addMenuItems(Map $Map): void
    {
        $Map->appendChild(new Item([
            'name' => 'erp-fixture',
            'text' => ['quiqqer/erp', 'fixture']
        ]));
    }

    public static function getNumberRanges(): array
    {
        return [new AjaxFixtureNumberRange()];
    }

    public static function getMailLocale(): array
    {
        return [['title' => 'Fixture mail']];
    }
}
