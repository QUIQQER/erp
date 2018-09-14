<?php

/**
 * This file contains QUI\ERP\Process
 */

namespace QUI\ERP;

use QUI;
use QUI\Permissions\Permission;

/**
 * Class Process
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
     * Process constructor.
     *
     * @param string $processId - the global process id
     */
    public function __construct($processId)
    {
        $this->processId = $processId;
    }

    //region messages

    /**
     * Add a comment to the history
     *
     * @param string $comment
     */
    public function addHistory($comment)
    {
        $history = $this->getAttribute('history');
        $History = QUI\ERP\Comments::unserialize($history);

        $History->addComment($comment);

        $this->setAttribute('history', $History->toJSON());

        QUI::getDataBase()->update(
            Handler::getInstance()->invoiceTable(),
            ['history' => $History->toJSON()],
            ['id' => $this->processId]
        );
    }

    /**
     * Return the invoice history
     *
     * @return QUI\ERP\Comments
     */
    public function getHistory()
    {
        $history = $this->getAttribute('history');

        return QUI\ERP\Comments::unserialize($history);
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
     * Return all invoices from the process
     *
     * @return array
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
     * Return the order, if the process has an order
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
