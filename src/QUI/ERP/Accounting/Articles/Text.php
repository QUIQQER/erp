<?php

/**
 * This file contains QUI\ERP\Accounting\Articles\Text
 */

namespace QUI\ERP\Accounting\Articles;

use QUI;
use QUI\ERP\Money\Price;

use function array_merge;
use function get_class;

/**
 * Article Text
 *
 * - An article containing only text
 * - Can be used as an information item on an invoice
 * - Does not have any values
 *
 * - Ein Artikel welcher nur Text beinhaltet
 * - Kann als Informationsposition auf einer Rechnung verwendet werden
 * - Besitzt keine Werte
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Text extends QUI\ERP\Accounting\Article
{
    protected $displayPrice = false;

    /**
     * @inheritdoc
     * @return QUI\ERP\Money\Price
     */
    public function getUnitPrice(): Price
    {
        return new Price(0, QUI\ERP\Defaults::getCurrency());
    }

    /**
     * @inheritdoc
     * @return QUI\ERP\Money\Price
     */
    public function getSum(): Price
    {
        return new Price(0, QUI\ERP\Defaults::getCurrency());
    }

    /**
     * @inheritdoc
     * @return bool
     */
    public function getQuantity()
    {
        return 1;
    }

    /**
     * @return bool
     */
    public function displayPrice(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'class'        => get_class($this),
            'control'      => 'package/quiqqer/erp/bin/backend/controls/articles/Text',
            'displayPrice' => $this->displayPrice()
        ]);
    }
}
