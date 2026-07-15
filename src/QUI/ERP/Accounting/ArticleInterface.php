<?php

/**
 * This file contains QUI\ERP\Accounting\ArticleInterface
 */

namespace QUI\ERP\Accounting;

use QUI\ERP\Currency\Currency;
use QUI\ERP\Money\Price;
use QUI\Exception;
use QUI\Interfaces\Users\User;

/**
 * Article
 * An temporary invoice article
 */
interface ArticleInterface
{
    /**
     * Article constructor.
     *
     * @param array<mixed> $attributes - article attributes
     * @throws Exception
     */
    public function __construct(array $attributes = []);

    /**
     * @return int
     */
    public function getId(): int;

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
    public function getPrice(): Price;

    /**
     * @return Price
     */
    public function getSum(): Price;

    /**
     * @return float|int|bool
     */
    public function getQuantity(): float|int|bool;

    /**
     * @return float|int
     */
    public function getVat(): float|int;

    public function getUser(): ?User;

    public function setUser(?User $User): void;

    public function getCurrency(): Currency;

    public function setCurrency(Currency $Currency): void;

    public function calc(null|Calc|\QUI\ERP\User $Instance = null): ArticleInterface;

    /**
     * @return ArticleDiscount|null
     */
    public function getDiscount(): ?ArticleDiscount;

    /**
     * @return array<mixed>
     */
    public function toArray(): array;

    /**
     * is the price displayed or not
     *
     * @return bool
     */
    public function displayPrice(): bool;
}
