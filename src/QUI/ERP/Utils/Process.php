<?php

/**
 * This file contains QUI\ERP\Utils\Process
 */

namespace QUI\ERP\Utils;

use QUI;

use function array_map;

/**
 * Class Process
 *
 * @package QUI\ERP\Utils
 */
class Process
{
    /**
     * Return all information about a process
     *
     * @param string $hash - process hash
     * @return array
     *
     * @deprecated
     */
    public static function getProcessInformation(string $hash): array
    {
        $result = [];

        // order
        try {
            QUI::getPackage('quiqqer/order');

            $Order = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($hash);
            $result['order'] = $Order->getAttributes();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        // invoice
        try {
            QUI::getPackage('quiqqer/invoice');

            $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getInvoiceByString($hash);
            $result['invoice'] = $Invoice->getAttributes();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        // transactions
        try {
            QUI::getPackage('quiqqer/payment-transactions');

            $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
            $transactions = $Transactions->getTransactionsByHash($hash);

            $result['transactions'] = array_map(function ($Transaction) {
                return $Transaction->getAttributes();
            }, $transactions);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return $result;
    }
}
