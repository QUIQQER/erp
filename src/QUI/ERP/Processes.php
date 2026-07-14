<?php

/**
 * This file contains QUI\ERP\Processes
 */

namespace QUI\ERP;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use QUI;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Offers\Handler as OfferHandler;
use QUI\ERP\Accounting\Payments\Transactions\Factory as TransactionFactory;
use QUI\ERP\Database\Queries;
use QUI\ERP\Order\Handler as OrderHandler;
use QUI\ERP\SalesOrders\Handler as SalesOrdersHandler;
use QUI\Exception;

use function class_exists;
use function strtotime;

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
 * - quiqqer/payment-transactions
 * - quiqqer/delivery-notes
 */
class Processes
{
    /**
     * @var array<string, mixed>
     */
    protected array $list = [];

    /**
     * @param string $entityHash
     * @param string|false $entityPlugin
     * @throws Exception
     */
    public function getEntity($entityHash, $entityPlugin = false): ErpEntityInterface
    {
        if ($entityPlugin === false || $entityPlugin === 'quiqqer/booking') {
            try {
                // @todo quiqqer/booking
                // @phpstan-ignore-next-line
            } catch (\Exception) {
            }
        }

        if (
            ($entityPlugin === false || $entityPlugin === 'quiqqer/contracts')
            && class_exists('QUI\ERP\Accounting\Contracts\Handler')
        ) {
            try {
                return QUI\ERP\Accounting\Contracts\Handler::getInstance()->get($entityHash);
            } catch (\Exception) {
            }
        }

        if (
            ($entityPlugin === false || $entityPlugin === 'quiqqer/dunning')
            && class_exists('QUI\ERP\Accounting\Dunning\Handler')
        ) {
            try {
                return QUI\ERP\Accounting\Dunning\Handler::getInstance()->getDunningProcess($entityHash);
            } catch (\Exception) {
            }
        }

        if ($entityPlugin === false || $entityPlugin === 'quiqqer/delivery-notes') {
            try {
                // @todo quiqqer/delivery-notes
                // @phpstan-ignore-next-line
            } catch (\Exception) {
            }
        }

        if (
            ($entityPlugin === false || $entityPlugin === 'quiqqer/invoice')
            && class_exists('QUI\ERP\Accounting\Invoice\Handler')
        ) {
            try {
                return QUI\ERP\Accounting\Invoice\Handler::getInstance()->getInvoiceByHash($entityHash);
            } catch (\Exception) {
            }
        }

        if (
            ($entityPlugin === false || $entityPlugin === 'quiqqer/offers')
            && class_exists('QUI\ERP\Accounting\Offers\Handler')
        ) {
            try {
                return QUI\ERP\Accounting\Offers\Handler::getInstance()->getOfferByHash($entityHash);
            } catch (\Exception) {
            }
        }

        if (
            ($entityPlugin === false || $entityPlugin === 'quiqqer/order')
            && class_exists('QUI\ERP\Order\Handler')
        ) {
            try {
                return QUI\ERP\Order\Handler::getInstance()->getOrderByHash($entityHash);
            } catch (\Exception) {
            }
        }

        if (
            ($entityPlugin === false || $entityPlugin === 'quiqqer/purchasing')
            && class_exists('QUI\ERP\Purchasing\Processes\Handler')
        ) {
            try {
                return QUI\ERP\Purchasing\Processes\Handler::getPurchasingProcess($entityHash);
            } catch (\Exception) {
            }

            try {
                return QUI\ERP\Purchasing\Processes\Handler::getPurchasingProcessDraft($entityHash);
            } catch (\Exception) {
            }
        }

        if (
            ($entityPlugin === false || $entityPlugin === 'quiqqer/salesorders')
            && class_exists('QUI\ERP\SalesOrders\Handler')
        ) {
            try {
                return QUI\ERP\SalesOrders\Handler::getSalesOrderByHash($entityHash);
            } catch (\Exception) {
            }
        }

        throw new Exception([
            'quiqqer/erp',
            'exception.entity.not.found',
            ['hash' => $entityHash]
        ], 404);
    }

    /**
     * @return array<mixed>
     */
    public function getList(): array
    {
        try {
            $this->readBooking();
        } catch (DbalException $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readInvoices();
        } catch (DbalException $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readOffers();
        } catch (DbalException $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readOrders();
        } catch (DbalException $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readPurchasing();
        } catch (DbalException $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readSalesOrders();
        } catch (DbalException $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readTransactions();
        } catch (DbalException $exception) {
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
            'quiqqer/booking',
            'quiqqer/contracts',
            'quiqqer/delivery-notes',
            'quiqqer/invoice',
            'quiqqer/offers',
            'quiqqer/order',
            'quiqqer/payments',
            'quiqqer/payment-transactions',
            'quiqqer/purchasing',
            'quiqqer/salesorders'
        ];
    }

    //region read databases

    /**
     * Rads all invoices
     *
     * @return void
     * @throws DbalException
     */
    protected function readInvoices(): void
    {
        if (
            !QUI::getPackageManager()->isInstalled('quiqqer/invoice')
            || !class_exists(InvoiceHandler::class)
        ) {
            return;
        }

        $invoices = $this->fetchAllAssociative(
            InvoiceHandler::getInstance()->invoiceTable(),
            ['hash', 'global_process_id', 'date']
        );

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
     * @throws DbalException
     */
    protected function readOrders(): void
    {
        if (
            !QUI::getPackageManager()->isInstalled('quiqqer/order')
            || !class_exists(OrderHandler::class)
        ) {
            return;
        }

        $orders = $this->fetchAllAssociative(
            OrderHandler::getInstance()->table(),
            ['hash', 'global_process_id', 'c_date']
        );

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
     * @throws DbalException
     */
    protected function readOffers(): void
    {
        if (
            !QUI::getPackageManager()->isInstalled('quiqqer/offers')
            || !class_exists(OfferHandler::class)
        ) {
            return;
        }

        $offers = $this->fetchAllAssociative(
            OfferHandler::getInstance()->offersTable(),
            ['hash', 'global_process_id', 'date']
        );

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
     * @throws DbalException
     */
    protected function readSalesOrders(): void
    {
        if (
            !QUI::getPackageManager()->isInstalled('quiqqer/salesorders')
            || !class_exists(SalesOrdersHandler::class)
        ) {
            return;
        }

        $salesOrders = $this->fetchAllAssociative(
            SalesOrdersHandler::getTableSalesOrders(),
            ['hash', 'global_process_id', 'date']
        );

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
     * Read all sales orders
     *
     * @return void
     * @throws DbalException
     */
    protected function readTransactions(): void
    {
        if (
            !QUI::getPackageManager()->isInstalled('quiqqer/payment-transactions')
            || !class_exists(TransactionFactory::class)
        ) {
            return;
        }

        $transactions = $this->fetchAllAssociative(
            TransactionFactory::table(),
            ['hash', 'global_process_id', 'date']
        );

        foreach ($transactions as $transaction) {
            $gpi = $transaction['global_process_id'];

            if (empty($gpi)) {
                $gpi = $transaction['hash'];
            }

            if (!isset($this->list[$gpi])) {
                $this->list[$gpi] = [];
            }

            $this->list[$gpi]['date'] = $this->getEarlierDate(
                $this->list[$gpi]['date'] ?? null,
                $transaction['date']
            );

            $this->list[$gpi]['transactions'] = $transaction['hash'];
        }
    }

    /**
     * Read all purchases
     *
     * @return void
     * @throws DbalException
     */
    protected function readPurchasing(): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/purchasing')) {
            return;
        }

        if (!class_exists('QUI\ERP\Purchasing\Processes\Handler')) {
            return;
        }

        $purchasing = $this->fetchAllAssociative(
            QUI\ERP\Purchasing\Processes\Handler::getTablePurchasingProcesses(),
            ['hash', 'global_process_id', 'date']
        );

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

    /**
     * Read all purchases
     *
     * @return void
     * @throws DbalException
     */
    protected function readBooking(): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/booking')) {
            return;
        }

        if (!class_exists('QUI\ERP\Booking\Table')) {
            return;
        }

        $bookings = $this->fetchAllAssociative(
            QUI\ERP\Booking\Table::BOOKINGS->tableName(),
            ['uuid', 'globalProcessId', 'createDate']
        );

        foreach ($bookings as $booking) {
            $gpi = $booking['globalProcessId'];

            if (empty($gpi)) {
                $gpi = $booking['uuid'];
            }

            if (!isset($this->list[$gpi])) {
                $this->list[$gpi] = [];
            }

            $this->list[$gpi]['date'] = $this->getEarlierDate(
                $this->list[$gpi]['date'] ?? null,
                $booking['createDate']
            );

            $this->list[$gpi]['booking'] = $booking['uuid'];
        }
    }

    //endregion

    //region utils

    /**
     * @param array<string> $columns
     * @return array<array<string, mixed>>
     * @throws DbalException
     */
    private function fetchAllAssociative(string $table, array $columns): array
    {
        return Queries::fetchAllAssociative($this->getDatabaseConnection(), $table, $columns);
    }

    protected function getDatabaseConnection(): Connection
    {
        return QUI::getDataBaseConnection();
    }

    /**
     * @param string|null $date1
     * @param string|null $date2
     * @return string|null
     */
    protected function getEarlierDate(?string $date1, ?string $date2): ?string
    {
        if ($date1 === null) {
            return $date2;
        }

        if ($date2 === null) {
            return $date1;
        }

        $timestamp1 = strtotime($date1);
        $timestamp2 = strtotime($date2);

        return ($timestamp1 < $timestamp2) ? $date1 : $date2;
    }

    //endregion
}
