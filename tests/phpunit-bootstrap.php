<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

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
