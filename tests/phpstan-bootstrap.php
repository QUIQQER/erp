<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

putenv("QUIQQER_OTHER_AUTOLOADERS=KEEP");

require_once __DIR__ . '/../../../../bootstrap.php';

if (!interface_exists(QUI\ERP\ErpEntityInterface::class, false)) {
    require_once __DIR__ . '/../src/QUI/ERP/ErpEntityInterface.php';
}

$optionalClassStubs = [
    QUI\ERP\Areas\Area::class => 'QUI/ERP/Areas/Area.php',
    QUI\ERP\Areas\Handler::class => 'QUI/ERP/Areas/Handler.php',
    QUI\ERP\Areas\Utils::class => 'QUI/ERP/Areas/Utils.php',
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
    QUI\ERP\Tax\Exception::class => 'QUI/ERP/Tax/Exception.php',
    QUI\ERP\Tax\TaxEntry::class => 'QUI/ERP/Tax/TaxEntry.php',
    QUI\ERP\Tax\TaxType::class => 'QUI/ERP/Tax/TaxType.php',
    QUI\ERP\Tax\Utils::class => 'QUI/ERP/Tax/Utils.php',
    QUI\ERP\Products\Field\Field::class => 'QUI/ERP/Products/Field/Field.php',
    QUI\ERP\Products\Field\Types\GroupList::class => 'QUI/ERP/Products/Field/Types/GroupList.php',
    QUI\ERP\Products\Product\UniqueProduct::class => 'QUI/ERP/Products/Product/UniqueProduct.php',
    QUI\ERP\Products\Product\Types\AbstractType::class => 'QUI/ERP/Products/Product/Types/AbstractType.php',
    QUI\ERP\Products\Product\Types\VariantParent::class => 'QUI/ERP/Products/Product/Types/VariantParent.php',
    QUI\ERP\Products\Handler\Fields::class => 'QUI/ERP/Products/Handler/Fields.php',
    QUI\ERP\Products\Handler\Products::class => 'QUI/ERP/Products/Handler/Products.php',
    QUI\ERP\Products\Controls\Products\ProductEdit::class => 'QUI/ERP/Products/Controls/Products/ProductEdit.php',
    QUI\ERP\Products\Utils\Package::class => 'QUI/ERP/Products/Utils/Package.php',
    QUI\ERP\Products\Utils\PriceFactor::class => 'QUI/ERP/Products/Utils/PriceFactor.php',
    QUI\ERP\Products\Interfaces\PriceFactorWithVatInterface::class
        => 'QUI/ERP/Products/Interfaces/PriceFactorWithVatInterface.php',
    QUI\ERP\Customer\Utils::class => 'QUI/ERP/Customer/Utils.php',
    QUI\ERP\Customer\CustomerFiles::class => 'QUI/ERP/Customer/CustomerFiles.php',
    QUI\ERP\Customer\NumberRange::class => 'QUI/ERP/Customer/NumberRange.php',
    QUI\ERP\SalesOrders\Handler::class => 'QUI/ERP/SalesOrders/Handler.php',
    QUI\ERP\SalesOrders\SalesOrder::class => 'QUI/ERP/SalesOrders/SalesOrder.php'
];

foreach ($optionalClassStubs as $className => $stubFile) {
    if (!class_exists($className, false)) {
        require_once __DIR__ . '/stubs/' . $stubFile;
    }
}
