<?php

namespace QUI\ERP;

use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Calculations;
use QUI\ERP\Address as ErpAddress;
use QUI\ERP\User as ErpUser;

interface ErpEntityInterface
{
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
     * @return ArticleList
     */
    public function getArticles(): ArticleList;

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
     * @param array|QUI\ERP\User|QUI\Interfaces\Users\User $User
     */
    public function setCustomer($User);
}
