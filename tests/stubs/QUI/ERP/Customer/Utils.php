<?php

namespace QUI\ERP\Customer;

class Utils
{
    public static function getInstance(): self
    {
        return new self();
    }

    public function getContactEmailByCustomer(\QUI\Interfaces\Users\User $Customer): bool | string
    {
        return false;
    }

    public function getContactPersonAddress(\QUI\Interfaces\Users\User $Customer): false | \QUI\ERP\Address
    {
        return false;
    }
}
