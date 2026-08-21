<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

require_once __DIR__ . '/QUITests/ERP/DatabaseEnvironment.php';

putenv('QUIQQER_OTHER_AUTOLOADERS=KEEP');

require_once __DIR__ . '/../../../../bootstrap.php';

spl_autoload_register(static function (string $className): void {
    $namespace = 'QUI\\ERP\\';

    if (!str_starts_with($className, $namespace)) {
        return;
    }

    $classFile = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $className) . '.php';

    if (is_file($classFile)) {
        require_once $classFile;
    }
}, true, true);

require_once __DIR__ . '/../../../autoload.php';
require_once __DIR__ . '/QUITests/ERP/DatabaseTestCase.php';

foreach (
    [
    'QUI/ERP/Accounting/Invoice/Handler.php',
    'QUI/ERP/Accounting/Offers/Handler.php',
    'QUI/ERP/Accounting/Payments/Transactions/Factory.php',
    'QUI/ERP/Accounting/Payments/Transactions/Handler.php',
    'QUI/ERP/Order/Handler.php',
    'QUI/ERP/SalesOrders/Handler.php'
    ] as $stubFile
) {
    $className = str_replace(['/', '.php'], ['\\', ''], $stubFile);

    if (!class_exists($className, false)) {
        require_once __DIR__ . '/stubs/' . $stubFile;
    }
}
