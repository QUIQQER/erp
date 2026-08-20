<?php

namespace QUITests\ERP\Api;

use QUI\Controls\Sitemap\Item;
use QUI\Controls\Sitemap\Map;
use QUI\ERP\Api\AbstractErpProvider;

class CoordinatorFixtureProvider extends AbstractErpProvider
{
    public static function addMenuItems(Map $Map): void
    {
        $Map->appendChild(new Item(['name' => 'zeta', 'text' => ['test', 'zeta']]));
        $Map->appendChild(new Item(['name' => 'priority', 'text' => ['test', 'last'], 'priority' => 1]));

        $Alpha = new Item(['name' => 'alpha', 'text' => ['test', 'alpha']]);
        $Alpha->appendChild(new Item(['name' => 'child-zeta', 'text' => ['test', 'zeta']]));
        $Alpha->appendChild(new Item(['name' => 'child-alpha', 'text' => ['test', 'alpha']]));
        $Map->appendChild($Alpha);
    }

    public static function getNumberRanges(): array
    {
        return ['invoice', 'order'];
    }

    public static function getMailLocale(): array
    {
        return [['title' => 'Z mail'], ['title' => 'A mail']];
    }
}
