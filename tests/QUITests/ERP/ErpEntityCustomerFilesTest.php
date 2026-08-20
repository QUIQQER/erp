<?php

namespace QUITests\ERP;

use PHPUnit\Framework\TestCase;
use QUI\ERP\ErpEntityCustomerFiles;
use QUI\ERP\User;

require_once __DIR__ . '/Fixtures/CustomerFilesEntityFixture.php';

class ErpEntityCustomerFilesTest extends TestCase
{
    public function testStoredCustomerFilesKeepOnlySupportedMailOption(): void
    {
        $Entity = new CustomerFilesEntityFixture($this->createMock(User::class));

        $Entity->setCustomFiles([
            [
                'hash' => 'customer-file-a',
                'options' => ['attachToEmail' => true, 'internal' => 'must be removed']
            ],
            [
                'hash' => 'customer-file-b',
                'options' => 'invalid options'
            ]
        ]);

        self::assertSame([
            [
                'hash' => 'customer-file-a',
                'options' => ['attachToEmail' => true]
            ],
            [
                'hash' => 'customer-file-b',
                'options' => ['attachToEmail' => false]
            ]
        ], $Entity->customData['customer_files']);
    }

    public function testEmptyFileReplacementClearsPreviousEntries(): void
    {
        $Entity = new CustomerFilesEntityFixture($this->createMock(User::class));
        $Entity->customData['customer_files'] = [['hash' => 'old-file']];

        $Entity->setCustomFiles([]);

        self::assertSame([], $Entity->customData['customer_files']);
        self::assertSame([], $Entity->getCustomerFiles());
    }

    public function testCustomerLookupFailureHidesStoredFileReferences(): void
    {
        $Entity = new CustomerFilesEntityFixture(null);
        $Entity->customData['customer_files'] = [[
            'hash' => 'unavailable-customer-file',
            'options' => ['attachToEmail' => false]
        ]];

        self::assertSame([], $Entity->getCustomerFiles());
    }

    public function testClearCustomerFilesHandlesPersistenceFailure(): void
    {
        $Entity = new CustomerFilesEntityFixture($this->createMock(User::class));
        $Entity->failWrites = true;

        $Entity->clearCustomerFiles();

        self::assertSame([], $Entity->customData);
    }
}
