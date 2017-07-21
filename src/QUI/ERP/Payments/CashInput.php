<?php

/**
 * This file contains QUI\ERP\Payments\CashInput
 */

namespace QUI\ERP\Payments;

use QUI;
use QUI\Interfaces\Users\User;

/**
 * Class CashInput
 *
 * @package QUI\ERP\Payments
 */
class CashInput
{
    /**
     * @var null|QUI\ERP\Accounting\Invoice\Invoice
     */
    protected $Invoice = null;

    /**
     * @var null|QUI\ERP\Accounting\Invoice\InvoiceTemporary
     */
    protected $InvoiceTemporary = null;

    /**
     * @var null|QUI\ERP\Order\Order
     */
    protected $Order = null;

    /**
     * @var null|QUI\ERP\Order\OrderInProcess
     */
    protected $OrderInProgress = null;

    /**
     * @var null|User
     */
    protected $User = null;

    /**
     * @var null|QUI\ERP\Currency\Currency
     */
    protected $Currency = null;

    /**
     * @var double
     */
    protected $amount = 0;

    /**
     * @var int
     */
    protected $date;

    /**
     * CashInput constructor.
     */
    public function __construct()
    {
        $this->Currency = QUI\ERP\Currency\Handler::getDefaultCurrency();
    }

    //region getter

    /**
     * Return the current amount of the cash input
     *
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Return the currency of the cash input
     *
     * @return null|QUI\ERP\Currency\Currency
     */
    public function getCurrency()
    {
        return $this->Currency;
    }

    /**
     * Returns the invoice which is associated with the cash input
     *
     * @return null|QUI\ERP\Accounting\Invoice\Invoice
     */
    public function getInvoice()
    {
        return $this->Invoice;
    }

    /**
     * Returns the invoice which is associated with the cash input
     *
     * @return null|QUI\ERP\Accounting\Invoice\InvoiceTemporary
     */
    public function getInvoiceTemporary()
    {
        return $this->InvoiceTemporary;
    }

    /**
     * Returns the order which is associated with the cash input
     *
     * @return null|QUI\ERP\Order\Order
     */
    public function getOrder()
    {
        return $this->Order;
    }

    /**
     * Returns the order which is associated with the cash input
     *
     * @return null|QUI\ERP\Order\OrderInProcess
     */
    public function getOrderInProgress()
    {
        return $this->OrderInProgress;
    }

    /**
     * @return null|User
     */
    public function getUser()
    {
        return $this->User;
    }

    //endregion

    //region setter

    /**
     * @param $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * @param QUI\ERP\Currency\Currency $Currency
     */
    public function setCurrency(QUI\ERP\Currency\Currency $Currency)
    {
        $this->Currency = $Currency;
    }

    /**
     * @param int $date
     */
    public function setDate($date)
    {
        $this->date = $date;
    }

    /**
     * @param QUI\ERP\Accounting\Invoice\Invoice $Invoice
     */
    public function setInvoice(QUI\ERP\Accounting\Invoice\Invoice $Invoice)
    {
        $this->Invoice = $Invoice;
    }

    /**
     * @param QUI\ERP\Accounting\Invoice\InvoiceTemporary $Invoice
     */
    public function setInvoiceTemporary(QUI\ERP\Accounting\Invoice\InvoiceTemporary $Invoice)
    {
        $this->InvoiceTemporary = $Invoice;
    }

    /**
     * @param QUI\ERP\Order\Order $Order
     */
    public function setOrder(QUI\ERP\Order\Order $Order)
    {
        $this->Order = $Order;
    }

    /**
     * @param QUI\ERP\Order\OrderInProcess $Order
     */
    public function setOrderInProgress(QUI\ERP\Order\OrderInProcess $Order)
    {
        $this->OrderInProgress = $Order;
    }

    /**
     * @param User $User
     */
    public function setUser(User $User)
    {
        $this->User = $User;
    }

    //endregion

    /**
     * Return the cash input as an array
     * This array fits as database values
     *
     * @return array
     */
    public function toArray()
    {
        // defaults
        $user               = '';
        $orderId            = '';
        $orderInProcessId   = '';
        $invoiceId          = '';
        $invoiceTemporaryId = '';


        if ($this->Order !== null) {
            $orderId = $this->Order->getId();
        }

        if ($this->OrderInProgress !== null) {
            $orderInProcessId = $this->OrderInProgress->getId();
        }

        if ($this->Invoice !== null) {
            $invoiceId = $this->Invoice->getId();
        }

        if ($this->InvoiceTemporary !== null) {
            $invoiceTemporaryId = $this->InvoiceTemporary->getId();
        }

        if ($this->User !== null) {
            $user = $this->User->getAttributes();
        }

        return array(
            'orderId'            => $orderId,
            'orderInProcessId'   => $orderInProcessId,
            'invoiceId'          => $invoiceId,
            'invoiceTemporaryId' => $invoiceTemporaryId,
            'amount'             => $this->amount,
            'date'               => $this->date,
            'currencyData'       => $this->Currency->toArray(),
            'user'               => $user,
        );
    }
}
