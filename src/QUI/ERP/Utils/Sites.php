<?php

namespace QUI\ERP\Utils;

use QUI;
use QUI\Projects\Site\Utils as SiteUtils;

use function json_decode;

class Sites
{
    /**
     * Return the general terms and condition site
     *
     * @param QUI\Locale|null $Locale - in which language the page should be
     * @return QUI\Projects\Site|null
     */
    public static function getTermsAndConditions(QUI\Locale $Locale = null): ?QUI\Projects\Site
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $Config   = QUI::getPackage('quiqqer/erp')->getConfig();
        $language = $Locale->getCurrent();

        $terms = $Config->getValue('sites', 'terms_and_conditions');
        $terms = json_decode($terms, true);

        if (isset($terms[$language])) {
            try {
                return SiteUtils::getSiteByLink($terms[$language]);
            } catch (QUI\Exception $Exception) {
            }
        }

        return null;
    }

    /**
     * Return the general revocation site
     *
     * @param QUI\Locale|null $Locale - in which language the page should be
     * @return QUI\Projects\Site|null
     */
    public static function getRevocation(QUI\Locale $Locale = null): ?QUI\Projects\Site
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $Config   = QUI::getPackage('quiqqer/erp')->getConfig();
        $language = $Locale->getCurrent();

        $terms = $Config->getValue('sites', 'revocation');
        $terms = json_decode($terms, true);

        if (isset($terms[$language])) {
            try {
                return SiteUtils::getSiteByLink($terms[$language]);
            } catch (QUI\Exception $Exception) {
            }
        }

        return null;
    }

    /**
     * Return the general privacy policy site
     *
     * @param QUI\Locale|null $Locale - in which language the page should be
     * @return QUI\Projects\Site|null
     */
    public static function getPrivacyPolicy(QUI\Locale $Locale = null): ?QUI\Projects\Site
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $Config   = QUI::getPackage('quiqqer/erp')->getConfig();
        $language = $Locale->getCurrent();

        $terms = $Config->getValue('sites', 'privacy_policy');
        $terms = json_decode($terms, true);

        if (isset($terms[$language])) {
            try {
                return SiteUtils::getSiteByLink($terms[$language]);
            } catch (QUI\Exception $Exception) {
            }
        }

        return null;
    }
}
