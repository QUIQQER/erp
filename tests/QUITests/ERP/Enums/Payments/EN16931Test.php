<?php

namespace QUITests\ERP\Enums\Payments;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Enums\Payments\EN16931;

class EN16931Test extends TestCase
{
    public function testEnumCaseCountAndSelectedValues(): void
    {
        $cases = EN16931::cases();

        $this->assertCount(12, $cases);
        $this->assertSame('10', EN16931::CASH->value);
        $this->assertSame('58', EN16931::SEPA_CREDIT_TRANSFER->value);
        $this->assertSame('ZZ', EN16931::MUTUALLY_DEFINED->value);
    }

    public function testFromResolvesCaseByValue(): void
    {
        $this->assertSame(EN16931::SEPA_DIRECT_DEBIT, EN16931::from('59'));
        $this->assertNull(EN16931::tryFrom('unknown'));
    }
}
