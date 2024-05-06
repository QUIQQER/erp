<?php

namespace QUI\ERP;

use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\ArticleListUnique;
use QUI\ERP\Accounting\Calculations;
use QUI\ERP\Address as ErpAddress;
use QUI\ERP\User as ErpUser;
use QUI\Interfaces\Users\User;

interface ErpEntityInterface
{
    //region QDOM

    public function getAttribute(string $key): mixed;

    public function getAttributes(): array;

    public function setAttribute(string $key, mixed $value): void;

    //endregion

    /**
     * return the internal database id
     *
     * @return int
     */
    public function getId(): int;

    /**
     * Return the uuid hash of the entity
     *
     * @return string
     */
    public function getUUID(): string;

    /**
     * Return the process of the entity (global process id))
     *
     * @return string
     */
    public function getGlobalProcessId(): string;

    /**
     * Return the entity number
     * returns the number that this entity has. a number is, for example, an invoice number or booking number. this number is not the id.
     *
     * @return string
     */
    public function getPrefixedNumber(): string;

    /**
     * Get the customer of the erp entity
     *
     * @return ErpUser|null The customer of the order, or null if no customer is set
     */
    public function getCustomer(): ?ErpUser;

    /**
     * Get the currency of the erp entity
     *
     * @return Currency\Currency
     */
    public function getCurrency(): QUI\ERP\Currency\Currency;

    /**
     * Get the article list of the erp entity
     *
     * @return ArticleList|ArticleListUnique
     */
    public function getArticles(): ArticleList|ArticleListUnique;

    /**
     * Get the price calculation object of the erp entity
     *
     * @return Calculations
     */
    public function getPriceCalculation(): Calculations;

    /**
     * Get the delivery address of the erp entity
     *
     * @return Address|null
     */
    public function getDeliveryAddress(): ?ErpAddress;

    /**
     * Set a customer to the erp entity
     *
     * @param array|User $User
     */
    public function setCustomer(array|QUI\Interfaces\Users\User $User);

    /**
     * Returns the erp entity as an array
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Cancel the entity
     * (Reversal, Storno, Cancel)
     *
     * @param string $reason
     * @param User|null $PermissionUser
     * @return ?ErpEntityInterface
     */
    public function reversal(
        string $reason = '',
        QUI\Interfaces\Users\User $PermissionUser = null
    ): ?ErpEntityInterface;

    public function addCustomerFile(string $fileHash, array $options = []): void;

    public function clearCustomerFiles(): void;

    public function getCustomerFiles(): array;
}
