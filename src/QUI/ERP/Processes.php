<?php


namespace QUI\ERP;

use QUI;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Offers\Handler as OfferHandler;
use QUI\ERP\Order\Handler as OrderHandler;
use QUI\ERP\Purchasing\Processes\Handler as PurchasingHandler;
use QUI\ERP\SalesOrders\Handler as SalesOrdersHandler;

/**
 *
 * Reads following modules for global process id entities
 * - quiqqer/invoice
 * - quiqqer/order
 * - quiqqer/offer
 * - quiqqer/salesorders
 * - quiqqer/purchasing
 * - quiqqer/booking
 * - quiqqer/contracts
 * - quiqqer/payments
 * - quiqqer/delivery-notes
 */
class Processes
{
    protected array $list = [];

    /**
     * @return array
     */
    public function getList(): array
    {
        try {
            $this->readInvoices();
        } catch (QUI\Database\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readOrders();
        } catch (QUI\Database\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readOffers();
        } catch (QUI\Database\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readSalesOrders();
        } catch (QUI\Database\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readPurchasing();
        } catch (QUI\Database\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }


        uasort($this->list, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return $this->list;
    }

    /**
     * Returns the plugin list with which plugins the processes can handle
     *
     * @return string[]
     */
    public function getWantedPluginList(): array
    {
        return [
            'quiqqer/invoice',
            'quiqqer/order',
            'quiqqer/offers',
            'quiqqer/salesorders',
            'quiqqer/purchasing',
            'quiqqer/booking',
            'quiqqer/contracts',
            'quiqqer/payments',
            'quiqqer/delivery-notes'
        ];
    }

    //region read databases

    /**
     * Rads all invoices
     *
     * @return void
     * @throws QUI\Database\Exception
     */
    protected function readInvoices(): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/invoice')) {
            return;
        }

        $invoices = QUI::getDatabase()->fetch([
            'select' => 'hash,global_process_id,date',
            'from' => InvoiceHandler::getInstance()->invoiceTable()
        ]);

        foreach ($invoices as $invoice) {
            $gpi = $invoice['global_process_id'];

            if (empty($gpi)) {
                $gpi = $invoice['hash'];
            }

            if (!isset($this->list[$gpi])) {
                $this->list[$gpi] = [];
            }

            $this->list[$gpi]['date'] = $this->getEarlierDate(
                $this->list[$gpi]['date'] ?? null,
                $invoice['date']
            );

            $this->list[$gpi]['invoice'] = $invoice['hash'];
        }
    }

    /**
     * Rads all invoices
     *
     * @return void
     * @throws QUI\Database\Exception
     */
    protected function readOrders(): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/order')) {
            return;
        }

        $orders = QUI::getDatabase()->fetch([
            'select' => 'hash,global_process_id,c_date',
            'from' => OrderHandler::getInstance()->table()
        ]);

        foreach ($orders as $order) {
            $gpi = $order['global_process_id'];

            if (empty($gpi)) {
                $gpi = $order['hash'];
            }

            if (!isset($this->list[$gpi])) {
                $this->list[$gpi] = [];
            }

            $this->list[$gpi]['date'] = $this->getEarlierDate(
                $this->list[$gpi]['date'] ?? null,
                $order['c_date']
            );

            $this->list[$gpi]['order'] = $order['hash'];
        }
    }

    /**
     * Read all offers
     *
     * @return void
     * @throws QUI\Database\Exception
     */
    protected function readOffers(): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/offers')) {
            return;
        }

        $offers = QUI::getDatabase()->fetch([
            'select' => 'hash,global_process_id,date',
            'from' => OfferHandler::getInstance()->offersTable()
        ]);

        foreach ($offers as $offer) {
            $gpi = $offer['global_process_id'];

            if (empty($gpi)) {
                $gpi = $offer['hash'];
            }

            if (!isset($this->list[$gpi])) {
                $this->list[$gpi] = [];
            }

            $this->list[$gpi]['date'] = $this->getEarlierDate(
                $this->list[$gpi]['date'] ?? null,
                $offer['date']
            );

            $this->list[$gpi]['offer'] = $offer['hash'];
        }
    }

    /**
     * Read all sales orders
     *
     * @return void
     * @throws QUI\Database\Exception
     */
    protected function readSalesOrders(): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/salesorders')) {
            return;
        }

        $salesOrders = QUI::getDatabase()->fetch([
            'select' => 'hash,global_process_id,date',
            'from' => SalesOrdersHandler::getTableSalesOrders()
        ]);

        foreach ($salesOrders as $salesOrder) {
            $gpi = $salesOrder['global_process_id'];

            if (empty($gpi)) {
                $gpi = $salesOrder['hash'];
            }

            if (!isset($this->list[$gpi])) {
                $this->list[$gpi] = [];
            }

            $this->list[$gpi]['date'] = $this->getEarlierDate(
                $this->list[$gpi]['date'] ?? null,
                $salesOrder['date']
            );

            $this->list[$gpi]['salesorders'] = $salesOrder['hash'];
        }
    }

    /**
     * Read all purchases
     *
     * @return void
     * @throws QUI\Database\Exception
     */
    protected function readPurchasing(): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/purchasing')) {
            return;
        }

        $purchasing = QUI::getDatabase()->fetch([
            'select' => 'hash,global_process_id,date',
            'from' => PurchasingHandler::getTablePurchasingProcesses()
        ]);

        foreach ($purchasing as $entry) {
            $gpi = $entry['global_process_id'];

            if (empty($gpi)) {
                $gpi = $entry['hash'];
            }

            if (!isset($this->list[$gpi])) {
                $this->list[$gpi] = [];
            }

            $this->list[$gpi]['date'] = $this->getEarlierDate(
                $this->list[$gpi]['date'] ?? null,
                $entry['date']
            );

            $this->list[$gpi]['purchasing'] = $entry['hash'];
        }
    }

    //endregion

    //region utils

    protected function getEarlierDate($date1, $date2)
    {
        $timestamp1 = strtotime($date1);
        $timestamp2 = strtotime($date2);

        if ($date1 === null && $date2) {
            return $date2;
        }

        if ($date1 && $date2 === null) {
            return $date1;
        }

        return ($timestamp1 < $timestamp2) ? $date1 : $date2;
    }

    //endregion
}
