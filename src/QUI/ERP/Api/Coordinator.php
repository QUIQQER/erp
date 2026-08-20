<?php

/**
 * This file contains QUI\ERP\Api\Coordinator
 */

namespace QUI\ERP\Api;

use Exception;
use QUI;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;

use function array_merge;
use function class_exists;
use function strcmp;
use function usort;

/**
 * Class Coordinator
 * - API point to get provider
 * - API point to get panel menu items
 * - API point to get number ranges
 */
class Coordinator extends QUI\Utils\Singleton
{
    /**
     * Return the ERP Api Provider from other packages
     *
     * @return AbstractErpProvider[]
     */
    public function getErpApiProvider(): array
    {
        $cache    = 'erp/provider/collection';
        $provider = [];

        try {
            $collect = QUI\Cache\Manager::get($cache);
        } catch (QUI\Cache\Exception) {
            $packages = QUI::getPackageManager()->getInstalled();
            $collect  = [];

            foreach ($packages as $package) {
                try {
                    $Package = QUI::getPackage($package['name']);

                    if (!$Package->isQuiqqerPackage()) {
                        continue;
                    }

                    $collect = array_merge($collect, $Package->getProvider('erp'));
                } catch (QUI\Exception) {
                }
            }

            try {
                QUI\Cache\Manager::set($cache, $collect);
            } catch (Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        // filter provider
        $collect = new RecursiveIteratorIterator(
            new RecursiveArrayIterator($collect)
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
     *
     * @return array<mixed>
     */
    public function getMenuItems(): array
    {
        $cache  = 'erp/provider/menuItems';
        $Map    = new QUI\Controls\Sitemap\Map();
        $Locale = QUI::getLocale();

        try {
            return QUI\Cache\Manager::get($cache);
        } catch (QUI\Cache\Exception) {
            $provider = $this->getErpApiProvider();

            foreach ($provider as $Provider) {
                $Provider->addMenuItems($Map);
            }
        }

        $result = $Map->toArray();

        $sorting = function (array $a, array $b) use ($Locale): int {
            if (!isset($a['priority']) && !isset($b['priority'])) {
                $aText = $a['text'] ?? null;
                $bText = $b['text'] ?? null;

                $aTextIsValid = is_array($aText)
                    && isset($aText[0], $aText[1])
                    && is_string($aText[0])
                    && is_string($aText[1]);
                $bTextIsValid = is_array($bText)
                    && isset($bText[0], $bText[1])
                    && is_string($bText[0])
                    && is_string($bText[1]);

                if (!$aTextIsValid && !$bTextIsValid) {
                    return 0;
                }

                if (!$aTextIsValid) {
                    return 1;
                }

                if (!$bTextIsValid) {
                    return -1;
                }

                // sort by text
                $aLocale = $Locale->get($aText[0], $aText[1]);
                $bLocale = $Locale->get($bText[0], $bText[1]);

                return strcmp($aLocale, $bLocale);
            }

            if (!isset($a['priority'])) {
                return 1;
            }

            if (!isset($b['priority'])) {
                return -1;
            }

            $pa = $a['priority'];
            $pb = $b['priority'];

            if ($pa == $pb) {
                return 0;
            }

            return $pa < $pb ? -1 : 1;
        };

        usort($result['items'], $sorting);

        foreach ($result['items'] as $key => $itemData) {
            usort($result['items'][$key]['items'], $sorting);
        }

        try {
            QUI\Cache\Manager::set($cache, $result);
        } catch (Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return $result;
    }

    /**
     * Return all number range objects
     *
     * @return array<mixed>
     */
    public function getNumberRanges(): array
    {
        $ranges   = [];
        $provider = $this->getErpApiProvider();

        foreach ($provider as $Provider) {
            $ranges = array_merge($Provider->getNumberRanges(), $ranges);
        }

        // @todo filter, only NumberRangeInterface are allowed

        return $ranges;
    }

    //region mail settings

    /**
     * @return array<mixed>
     */
    public function getMailTextsList(): array
    {
        $provider   = $this->getErpApiProvider();
        $mailLocale = [];

        foreach ($provider as $Provider) {
            $mailLocale = array_merge($mailLocale, $Provider->getMailLocale());
        }

        usort($mailLocale, function ($a, $b) {
            return strcmp($a['title'], $b['title']);
        });

        return $mailLocale;
    }
}
