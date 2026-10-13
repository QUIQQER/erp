<?php

namespace QUITests\ERP;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Customer\CustomerFiles;
use QUI\ERP\ErpEntityCustomerFiles;
use QUI\ERP\User;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ErpEntityCustomerFilesRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Fixtures/CustomerFiles.php';
    }

    public static function parsingModes(): array
    {
        return [
            'raw references' => [false],
            'parsed files' => [true]
        ];
    }

    #[DataProvider('parsingModes')]
    public function testMissingFilesAreSkippedAndExistingFilesArePreserved(bool $parsing): void
    {
        $file = [
            'basename' => 'attachment.pdf',
            'extension' => 'pdf',
            'uploadTime' => 1791874800
        ];
        CustomerFiles::$files['customer-id']['existing-file'] = $file;
        $Entity = $this->createEntity();
        $existingEntry = [
            'hash' => 'existing-file',
            'options' => ['attachToEmail' => true]
        ];
        $entries = [
            ['hash' => 'missing-file'],
            $existingEntry
        ];
        $Entity->addCustomDataEntry('customer_files', $entries);

        $result = $Entity->getCustomerFiles($parsing);

        if ($parsing) {
            $file['uploadTime_formatted'] = QUI::getLocale()->formatDate($file['uploadTime']);
            $file['icon'] = QUI\Projects\Media\Utils::getIconByExtension('pdf');
            $existingEntry['file'] = $file;
        }

        $this->assertSame([$existingEntry], $result);
        $this->assertSame($entries, $Entity->getCustomDataEntry('customer_files'));
    }

    public static function uploadTimes(): array
    {
        return [
            'null timestamp' => [['uploadTime' => null], null],
            'missing timestamp' => [[], null],
            'failed filemtime' => [['uploadTime' => false], null],
            'empty timestamp' => [['uploadTime' => ''], null],
            'unix epoch' => [['uploadTime' => 0], 0],
            'string timestamp' => [['uploadTime' => '1791874800'], '1791874800']
        ];
    }

    #[DataProvider('uploadTimes')]
    public function testUploadTimeFormatting(array $metadata, int|string|null $expectedTimestamp): void
    {
        CustomerFiles::$files['customer-id']['existing-file'] = array_merge(
            [
                'basename' => 'attachment.pdf',
                'extension' => 'pdf'
            ],
            $metadata
        );
        $Entity = $this->createEntity();
        $Entity->addCustomDataEntry('customer_files', [['hash' => 'existing-file']]);

        $result = $Entity->getCustomerFiles(true);
        $expectedDate = $expectedTimestamp === null ? '' : QUI::getLocale()->formatDate($expectedTimestamp);

        $this->assertCount(1, $result);
        $this->assertSame('attachment.pdf', $result[0]['file']['basename']);
        $this->assertSame($expectedDate, $result[0]['file']['uploadTime_formatted']);
    }

    public function testMissingCustomerReturnsNoFiles(): void
    {
        $Entity = $this->createEntity(false);
        $Entity->addCustomDataEntry('customer_files', [['hash' => 'missing-file']]);

        $this->assertSame([], $Entity->getCustomerFiles(true));
    }

    private function createEntity(bool $withCustomer = true): object
    {
        $Customer = null;

        if ($withCustomer) {
            $Customer = $this->createMock(User::class);
            $Customer->method('getUUID')->willReturn('customer-id');
        }

        return new class ($Customer) {
            use ErpEntityCustomerFiles;

            private array $customData = [];

            public function __construct(private ?User $Customer)
            {
            }

            public function getCustomer(): ?User
            {
                return $this->Customer;
            }

            public function addCustomDataEntry(string $key, mixed $value): void
            {
                $this->customData[$key] = $value;
            }

            public function getCustomDataEntry(string $key): mixed
            {
                return $this->customData[$key] ?? null;
            }
        };
    }
}
