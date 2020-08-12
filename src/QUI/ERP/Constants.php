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
}
