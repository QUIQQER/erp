<?php

/**
 * This file contains QUI\ERP\Requirements\Area
 */

namespace QUI\ERP\Requirements;

use QUI;
use QUI\ERP\Defaults;
use QUI\Requirements\TestResult;
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
     * @return TestResult
     */
    public function run(): TestResult
    {
        try {
            Defaults::getArea();

            return new TestResult(
                TestResult::STATUS_FAILED,
                QUI::getLocale()->get('quiqqer/erp', 'message.default.area.missing')
            );
        } catch (QUI\Exception $Exception) {
            return new TestResult(
                TestResult::STATUS_OK,
                QUI::getLocale()->get('quiqqer/erp', 'message.default.area.ok')
            );
        }
    }
}
