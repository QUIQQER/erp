<?php

namespace QUI\ERP;

/**
 * Class Erp
 * - Main ERP Class for all const vars
 *
 * @package QUI\ERP
 */
class Constants
{
    const PAYMENT_STATUS_OPEN = 0;
    const PAYMENT_STATUS_PAID = 1;
    const PAYMENT_STATUS_PART = 2;
    const PAYMENT_STATUS_ERROR = 4;
    const PAYMENT_STATUS_CANCELED = 5;
    const PAYMENT_STATUS_DEBIT = 11;
    const PAYMENT_STATUS_PLAN = 12;

    /**
     * Order is only created
     */
    const ORDER_STATUS_CREATED = 0;

    /**
     * Order is posted (Invoice created)
     * Bestellung ist gebucht (Invoice erstellt)
     */
    const ORDER_STATUS_POSTED = 1; // Bestellung ist gebucht (Invoice erstellt)

    /**
     * Order is canceled
     */
    const ORDER_STATUS_STORNO = 2; // Bestellung ist storniert

    /**
     * @var int
     */
    const TYPE_INVOICE = 1;

    /**
     * @var int
     */
    const TYPE_INVOICE_TEMPORARY = 2;

    /**
     * Gutschrift / Credit note
     * @var int
     */
    const TYPE_INVOICE_CREDIT_NOTE = 3;

    // Storno types

    /**
     * Reversal, storno, cancellation
     */
    const TYPE_INVOICE_REVERSAL = 4;

    /**
     * Alias for reversal
     * @var int
     */
    const TYPE_INVOICE_STORNO = 4;

    /**
     * Status für alte stornierte Rechnung
     *
     * @var int
     */
    const TYPE_INVOICE_CANCEL = 5;

    /**
     * ID of the invoice product text field
     */
    const INVOICE_PRODUCT_TEXT_ID = 102;
}
