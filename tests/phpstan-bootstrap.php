<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

putenv("QUIQQER_OTHER_AUTOLOADERS=KEEP");

require_once __DIR__ . '/../../../../bootstrap.php';

$optionalClassStubs = [
    QUI\ERP\Accounting\Invoice\Handler::class => 'QUI/ERP/Accounting/Invoice/Handler.php',
    QUI\ERP\Accounting\Invoice\Invoice::class => 'QUI/ERP/Accounting/Invoice/Invoice.php',
    QUI\ERP\Accounting\Invoice\InvoiceTemporary::class => 'QUI/ERP/Accounting/Invoice/InvoiceTemporary.php',
    QUI\ERP\Accounting\Invoice\Utils\Invoice::class => 'QUI/ERP/Accounting/Invoice/Utils/Invoice.php',
    QUI\ERP\Accounting\Offers\Handler::class => 'QUI/ERP/Accounting/Offers/Handler.php',
    QUI\ERP\Accounting\Offers\Offer::class => 'QUI/ERP/Accounting/Offers/Offer.php',
    QUI\ERP\Accounting\Payments\Transactions\Factory::class => 'QUI/ERP/Accounting/Payments/Transactions/Factory.php',
    QUI\ERP\Accounting\Payments\Transactions\Handler::class => 'QUI/ERP/Accounting/Payments/Transactions/Handler.php',
    QUI\ERP\Accounting\Payments\Transactions\Transaction::class => 'QUI/ERP/Accounting/Payments/Transactions/Transaction.php',
    QUI\ERP\Order\AbstractOrder::class => 'QUI/ERP/Order/AbstractOrder.php',
    QUI\ERP\Order\Handler::class => 'QUI/ERP/Order/Handler.php',
    QUI\ERP\Order\Order::class => 'QUI/ERP/Order/Order.php',
    QUI\ERP\Order\OrderInProcess::class => 'QUI/ERP/Order/OrderInProcess.php',
    QUI\ERP\SalesOrders\Handler::class => 'QUI/ERP/SalesOrders/Handler.php',
    QUI\ERP\SalesOrders\SalesOrder::class => 'QUI/ERP/SalesOrders/SalesOrder.php'
];

foreach ($optionalClassStubs as $className => $stubFile) {
    if (!class_exists($className)) {
        require_once __DIR__ . '/stubs/' . $stubFile;
    }
}
