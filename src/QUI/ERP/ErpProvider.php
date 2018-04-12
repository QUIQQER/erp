<?php

/**
 * This file contains QUI\ERP\ErpProvider
 */

namespace QUI\ERP;

use QUI;

use QUI\ERP\Api\AbstractErpProvider;

use QUI\Controls\Sitemap\Map;
use QUI\Controls\Sitemap\Item;

/**
 * Class ErpProvider
 *
 * @package QUI\ERP\Products
 */
class ErpProvider extends AbstractErpProvider
{
    /**
     * @param \QUI\Controls\Sitemap\Map $Map
     */
    public static function addMenuItems(Map $Map)
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
    }
}
