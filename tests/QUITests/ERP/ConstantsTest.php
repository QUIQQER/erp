<?php

namespace QUITests\ERP;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Constants;

class ConstantsTest extends TestCase
{
    public function testInvoiceTypeAliasesMatch(): void
    {
        $this->assertSame(Constants::TYPE_INVOICE_REVERSAL, Constants::TYPE_INVOICE_STORNO);
    }

    public function testKnownPaymentAndOrderStatuses(): void
    {
        $this->assertSame(0, Constants::PAYMENT_STATUS_OPEN);
        $this->assertSame(1, Constants::PAYMENT_STATUS_PAID);
        $this->assertSame(2, Constants::ORDER_STATUS_STORNO);
        $this->assertSame(102, Constants::INVOICE_PRODUCT_TEXT_ID);
    }
}
