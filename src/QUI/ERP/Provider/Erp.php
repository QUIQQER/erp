<?php

/**
 * This file contains QUI\ERP\Provider\Erp
 */

namespace QUI\ERP\Provider;

use QUI;
use QUI\Controls\Sitemap\Item;
use QUI\Controls\Sitemap\Map;
use QUI\ERP\Api\AbstractErpProvider;

/**
 * Class ErpProvider
 *
 * @package QUI\ERP\Provider\Products
 */
class Erp extends AbstractErpProvider
{
    /**
     * @param Map $Map
     */
    public static function addMenuItems(Map $Map): void
    {
        $Extras = $Map->getChildrenByName('extras');

        if ($Extras === null) {
            $Extras = new Item([
                'icon'     => 'fa fa-wrench',
                'name'     => 'extras',
                'text'     => ['quiqqer/erp', 'erp.panel.extras.text'],
                'priority' => 100
            ]);

            $Map->appendChild($Extras);
        }

        $Menu = new QUI\Workspace\Menu();
        $menu = $Menu->getMenu();

        $findItem = function ($name, $items) {
            foreach ($items as $item) {
                if ($item['name'] === $name) {
                    return $item;
                }
            }

            return false;
        };

        $extras = $findItem('extras', $menu);

        if (!$extras) {
            return;
        }

        $erp = $findItem('erp', $extras['items']);

        if (!$erp) {
            return;
        }

        $items = $erp['items'];

        foreach ($items as $item) {
            $Extras->appendChild(
                new Item([
                    'icon'    => $item['icon'],
                    'name'    => $item['name'],
                    'text'    => $item['locale'],
                    'require' => $item['require']
                ])
            );
        }

        // settings
        $Settings = $Map->getChildrenByName('settings');

        if ($Settings === null) {
            $Settings = new Item([
                'icon'     => 'fa fa-gears',
                'name'     => 'settings',
                'text'     => ['quiqqer/erp', 'erp.panel.settings.text'],
                'priority' => 101,
                'require'  => 'package/quiqqer/erp/bin/backend/utils/ErpMenuSettings'
            ]);

            $Map->appendChild($Settings);
        }
    }
}
