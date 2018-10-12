<?php

/**
 * This file contains QUI\ERP\Requirements\Area
 */

namespace QUI\ERP\Requirements;

use QUI;
use QUI\ERP\Defaults;
use QUI\Requirements\Tests\Test;

/**
 * Class Area
 * - checks if a default zone is set
 *
 * @package QUI\ERP\Requirements
 */
class Area extends Test
{
    /**
     * Execute the test
     *
     * @return \QUI\Requirements\TestResult
     */
    public function run()
    {
        try {
            Defaults::getArea();

            return new QUI\Requirements\TestResult(
                QUI\Requirements\TestResult::STATUS_FAILED,
                QUI::getLocale()->get('quiqqer/erp', 'message.default.area.missing')
            );
        } catch (QUI\Exception $Exception) {
            return new QUI\Requirements\TestResult(
                QUI\Requirements\TestResult::STATUS_OK,
                QUI::getLocale()->get('quiqqer/erp', 'message.default.area.ok')
            );
        }
    }
}
