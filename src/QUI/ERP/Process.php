<?php

/**
 * This file contains QUI\ERP\Process
 */

namespace QUI\ERP;

use QUI;

use function count;

/**
 * Class Process
 * - represents a complete erp process
 * - Vorgangsnummer
 *
 * @package QUI\ERP
 */
class Process
{
    /**
     * @var string
     */
    protected string $processId;

    /**
     * @var null|array
     */
    protected ?array $transactions = null;

    /**
     * @var null|QUI\ERP\Comments
     */
    protected ?Comments $History = null;

    /**
     * Process constructor.
     *
     * @param string $processId - the global process id
     */
    public function __construct(string $processId)
    {
        $this->processId = $processId;
    }

    /**
     * Return the db table name
     *
     * @return string
     */
    protected function table(): string
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
    public function addHistory(string $message, $time = false)
    {
        $this->getHistory()->addComment($message, $time);

        try {
            QUI::getDataBase()->update(
                $this->table(),
                ['history' => $this->getHistory()->toJSON()],
                ['id' => $this->processId]
            );
        } catch (\QUI\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());
        }
    }

    /**
     * Return the history of the process
     * This history only contains the process history
     *
     * If you want the complete history of all process objects, use getCompleteHistory()
     *
     * @return QUI\ERP\Comments
     */
    public function getHistory(): Comments
    {
        if ($this->History === null) {
            $history = '';

            try {
                $result = QUI::getDataBase()->fetch([
                    'from' => $this->table(),
                    'where' => [
                        'id' => $this->processId
                    ],
                    'limit' => 1
                ]);

                if (isset($result[0]['history'])) {
                    $history = $result[0]['history'];
                } else {
                    QUI::getDataBase()->insert($this->table(), [
                        'id' => $this->processId
                    ]);
                }
            } catch (\QUI\Exception $Exception) {
                QUI\System\Log::addError($Exception->getMessage());
            }

            $this->History = QUI\ERP\Comments::unserialize($history);
        }

        return $this->History;
    }

    /**
     * Return a complete history of all process objects
     * invoices and orders
     *
     * @return Comments
     */
    public function getCompleteHistory(): Comments
    {
        $History = $this->getHistory();

        $invoices = $this->getInvoices();
        $orders = $this->getOrders();

        foreach ($invoices as $Invoice) {
            $History->import($Invoice->getHistory());
        }

        foreach ($orders as $Order) {
            $History->import($Order->getHistory());
        }

        try {
            QUI::getEvents()->fireEvent('quiqqerErpGetCompleteHistory', [$this, $this->processId]);
        } catch (\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            QUI::getEvents()->fireEvent('quiqqerErpProcessHistory', [$this, $this->processId]);
        } catch (\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
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
    public function hasInvoice(): bool
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
    public function hasTemporaryInvoice(): bool
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
    public function getInvoices(): array
    {
        try {
            return QUI\ERP\Accounting\Invoice\Handler::getInstance()->getInvoicesByGlobalProcessId($this->processId);
        } catch (\QUI\Exception $Exception) {
            return [];
        }
    }

    //endregion

    //region order

    /**
     * @return bool
     */
    public function hasOrder(): bool
    {
        return !($this->getOrder() === null);
    }

    /**
     * Return the first order of the process
     *
     * @return null|Order\Order|Order\OrderInProcess
     */
    public function getOrder(): Order\OrderInProcess|Order\Order|null
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
     * @return array|Order\Order|Order\Order[]|Order\OrderInProcess[]
     */
    public function getOrders(): array
    {
        try {
            QUI::getPackage('quiqqer/order');

            return QUI\ERP\Order\Handler::getInstance()->getOrdersByGlobalProcessId($this->processId);
        } catch (QUI\Exception $Exception) {
            return [];
        }
    }

    //endregion

    //region transactions

    /**
     * @return bool
     */
    public function hasTransactions(): bool
    {
        $transactions = $this->getTransactions();

        return !!count($transactions);
    }

    /**
     * Return all related transactions
     *
     * @return QUI\ERP\Accounting\Payments\Transactions\Transaction[];
     */
    public function getTransactions(): ?array
    {
        try {
            QUI::getPackage('quiqqer/payment-transactions');
        } catch (QUI\Exception $Exception) {
            return [];
        }

        if ($this->transactions !== null) {
            return $this->transactions;
        }

        $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
        $this->transactions = $Transactions->getTransactionsByProcessId($this->processId);

        return $this->transactions;
    }

    //endregion
}
