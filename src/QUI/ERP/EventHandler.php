<?php

/**
 * This file contains QUI\ERP\EventHandler
 */

namespace QUI\ERP;

use QUI;
use QUI\ERP\BankAccounts\Handler as BankAccounts;
use QUI\ERP\Products\Handler\Fields as ProductFields;
use QUI\Package\Package;
use QUI\Smarty\Collector;
use Smarty;
use SmartyException;

use function array_flip;
use function class_exists;
use function dirname;
use function explode;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Class EventHandler
 *
 * @package QUI\ERP
 */
class EventHandler
{
    /**
     * event : on admin load footer
     */
    public static function onAdminLoadFooter(): void
    {
        echo '<link href="' . URL_OPT_DIR . 'quiqqer/erp/bin/backend/payment-status.css" rel="stylesheet" type="text/css" />';
        echo '<script>window.ERP_ENTITY_ICONS = ' . json_encode(QUI\ERP\Utils\Utils::$entityIcons) . '</script>';
        echo '<script src="' . URL_OPT_DIR . 'quiqqer/erp/bin/load.js"></script>';
    }

    /**
     * @param QUI\Template $Template
     */
    public static function onTemplateGetHeader(QUI\Template $Template): void
    {
        try {
            $Package = QUI::getPackage('quiqqer/erp');
            $areas = $Package->getConfig()->get('general', 'customerRequestWindow');

            if (empty($areas)) {
                return;
            }

            $areas = explode(',', $areas);
        } catch (\QUI\Exception) {
            return;
        }

        if (count($areas)) {
            $Template->extendHeaderWithJavaScriptFile(
                URL_OPT_DIR . 'quiqqer/erp/bin/frontend.js'
            );
        }
    }

    /**
     * event: on package setup
     *
     * @param Package $Package
     * @throws QUI\Exception
     */
    public static function onPackageSetup(Package $Package): void
    {
        if ($Package->getName() !== 'quiqqer/erp') {
            return;
        }

        self::createDefaultManufacturerGroup();
        self::patchBankAccount();
    }

    /**
     * Create a default manufacturer group if none exists yet.
     *
     * @return void
     */
    public static function createDefaultManufacturerGroup(): void
    {
        try {
            $Conf = QUI::getPackage('quiqqer/erp')->getConfig();
            $defaultGroupId = $Conf->get('manufacturers', 'groupId');

            if (!empty($defaultGroupId)) {
                return;
            }

            $Root = QUI::getGroups()->firstChild();

            $Manufacturers = $Root->createChild(
                QUI::getLocale()->get('quiqqer/erp', 'manufacturers.default_group_name'),
                QUI::getUsers()->getSystemUser()
            );

            $Conf->setValue('manufacturers', 'groupId', $Manufacturers->getUUID());
            $Conf->save();

            $Manufacturers->activate();

            // Add manufacturer group ID to product manufacturer field
            if (QUI::getPackageManager()->isInstalled('quiqqer/products')) {
                try {
                    /** @var QUI\ERP\Products\Field\Types\GroupList $ProductField */
                    $ProductField = ProductFields::getField(ProductFields::FIELD_MANUFACTURER);
                    $groupIds = $ProductField->getOption('groupIds');

                    if (empty($groupIds)) {
                        $groupIds = [];
                    }

                    $groupIds[] = $Manufacturers->getUUID();
                    $ProductField->setOption('groupIds', $groupIds);
                    $ProductField->save();
                } catch (\Exception $Exception) {
                    QUI\System\Log::writeDebugException($Exception);
                }
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Patches old bank account data structure to new one.
     *
     * @return void
     * @throws QUI\Exception
     */
    public static function patchBankAccount(): void
    {
        $Conf = QUI::getPackage('quiqqer/erp')->getConfig();
        $bankAccountPatched = $Conf->getValue('bankAccounts', 'isPatched');

        if (!empty($bankAccountPatched)) {
            return;
        }

        // Create new bank account with existing details
        $bankName = $Conf->get('company', 'bankName');
        $bankIban = $Conf->get('company', 'bankIban');
        $bankBic = $Conf->get('company', 'bankBic');
        $companyName = $Conf->get('company', 'name');

        if (empty($bankIban) || empty($bankBic) || empty($companyName)) {
            $Conf->setValue('bankAccounts', 'isPatched', 1);
            $Conf->save();

            return;
        }

        $bankAccountData = [
            'name' => $bankName ?: $bankIban,
            'iban' => $bankIban,
            'bic' => $bankBic,
            'title' => $companyName,
            'accountHolder' => $companyName,
            'default' => true
        ];

        try {
            $bankAccount = BankAccounts::addBankAccount($bankAccountData);
            $Conf->setValue('company', 'bankAccountId', $bankAccount['id']);

            QUI\System\Log::addInfo(
                QUI::getLocale()->get(
                    'quiqqer/erp',
                    'bankAccounts.patch.bankAccount_created'
                )
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException(
                $Exception,
                QUI\System\Log::LEVEL_ERROR,
                [
                    'operation' => 'Creating default bank account based on ecoyn bank / company config.'
                ]
            );
        }

        $Conf->setValue('bankAccounts', 'isPatched', 1);
        $Conf->save();
    }

    /**
     * @param Package $Package
     * @param array $params
     */
    public static function onPackageConfigSave(QUI\Package\Package $Package, array $params): void
    {
        if ($Package->getName() !== 'quiqqer/erp') {
            return;
        }

        $languages = QUI::availableLanguages();
        $languages = array_flip($languages);

        try {
            $Config = $Package->getConfig();

            // timestampFormat
            if (isset($params['timestampFormat'])) {
                foreach ($params['timestampFormat'] as $language => $format) {
                    if (isset($languages[$language])) {
                        $Config->setValue('timestampFormat', $language, $format);
                    }
                }
            }

            // dateFormat
            if (isset($params['dateFormat'])) {
                foreach ($params['dateFormat'] as $language => $format) {
                    if (isset($languages[$language])) {
                        $Config->setValue('dateFormat', $language, $format);
                    }
                }
            }

            $Config->save();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * event: on user save
     * @param QUI\Interfaces\Users\User $User
     * @todo prüfung auch für steuernummer
     *
     */
    public static function onUserSave(QUI\Interfaces\Users\User $User): void
    {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        // eu vat id validation
        try {
            $Package = QUI::getPackage('quiqqer/tax');
            $validate = $Package->getConfig()->getValue('shop', 'validateVatId');
            $vatId = $User->getAttribute('quiqqer.erp.euVatId');

            if ($validate && !empty($vatId)) {
                try {
                    $vatId = QUI\ERP\Tax\Utils::validateVatId($vatId);
                } catch (QUI\ERP\Tax\Exception $Exception) {
                    if ($Exception->getCode() !== 503) {
                        throw $Exception;
                    }

                    $vatId = QUI\ERP\Tax\Utils::cleanupVatId($vatId);
                }
            } elseif ($vatId) {
                $vatId = QUI\ERP\Tax\Utils::cleanupVatId($vatId);
            }

            $User->setAttribute('quiqqer.erp.euVatId', $vatId);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        // netto brutto user status
        // @todo im admin muss dieser schritt seperat gemacht werden
        // @todo im admin muss festgelegt werden was der nutzer ist
        // @todo das muss in das customer modul rein
//        $User->setAttribute('quiqqer.erp.isNettoUser', false); // reset status
//
//        $User->setAttribute(
//            'quiqqer.erp.isNettoUser',
//            QUI\ERP\Utils\User::getBruttoNettoUserStatus($User)
//        );
    }

    /**
     * event: on user save
     * saves the vat number
     *
     *
     * @param QUI\Users\User $User
     * @throws QUI\Exception
     */
    public static function onUserSaveBegin(QUI\Users\User $User): void
    {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        if (QUI::isBackend()) {
            return;
        }

        $Request = QUI::getRequest()->request;
        $data = $Request->all();

        if (empty($data)) {
            return;
        }

        if (isset($data['data'])) {
            $data = json_decode($data['data'], true);
        }

        if (isset($data['company'])) {
            try {
                $Address = $User->getStandardAddress();
                $Address->setAttribute(
                    'company',
                    QUI\Utils\Security\Orthos::clear($data['company'])
                );
                $Address->save();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        if (isset($data['vatId'])) {
            $vatId = $data['vatId'];

            if (
                class_exists('QUI\ERP\Tax\Utils')
                && QUI\ERP\Tax\Utils::shouldVatIdValidationBeExecuted()
                && !empty($vatId)
            ) {
                $vatId = QUI\ERP\Tax\Utils::validateVatId($vatId);
            }

            // save VAT ID
            $User->setAttribute('quiqqer.erp.euVatId', QUI\Utils\Security\Orthos::clear($vatId));
        }

        if (isset($data['chUID'])) {
            $User->setAttribute('quiqqer.erp.chUID', QUI\Utils\Security\Orthos::clear($data['chUID']));
        }
    }

    /**
     * event: on user address save
     *
     * @param QUI\Users\Address $Address
     * @param QUI\Users\User $User
     */
    public static function onUserAddressSave(QUI\Users\Address $Address, QUI\Users\User $User): void
    {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        $Request = QUI::getRequest()->request;
        $data = $Request->get('data');

        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (empty($data) || !is_array($data)) {
            return;
        }

        if (isset($data['vatId'])) {
            $vatId = $data['vatId'];

            try {
                if (
                    class_exists('QUI\ERP\Tax\Utils')
                    && QUI\ERP\Tax\Utils::shouldVatIdValidationBeExecuted()
                    && !empty($vatId)
                ) {
                    $vatId = QUI\ERP\Tax\Utils::validateVatId($vatId);
                }

                // save VAT ID
                $User->setAttribute('quiqqer.erp.euVatId', $vatId);
                $User->save();
            } catch (QUI\ERP\Tax\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }
    }

    //region smarty

    /**
     * register erp smarty functions
     *
     * @param Smarty $Smarty - \Smarty
     * @throws SmartyException
     */
    public static function onSmartyInit(Smarty $Smarty): void
    {
        // {pace}
        if (
            !isset($Smarty->registered_plugins['function']) ||
            !isset($Smarty->registered_plugins['function']['getPrefixedNumber'])
        ) {
            $Smarty->registerPlugin(
                "function",
                "erpGetPrefixedNumber",
                "\\QUI\\ERP\\EventHandler::getPrefixedNumber"
            );
        }
    }

    /**
     * erp smarty function {getPrefixedNumber}
     *
     * @param array $params
     * @param $smarty
     * @return string
     * @example {erpGetPrefixedNumber assign=prefixedNumber var=$erpUUID}
     *
     */
    public static function getPrefixedNumber(array $params, $smarty): string
    {
        $prefixedNumber = '';

        if (empty($params['var'])) {
            return '';
        }

        $var = $params['var'];

        if (is_array($var) && isset($var['prefixedNumber'])) {
            $prefixedNumber = $var['prefixedNumber'];
        } elseif (is_array($var) && isset($var['hash'])) {
            try {
                $Entity = (new Processes())->getEntity($var['hash']);
                $prefixedNumber = $Entity->getPrefixedNumber();
            } catch (QUI\Exception) {
            }
        } else {
            try {
                $Entity = (new Processes())->getEntity($params['var']);
                $prefixedNumber = $Entity->getPrefixedNumber();
            } catch (QUI\Exception) {
            }
        }

        if (!isset($params['assign'])) {
            return $prefixedNumber;
        }

        $smarty->assign($params['assign'], $prefixedNumber);
        return '';
    }

    //endregion

    //region user profile extension

    /**
     * @param Collector $Collector
     * @param QUI\Interfaces\Users\User $User
     * @param QUI\Users\Address $Address
     */
    public static function onFrontendUserCustomerBegin(
        Collector $Collector,
        QUI\Interfaces\Users\User $User,
        QUI\Users\Address $Address
    ): void {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        $Engine = QUI::getTemplateManager()->getEngine();

        // business type
        $businessType = 'b2c';
        $company = $Address->getAttribute('company');

        if (!empty($company)) {
            $businessType = 'b2b';
        }

        if ($User->getAttribute('quiqqer.erp.euVatId')) {
            $businessType = 'b2b';
        }

        // template data
        $Engine->assign([
            'User' => $User,
            'Address' => $Address,
            'businessType' => $businessType,

            'businessTypeIsChangeable' => !(QUI\ERP\Utils\Shop::isOnlyB2C() || QUI\ERP\Utils\Shop::isOnlyB2B()),
            'isB2C' => QUI\ERP\Utils\Shop::isB2C(),
            'isB2B' => QUI\ERP\Utils\Shop::isB2B(),
            'isOnlyB2B' => QUI\ERP\Utils\Shop::isOnlyB2B(),
        ]);

        try {
            $Collector->append(
                $Engine->fetch(dirname(__FILE__) . '/FrontendUsers/customerData.html')
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * @param Collector $Collector
     * @param QUI\Interfaces\Users\User $User
     * @param QUI\Users\Address $Address
     */
    public static function onFrontendUserDataMiddle(
        Collector $Collector,
        QUI\Interfaces\Users\User $User,
        QUI\Users\Address $Address
    ): void {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        $Engine = QUI::getTemplateManager()->getEngine();

        $Engine->assign([
            'User' => $User,
            'Address' => $Address,

            'businessTypeIsChangeable' => !(QUI\ERP\Utils\Shop::isOnlyB2C() || QUI\ERP\Utils\Shop::isOnlyB2B()),
            'isB2C' => QUI\ERP\Utils\Shop::isB2C(),
            'isB2B' => QUI\ERP\Utils\Shop::isB2B(),
            'isOnlyB2B' => QUI\ERP\Utils\Shop::isOnlyB2B(),
        ]);

        try {
            $Collector->append(
                $Engine->fetch(dirname(__FILE__) . '/FrontendUsers/profileData.html')
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * @param Collector $Collector
     * @param QUI\Interfaces\Users\User $User
     */
    public static function onFrontendUserAddressCreateBegin(Collector $Collector, QUI\Interfaces\Users\User $User): void
    {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        $Engine = QUI::getTemplateManager()->getEngine();

        try {
            $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();
            $settings = $Conf->getValue('profile', 'addressFields');

            if (!empty($settings)) {
                $settings = json_decode($settings, true);
            } else {
                $settings = [];
            }

            $Engine->assign('settings', QUI\FrontendUsers\Controls\Address\Address::checkSettingsArray($settings));
        } catch (QUI\Exception) {
            $Engine->assign('settings', QUI\FrontendUsers\Controls\Address\Address::checkSettingsArray([]));
        }

        $Engine->assign([
            'User' => $User,

            'businessTypeIsChangeable' => !(QUI\ERP\Utils\Shop::isOnlyB2C() || QUI\ERP\Utils\Shop::isOnlyB2B()),
            'isB2C' => QUI\ERP\Utils\Shop::isB2C(),
            'isB2B' => QUI\ERP\Utils\Shop::isB2B(),
            'isOnlyB2B' => QUI\ERP\Utils\Shop::isOnlyB2B(),
            'isOnlyB2C' => QUI\ERP\Utils\Shop::isOnlyB2C(),
        ]);

        try {
            $Collector->append(
                $Engine->fetch(dirname(__FILE__) . '/FrontendUsers/createAddressBegin.html')
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * @param Collector $Collector
     * @param QUI\Interfaces\Users\User $User
     */
    public static function onFrontendUserAddressCreateEnd(Collector $Collector, QUI\Interfaces\Users\User $User): void
    {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        $Engine = QUI::getTemplateManager()->getEngine();

        try {
            $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();
            $settings = $Conf->getValue('profile', 'addressFields');

            if (!empty($settings)) {
                $settings = json_decode($settings, true);
            } else {
                $settings = [];
            }

            $Engine->assign('settings', QUI\FrontendUsers\Controls\Address\Address::checkSettingsArray($settings));
        } catch (QUI\Exception) {
            $Engine->assign('settings', QUI\FrontendUsers\Controls\Address\Address::checkSettingsArray([]));
        }

        $Engine->assign([
            'User' => $User,

            'businessTypeIsChangeable' => !(QUI\ERP\Utils\Shop::isOnlyB2C() || QUI\ERP\Utils\Shop::isOnlyB2B()),
            'isB2C' => QUI\ERP\Utils\Shop::isB2C(),
            'isB2B' => QUI\ERP\Utils\Shop::isB2B(),
            'isOnlyB2B' => QUI\ERP\Utils\Shop::isOnlyB2B(),
            'isOnlyB2C' => QUI\ERP\Utils\Shop::isOnlyB2C(),
        ]);

        try {
            $Collector->append(
                $Engine->fetch(dirname(__FILE__) . '/FrontendUsers/createAddressEnd.html')
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * @param Collector $Collector
     * @param QUI\Interfaces\Users\User $User
     * @param QUI\Users\Address $Address
     */
    public static function onFrontendUserAddressEditBegin(
        Collector $Collector,
        QUI\Interfaces\Users\User $User,
        QUI\Users\Address $Address
    ): void {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        $Engine = QUI::getTemplateManager()->getEngine();

        try {
            $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();
            $settings = $Conf->getValue('profile', 'addressFields');

            if (!empty($settings)) {
                $settings = json_decode($settings, true);
            } else {
                $settings = [];
            }

            $Engine->assign('settings', QUI\FrontendUsers\Controls\Address\Address::checkSettingsArray($settings));
        } catch (QUI\Exception) {
            $Engine->assign('settings', QUI\FrontendUsers\Controls\Address\Address::checkSettingsArray([]));
        }

        // business type
        $businessType = 'b2c';
        $company = $Address->getAttribute('company');

        if (!empty($company)) {
            $businessType = 'b2b';
        }

        if ($User->getAttribute('quiqqer.erp.euVatId')) {
            $businessType = 'b2b';
        }

        // template data
        $Engine->assign([
            'User' => $User,
            'Address' => $Address,
            'businessType' => $businessType,

            'businessTypeIsChangeable' => !(QUI\ERP\Utils\Shop::isOnlyB2C() || QUI\ERP\Utils\Shop::isOnlyB2B()),
            'isB2C' => QUI\ERP\Utils\Shop::isB2C(),
            'isB2B' => QUI\ERP\Utils\Shop::isB2B(),
            'isOnlyB2B' => QUI\ERP\Utils\Shop::isOnlyB2B(),
            'isOnlyB2C' => QUI\ERP\Utils\Shop::isOnlyB2C(),
        ]);

        try {
            $Collector->append(
                $Engine->fetch(dirname(__FILE__) . '/FrontendUsers/editAddressBegin.html')
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * @param Collector $Collector
     * @param QUI\Interfaces\Users\User $User
     * @param QUI\Users\Address $Address
     */
    public static function onFrontendUserAddressEditEnd(
        Collector $Collector,
        QUI\Interfaces\Users\User $User,
        QUI\Users\Address $Address
    ): void {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        $Engine = QUI::getTemplateManager()->getEngine();

        try {
            $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();
            $settings = $Conf->getValue('profile', 'addressFields');

            if (!empty($settings)) {
                $settings = json_decode($settings, true);
            } else {
                $settings = [];
            }

            $Engine->assign('settings', QUI\FrontendUsers\Controls\Address\Address::checkSettingsArray($settings));
        } catch (QUI\Exception) {
            $Engine->assign('settings', QUI\FrontendUsers\Controls\Address\Address::checkSettingsArray([]));
        }

        $Engine->assign([
            'User' => $User,
            'Address' => $Address,

            'businessTypeIsChangeable' => !(QUI\ERP\Utils\Shop::isOnlyB2C() || QUI\ERP\Utils\Shop::isOnlyB2B()),
            'isB2C' => QUI\ERP\Utils\Shop::isB2C(),
            'isB2B' => QUI\ERP\Utils\Shop::isB2B(),
            'isOnlyB2B' => QUI\ERP\Utils\Shop::isOnlyB2B(),
            'isOnlyB2C' => QUI\ERP\Utils\Shop::isOnlyB2C(),
        ]);

        try {
            $Collector->append(
                $Engine->fetch(dirname(__FILE__) . '/FrontendUsers/editAddressEnd.html')
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    //endregion
}
