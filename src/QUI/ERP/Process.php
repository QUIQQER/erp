<?php

/**
 * This file contains QUI\ERP\Process
 */

namespace QUI\ERP;

use QUI;

/**
 * Class Process
 * - represents a complete erp process
 *
 * @package QUI\ERP
 */
class Process
{
    /**
     * @var string
     */
    protected $processId;

    /**
     * @var null|array
     */
    protected $transactions = null;

    /**
     * @var null|QUI\ERP\Comments
     */
    protected $History = null;

    /**
     * Process constructor.
     *
     * @param string $processId - the global process id
     */
    public function __construct($processId)
    {
        $this->processId = $processId;
    }

    /**
     * Return the db table name
     *
     * @return string
     */
    protected function table()
    {
        return QUI::getDBTableName('process');
    }

    //region messages

    /**
     * Add a comment to the history for the complete process
     *
     * @param string $message
     * @param int|bool $time - optional, unix timestamp
     */
    public function addHistory($message, $time = false)
    {
        $this->getHistory()->addComment($message, $time);

        QUI::getDataBase()->update(
            $this->table(),
            ['history' => $this->getHistory()->toJSON()],
            ['id' => $this->processId]
        );
    }

    /**
     * Return the history of the process
     * This history only contains the process history
     *
     * If you want the complete history of all process objects, use getCompleteHistory()
     *
     * @return QUI\ERP\Comments
     */
    public function getHistory()
    {
        if ($this->History === null) {
            $result = QUI::getDataBase()->fetch([
                'from'  => $this->table(),
                'where' => [
                    'id' => $this->processId
                ],
                'limit' => 1
            ]);

            $history = '';

            if (isset($result[0]['history'])) {
                $history = $result[0]['history'];
            }

            $this->History = QUI\ERP\Comments::unserialize($history);
        }

        return $this->History;
    }

    /**
     * Return a complete history of all process objects
     * invoices invoices and orders
     *
     * @return Comments
     */
    public function getCompleteHistory()
    {
        $History = $this->getHistory();

        $invoices = $this->getInvoices();
        $orders   = $this->getOrders();

        foreach ($invoices as $Invoice) {
            $History->import($Invoice->getHistory());
        }

        foreach ($orders as $Order) {
            $History->import($Order->getHistory());
        }

        return $History;
    }

    //endregion

    //region invoice

    /**
     * Return if the process has invoices or not
     *
     * @return bool
     */
    public function hasInvoice()
    {
        $invoices = $this->getInvoices();

        foreach ($invoices as $Invoice) {
            if ($Invoice instanceof QUI\ERP\Accounting\Invoice\Invoice) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return if the process has temporary invoices or not
     *
     * @return bool
     */
    public function hasTemporaryInvoice()
    {
        $invoices = $this->getInvoices();

        foreach ($invoices as $Invoice) {
            if ($Invoice instanceof QUI\ERP\Accounting\Invoice\InvoiceTemporary) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Accounting\Invoice\Invoice[]|Accounting\Invoice\InvoiceTemporary[]
     */
    public function getInvoices()
    {
        $Handler  = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $invoices = $Handler->getInvoicesByGlobalProcessId($this->processId);

        return $invoices;
    }

    //endregion

    //region order

    /**
     * @return bool
     */
    public function hasOrder()
    {
        return !($this->getOrder() === null);
    }

    /**
     * Return the first order of the process
     *
     * @return null|Order\Order|Order\OrderInProcess|Order\Order|Order\Order
     */
    public function getOrder()
    {
        try {
            QUI::getPackage('quiqqer/order');
        } catch (QUI\Exception $Exception) {
            return null;
        }

        $OrderHandler = QUI\ERP\Order\Handler::getInstance();

        try {
            return $OrderHandler->getOrderByGlobalProcessId($this->processId);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        try {
            return $OrderHandler->getOrderByHash($this->processId);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return null;
    }

    /**
     * Return all orders from the process
     *
     * @return array|Order\Order|Order\Order[]|Order\OrderInProcess
     */
    public function getOrders()
    {
        try {
            QUI::getPackage('quiqqer/order');
        } catch (QUI\Exception $Exception) {
            return [];
        }

        return QUI\ERP\Order\Handler::getInstance()->getOrdersByGlobalProcessId($this->processId);
    }

    //endregion

    //region transactions

    /**
     * @return bool
     */
    public function hasTransactions()
    {
        $transactions = $this->getTransactions();

        return !!count($transactions);
    }

    /**
     * Return all related transactions
     *
     * @return QUI\ERP\Accounting\Payments\Transactions\Transaction[];
     */
    public function getTransactions()
    {
        try {
            QUI::getPackage('quiqqer/payment-transactions');
        } catch (QUI\Exception $Exception) {
            return [];
        }

        if ($this->transactions !== null) {
            return $this->transactions;
        }

        $Transactions       = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
        $this->transactions = $Transactions->getTransactionsByProcessId($this->processId);

        return $this->transactions;
    }

    //endregion
}
