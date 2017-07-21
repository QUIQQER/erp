<?php

/**
 * This file contains QUI\ERP\Payments\CashInputs
 */

namespace QUI\ERP\Payments;

use QUI;
use QUI\ERP\Currency\Handler as Currencies;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;

/**
 * Class CashInputs
 *
 * @package QUI\ERP\Payments
 */
class CashInputs extends QUI\Utils\Singleton
{
    /**
     * Return the payments cash inputs table name
     *
     * @return string
     */
    public function table()
    {
        return QUI::getDBTableName('payments_cash_inputs');
    }

    /**
     * Add a payment amount to specific object
     *
     * @param CashInput $CashInput
     * @param Order|OrderInProcess|Invoice|InvoiceTemporary $Parent
     */
    public function addPayment(CashInput $CashInput, $Parent)
    {
        QUI::getDataBase()->insert(
            $this->table(),
            $CashInput->toArray()
        );
    }

    /**
     * @param Invoice $Invoice
     * @return array
     */
    public function getPaymentsFromInvoice(Invoice $Invoice)
    {
        $result = QUI::getDataBase()->fetch(array(
            'from'  => $this->table(),
            'where' => array(
                'invoiceId' => $Invoice->getId(),
            ),
        ));

        return $this->parseResult($result);
    }

    /**
     * @param $Order
     * @return array
     */
    public function getPaymentsFromOrder(Order $Order)
    {
        $result = QUI::getDataBase()->fetch(array(
            'from'  => $this->table(),
            'where' => array(
                'orderId' => $Order->getId(),
            ),
        ));

        return $this->parseResult($result);
    }

    /**
     * @param array $result
     * @return array
     */
    protected function parseResult(array $result)
    {
        $cashInputs = array();

        foreach ($result as $entry) {
            $cashInputs[] = $this->parseDataToCashInput($entry);
        }

        return $cashInputs;
    }

    /**
     * Parse database data from a cash input to a cash input object
     *
     * @param array $data
     * @return CashInput
     */
    protected function parseDataToCashInput($data)
    {
        $CashInput = new CashInput();
        $Packages  = QUI::getPackageManager();

        if (isset($data['amount'])) {
            $CashInput->setAmount($data['amount']);
        }

        // currency
        if (isset($data['currency'])) {
            $currency = $data['currency'];
            $Currency = Currencies::getDefaultCurrency();

            if (is_string($currency)) {
                $currency = json_decode($currency, true);
            }

            if (is_array($currency) && isset($currency['code'])) {
                $Currency = Currencies::getCurrency($currency['code']);
            }

            $CashInput->setCurrency($Currency);
        }

        // user
        if (isset($data['user'])) {
            $userData = $data['user'];

            if (is_string($userData)) {
                $userData = json_decode($userData, true);
            }

            try {
                $User = QUI\ERP\User::convertUserDataToErpUser($userData);
                $CashInput->setUser($User);
            } catch (QUI\Exception $Exception) {
            }
        }

        // order
        if (isset($data['orderId'])
            && $Packages->isInstalled('quiqqer/order')) {
            try {
                $Orders = QUI\ERP\Order\Handler::getInstance();
                $Order  = $Orders->get($data['orderId']);
                $CashInput->setOrder($Order);
            } catch (QUI\Exception $Exception) {
            }
        }

        // order in process
        if (isset($data['orderInProcessId'])
            && $Packages->isInstalled('quiqqer/order')) {
            try {
                $Orders = QUI\ERP\Order\Handler::getInstance();
                $Order  = $Orders->getOrderInProcess($data['orderInProcessId']);
                $CashInput->setOrderInProgress($Order);
            } catch (QUI\Exception $Exception) {
            }
        }

        // invoice
        if (isset($data['invoiceId'])
            && $Packages->isInstalled('quiqqer/invoice')) {
            try {
                $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
                $Invoice  = $Invoices->getInvoice($data['invoiceId']);
                $CashInput->setInvoice($Invoice);
            } catch (QUI\Exception $Exception) {
            }
        }

        // invoice temporary
        if (isset($data['invoiceTemporaryId'])
            && $Packages->isInstalled('quiqqer/invoice')) {
            try {
                $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
                $Invoice  = $Invoices->getTemporaryInvoice($data['invoiceTemporaryId']);
                $CashInput->setInvoiceTemporary($Invoice);
            } catch (QUI\Exception $Exception) {
            }
        }

        return $CashInput;
    }
}
