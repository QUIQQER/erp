<?php

/**
 * This file contains QUI\ERP\Accounting\ArticleInterface
 */

namespace QUI\ERP\Accounting;

use QUI;

/**
 * Article
 * An temporary invoice article
 *
 * @package QUI\ERP\Accounting\Invoice
 */
interface ArticleInterface
{
    /**
     * Article constructor.
     *
     * @param array $attributes - article attributes
     * @throws \QUI\Exception
     */
    public function __construct($attributes = []);

    /**
     * @return ArticleView
     */
    public function getView(): ArticleView;

    /**
     * @return string
     */
    public function getTitle(): string;

    /**
     * @return string
     */
    public function getDescription(): string;

    /**
     * @return integer|float
     */
    public function getUnitPrice();

    /**
     * @return integer|float
     */
    public function getUnitPriceUnRounded();

    /**
     * @return integer|float
     */
    public function getSum();

    /**
     * @return integer|float
     */
    public function getQuantity();

    /**
     * @return array
     */
    public function toArray(): array;

    /**
     * is the price displayed or not
     *
     * @return bool
     */
    public function displayPrice(): bool;
}
