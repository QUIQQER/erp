<?php

/**
 * This file contains QUI\ERP\Api\Coordinator
 */

namespace QUI\ERP\Api;

use QUI;

/**
 * Class Coordinator
 * - API point to get provider
 * - API point to get panel menu items
 * - API point to get number ranges
 *
 * @package QUI\ERP\Api
 */
class Coordinator extends QUI\Utils\Singleton
{
    /**
     * Return the ERP Api Provider from other packages
     *
     * @return array
     */
    public function getErpApiProvider()
    {
        $cache    = 'erp/provider/collection';
        $provider = [];

        try {
            $collect = QUI\Cache\Manager::get($cache);
        } catch (QUI\Cache\Exception $Exception) {
            $packages = QUI::getPackageManager()->getInstalled();
            $collect  = [];

            foreach ($packages as $package) {
                try {
                    $Package = QUI::getPackage($package['name']);

                    if (!$Package->isQuiqqerPackage()) {
                        continue;
                    }

                    $collect = array_merge($collect, $Package->getProvider('erp'));
                } catch (QUI\Exception $exception) {
                }
            }

            QUI\Cache\Manager::set($cache, $collect);
        }

        // filter provider
        $collect = new \RecursiveIteratorIterator(
            new \RecursiveArrayIterator($collect)
        );

        foreach ($collect as $entry) {
            if (!class_exists($entry)) {
                continue;
            }

            $Provider = new $entry();

            if ($Provider instanceof AbstractErpProvider) {
                $provider[] = $Provider;
            }
        }

        return $provider;
    }

    /**
     * Return the menu items for the shop panel
     * @return array
     */
    public function getMenuItems()
    {
        $cache = 'erp/provider/menuItems';

        try {
            $items = QUI\Cache\Manager::get($cache);
        } catch (QUI\Cache\Exception $Exception) {
            $items    = [];
            $provider = $this->getErpApiProvider();

            /* @var $Provider AbstractErpProvider */
            foreach ($provider as $Provider) {
                $items = array_merge($Provider->getMenuItems(), $items);
            }
        }

        return $items;
    }

    /**
     * Return all number range objects
     *
     * @return array
     */
    public function getNumberRanges()
    {
        $ranges   = [];
        $provider = $this->getErpApiProvider();

        /* @var $Provider AbstractErpProvider */
        foreach ($provider as $Provider) {
            $ranges = array_merge($Provider->getNumberRanges(), $ranges);
        }

        // @todo filter, only NumberRangeInterface are allowed

        return $ranges;
    }
}
