<?php

namespace QUITests\ERP;

use QUI\ERP\ErpEntityCustomerFiles;
use QUI\ERP\User;

class CustomerFilesEntityFixture
{
    use ErpEntityCustomerFiles;

    /** @var array<string, mixed> */
    public array $customData = [];
    public bool $failWrites = false;

    public function __construct(private ?User $Customer)
    {
    }

    public function getCustomer(): ?User
    {
        if ($this->Customer === null) {
            throw new \QUI\Exception('Customer is unavailable');
        }

        return $this->Customer;
    }

    public function addCustomDataEntry(string $key, mixed $value): void
    {
        if ($this->failWrites) {
            throw new \QUI\Exception('Fixture persistence failed');
        }

        $this->customData[$key] = $value;
    }

    public function getCustomDataEntry(string $key): mixed
    {
        return $this->customData[$key] ?? null;
    }
}
