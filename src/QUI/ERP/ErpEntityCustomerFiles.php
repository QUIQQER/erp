<?php

namespace QUI\ERP;

use QUI;
use QUI\ERP\Customer\CustomerFiles;
use QUI\ERP\User as ErpUser;

use function is_array;

trait ErpEntityCustomerFiles // @phpstan-ignore-line
{
    // region needed methods

    abstract public function getCustomer(): ?ErpUser;

    abstract public function addCustomDataEntry(string $key, mixed $value): void;

    abstract public function getCustomDataEntry(string $key): mixed;

    //endregion

    // region extended customer file methods

    protected array $defaultCustomerFilesOptions = [
        'attachToEmail' => false
    ];

    /**
     * Add a customer file to this invoice
     *
     * @param string $fileHash - SHA256 hash of the file basename
     * @param array $options (optional) - File options; see $defaultOptions in code for what's possible
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function addCustomerFile(string $fileHash, array $options = []): void
    {
        $Customer = $this->getCustomer();
        $file = CustomerFiles::getFileByHash($Customer->getUUID(), $fileHash);

        if (empty($file)) {
            throw new Exception(
                QUI::getLocale()->get('quiqqer/invoice', 'exception.Invoice.addCustomerFile.file_not_found')
            );
        }

        $fileEntry = [
            'hash' => $fileHash,
            'options' => $this->cleanCustomerFilesOptions($options)
        ];

        $customerFiles = $this->getCustomerFiles();
        $customerFiles[] = $fileEntry;

        $this->addCustomDataEntry('customer_files', $customerFiles);
    }

    /**
     * @param array $files
     * @return void
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function setCustomFiles(array $files = []): void
    {
        $this->clearCustomerFiles();

        if (empty($files)) {
            return;
        }

        $customerFiles = [];

        foreach ($files as $file) {
            $options = $file['options'];

            if (!is_array($options)) {
                $options = [];
            }

            $fileEntry = [
                'hash' => $file['hash'],
                'options' => $this->cleanCustomerFilesOptions($options)
            ];

            $customerFiles[] = $fileEntry;
        }

        $this->addCustomDataEntry('customer_files', $customerFiles);
    }

    /**
     * Clear customer files
     *
     * @return void
     */
    public function clearCustomerFiles(): void
    {
        try {
            $this->addCustomDataEntry('customer_files', []);
        } catch (\Exception $e) {
            QUI\System\Log::addError($e->getMessage());
        }
    }

    /**
     * Get customer files that are attached to this invoice.
     *
     * @param bool $parsing -  true = parses the file hash
     * ]
     * @return array - Contains file hash and file options
     */
    public function getCustomerFiles(bool $parsing = false): array
    {
        $customerFiles = $this->getCustomDataEntry('customer_files');

        if (empty($customerFiles)) {
            return [];
        }

        try {
            $Customer = $this->getCustomer();
        } catch (\Exception) {
            return [];
        }

        $result = [];

        foreach ($customerFiles as $customerFile) {
            try {
                // check if file is from customer
                $file = CustomerFiles::getFileByHash($Customer->getUUID(), $customerFile['hash']);

                if ($parsing) {
                    $file['uploadTime_formatted'] = QUI::getLocale()->formatDate($file['uploadTime']);
                    $file['icon'] = QUI\Projects\Media\Utils::getIconByExtension($file['extension']);

                    $customerFile['file'] = $file;
                }

                $result[] = $customerFile;
            } catch (\Exception) {
            }
        }

        return $result;
    }

    /**
     * cleans a customer files option array
     *
     * @param array $options
     * @return array
     */
    protected function cleanCustomerFilesOptions(array $options = []): array
    {
        // set default options
        foreach ($this->defaultCustomerFilesOptions as $k => $v) {
            if (!isset($options[$k])) {
                $options[$k] = $v;
            }
        }

        // cleanup
        foreach ($options as $k => $v) {
            if (!isset($this->defaultCustomerFilesOptions[$k])) {
                unset($options[$k]);
            }
        }

        return $options;
    }

    // endregion
}
