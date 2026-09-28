<?php

// This bootstrap runs in an isolated process, in the copied endpoints' packages directory.
require (string)getenv('ERP_OUTPUT_TEST_AUTOLOAD');
require (string)getenv('ERP_OUTPUT_TEST_RESPONSE');

if (!defined('QUIQQER_BACKEND') || QUIQQER_BACKEND !== true) {
    throw new RuntimeException('Backend context must be selected before the bootstrap');
}

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace -- Replaces the global QUI facade in this process.
class QUI
{
    public static function getUserBySession(): object
    {
        return new class {
            public function canUseBackend(): bool
            {
                return getenv('ERP_OUTPUT_TEST_ALLOWED') === '1';
            }
        };
    }

    public static function getLocale(): object
    {
        return new class {
            public function get(string $group, string $key): string
            {
                return 'Permission denied';
            }
        };
    }

    public static function getRequest(): Symfony\Component\HttpFoundation\Request
    {
        if (getenv('ERP_OUTPUT_TEST_ALLOWED') === '1') {
            echo 'Authorized output reached';
            exit;
        }

        return Symfony\Component\HttpFoundation\Request::create('/?oid=output-dialog');
    }
}

register_shutdown_function(static function (): void {
    fwrite(STDERR, 'HTTP_STATUS=' . http_response_code());
});
