<?php

namespace QUI\ERP\Customer;

// Static lookup fixture, loaded only in isolated customer-file tests.
if (!class_exists(CustomerFiles::class, false)) {
    class CustomerFiles
    {
        public static array $files = [];

        public static function getFileByHash(int|string $customerId, string $fileHash): bool|array
        {
            return self::$files[$customerId][$fileHash] ?? false;
        }
    }
}
