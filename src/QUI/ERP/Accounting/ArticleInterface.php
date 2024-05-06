<?php

/**
 * This file contains QUI\ERP\Accounting\ArticleInterface
 */

namespace QUI\ERP\Accounting;

use QUI\ERP\Money\Price;
use QUI\Exception;

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
     * @throws Exception
     */
    public function __construct(array $attributes = []);

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
     * @return Price
     */
    public function getUnitPrice(): Price;

    /**
     * @return Price
     */
    public function getUnitPriceUnRounded(): Price;

    /**
     * @return Price
     */
    public function getSum(): Price;

    /**
     * @return float|int|bool
     */
    public function getQuantity(): float|int|bool;

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
