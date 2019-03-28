<?php

/**
 * This file contains QUI\ERP\Accounting\ArticleDiscount
 */

namespace QUI\ERP\Accounting;

use QUI;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Currency\Handler as CurrencyHandler;

/**
 * Class ArticleDiscount
 *
 * @package QUI\ERP\Accounting
 */
class ArticleDiscount
{
    /**
     * Calculation type, % or complement (currency value)
     *
     * @var int
     */
    protected $type = Calc::CALCULATION_COMPLEMENT;

    /**
     * Discount value
     *
     * @var int
     */
    protected $value = 0;

    /**
     * Return the currency
     *
     * @var null|Currency
     */
    protected $Currency = null;

    /**
     * ArticleDiscount constructor.
     *
     * @param int $discount
     * @param int $type
     */
    public function __construct($discount, $type)
    {
        switch ($type) {
            case Calc::CALCULATION_PERCENTAGE:
            case Calc::CALCULATION_COMPLEMENT:
                break;

            default:
                $type = Calc::CALCULATION_COMPLEMENT;
        }

        $this->type  = $type;
        $this->value = $discount;
    }

    /**
     * Unserialize a discount string
     *
     * The string can be in the following format:
     * - 10%
     * - 10€
     * - 10
     * - {"value": 10, "type": 1}
     *
     * @param string $string
     * @return null|ArticleDiscount
     */
    public static function unserialize($string)
    {
        $data = [];

        if (\is_numeric($string)) {
            // number, float, int -> 5.99
            $data['value'] = QUI\ERP\Money\Price::validatePrice($string);
            $data['type']  = Calc::CALCULATION_COMPLEMENT;
        } elseif (\strpos($string, '{') !== false || \strpos($string, '[') !== false) {
            // json string
            $data = \json_decode($string, true);

            if (!\is_array($data)) {
                return null;
            }
        } else {
            // is normal string 5% or 5.99 €
            if (\strpos($string, '%') !== false) {
                $data['value'] = (int)\str_replace('%', '', $string);
                $data['type']  = Calc::CALCULATION_PERCENTAGE;
            } else {
                $data['value'] = QUI\ERP\Money\Price::validatePrice($string);
                $data['type']  = Calc::CALCULATION_COMPLEMENT;
            }
        }

        if (!isset($data['value']) || !isset($data['type'])) {
            return null;
        }

        $Discount = new self($data['value'], $data['type']);

        // discount
        if (isset($data['currency']) && isset($data['currency']['code'])) {
            try {
                $Discount->setCurrency(
                    CurrencyHandler::getCurrency($data['currency']['code'])
                );
            } catch (QUI\Exception $Exception) {
            }
        }

        return $Discount;
    }

    /**
     * Return the discount type, % or complement (currency value)
     *
     * Calc::CALCULATION_COMPLEMENT => 2 = €
     * Calc::CALCULATION_PERCENTAGE => 1 = %
     *
     * @return int
     */
    public function getDiscountType()
    {
        return $this->type;
    }

    /**
     * Alias for getDiscountType()
     * For better understanding in the calculation API
     *
     * @return int
     */
    public function getCalculation()
    {
        return $this->getDiscountType();
    }

    /**
     * Return the discount value
     *
     * @return int
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Return the Discount Currency
     *
     * @return Currency
     */
    public function getCurrency()
    {
        if ($this->Currency === null) {
            return QUI\ERP\Defaults::getCurrency();
        }

        return $this->Currency;
    }

    /**
     * Set the currency
     *
     * @param Currency $Currency
     */
    public function setCurrency(Currency $Currency)
    {
        $this->Currency = $Currency;
    }

    /**
     * Parse the discount to an array
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'value'    => $this->value,
            'type'     => $this->type,
            'currency' => $this->getCurrency()->toArray()
        ];
    }

    /**
     * Parse the discount to a json representation
     *
     * @return string
     */
    public function toJSON()
    {
        return \json_encode($this->toArray());
    }
}
