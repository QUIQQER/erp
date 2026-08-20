<?php

namespace QUITests\ERP\Accounting;

use QUI\ERP\ErpEntityInterface;

interface PaymentCalculationEntityInterface extends ErpEntityInterface
{
    public function getHash(): string;

    public function getCleanId(): int;
}
