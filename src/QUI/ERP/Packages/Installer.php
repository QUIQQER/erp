<?php

/**
 * This file contains QUI\ERP\Packages\Installer
 */

namespace QUI\ERP\Packages;

use QUI;

/**
 * Class Installer
 * - ERP package installer, installs ERP Packages to the system
 * - Easier installation for the ERP Stack
 *
 * @package QUI\ERP\Packages
 */
class Installer extends QUI\Utils\Singleton
{
    /**
     * List of packages and their requirements
     *
     * @var array
     */
    protected $packages = array(
        'quiqqer/areas' => array(
            'server' => array(
                'git@dev.quiqqer.com:quiqqer/areas.git'
            )
        ),

        'quiqqer/discount' => array(
            'server' => array(
                'git@dev.quiqqer.com:quiqqer/areas.git',
                'git@dev.quiqqer.com:quiqqer/discount.git',
                'git@dev.quiqqer.com:quiqqer/tax.git',
                'git@dev.quiqqer.com:quiqqer/products.git'
            )
        ),

        'quiqqer/invoice' => array(
            'server' => array(
                'git@dev.quiqqer.com:quiqqer/invoice.git'
            )
        ),

        'quiqqer/order' => array(
            'server' => array(
                'git@dev.quiqqer.com:quiqqer/order.git',
                'git@dev.quiqqer.com:quiqqer/products.git',
                'git@dev.quiqqer.com:quiqqer/areas.git',
                'git@dev.quiqqer.com:quiqqer/discount.git'
            )
        ),

        'quiqqer/products' => array(
            'server' => array(
                'git@dev.quiqqer.com:quiqqer/products.git',
                'git@dev.quiqqer.com:quiqqer/areas.git',
                'git@dev.quiqqer.com:quiqqer/discount.git'
            )
        ),

        'quiqqer/productstags' => array(
            'server' => array(
                'git@dev.quiqqer.com:quiqqer/productstags.git',
                'git@dev.quiqqer.com:quiqqer/products.git',
                'git@dev.quiqqer.com:quiqqer/areas.git',
                'git@dev.quiqqer.com:quiqqer/discount.git'
            )
        ),

        'quiqqer/productsimportexport' => array(
            'server' => array(
                'git@dev.quiqqer.com:quiqqer/productsimportexport.git',
                'git@dev.quiqqer.com:quiqqer/products.git',
                'git@dev.quiqqer.com:quiqqer/areas.git',
                'git@dev.quiqqer.com:quiqqer/discount.git'
            )
        ),

        'quiqqer/tax' => array(
            'server' => array(
                'git@dev.quiqqer.com:quiqqer/tax.git',
                'git@dev.quiqqer.com:quiqqer/areas.git'
            )
        ),

        'quiqqer/watchlist' => array(
            'server' => array(
                'git@dev.quiqqer.com:quiqqer/watchlist.git',
                'git@dev.quiqqer.com:quiqqer/products.git',
                'git@dev.quiqqer.com:quiqqer/areas.git',
                'git@dev.quiqqer.com:quiqqer/discount.git',
                'git@dev.quiqqer.com:quiqqer/htmltopdf.git'
            )
        ),
    );

    /**
     * Installs an erp package
     *
     * @param string $packageName - Package name
     * @throws Exception
     */
    public function install($packageName)
    {
        if (!in_array($packageName, $this->getPackageList())) {
            throw new Exception(array(
                'quiqqer/erp',
                'exception.package.is.not.erp.package'
            ));
        }

        $this->setPackageRequirements($packageName);

        $Packages = QUI::getPackageManager();
        $Packages->install($packageName);
    }

    /**
     * Return all ERP Package module names
     *
     * @return array
     */
    public function getPackageList()
    {
        return array_keys($this->packages);
    }

    /**
     * Return the package requirements
     *
     * @param $packageName
     * @return array
     * @throws Exception
     */
    protected function getPackageRequirements($packageName)
    {
        if (!in_array($packageName, $this->getPackageList())) {
            throw new Exception(array(
                'quiqqer/erp',
                'exception.erp.package.not.an.erp.package'
            ));
        }

        if (!isset($this->packages[$packageName])) {
            throw new Exception(array(
                'quiqqer/erp',
                'exception.erp.package.not.found'
            ));
        }

        return $this->packages[$packageName];
    }

    /**
     * Set all package requirements to the composer
     * - server
     *
     * @param $packageName
     * @throws Exception
     */
    public function setPackageRequirements($packageName)
    {
        if (!in_array($packageName, $this->getPackageList())) {
            throw new Exception(array(
                'quiqqer/erp',
                'exception.package.is.not.erp.package'
            ));
        }

        $requirements = $this->getPackageRequirements($packageName);
    }
}
