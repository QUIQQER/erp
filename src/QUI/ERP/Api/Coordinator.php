<?php

namespace QUI\ERP\Api;

use QUI;

/**
 * Class Coordinator
 *
 * @package QUI\ERP\Api
 */
class Coordinator extends QUI\Utils\Singleton
{
    /**
     * Return the ERP Api Provider from other pacages
     *
     * @return array
     */
    public function getErpApiProvider()
    {
        $cache    = 'erp/provider/collection';
        $provider = array();

        try {
            $collect = QUI\Cache\Manager::get($cache);
        } catch (QUI\Cache\Exception $Exception) {
            $packages = QUI::getPackageManager()->getInstalled();
            $collect  = array();

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
            $items    = array();
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
        $ranges   = array();
        $provider = $this->getErpApiProvider();

        /* @var $Provider AbstractErpProvider */
        foreach ($provider as $Provider) {
            $ranges = array_merge($Provider->getNumberRanges(), $ranges);
        }

        // @todo filter, only NumberRangeInterface are allowed

        return $ranges;
    }
}