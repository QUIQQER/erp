<?php

/**
 * This file contains QUI\ERP\Money\Price
 */

namespace QUI\ERP\Money;

use JsonException;
use NumberFormatter;
use QUI;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Discount\Discount;
use QUI\Interfaces\Users\User;

use function floatval;
use function is_array;
use function is_float;
use function is_int;
use function mb_strpos;
use function mb_substr;
use function preg_replace;
use function round;
use function str_replace;
use function trim;

/**
 * Class Price
 */
class Price
{
    /**
     * Netto Price
     * @var float|int
     */
    protected float | int $price;

    /**
     * Price currency
     * @var QUI\ERP\Currency\Currency
     */
    protected QUI\ERP\Currency\Currency $Currency;

    /**
     * Flag for Price from
     * @var bool
     */
    protected bool $isMinimalPrice = false;

    /**
     * @var array<mixed>
     */
    protected array $discounts;

    /**
     * User
     * @var ?QUI\Interfaces\Users\User
     */
    protected ?QUI\Interfaces\Users\User $User = null;

    /**
     * Price constructor.
     *
     * @param float|int|null $price
     * @param Currency $Currency
     * @param User|null $User - optional, if no user, session user are used
     */
    public function __construct(
        float | int | null $price,
        QUI\ERP\Currency\Currency $Currency,
        null | QUI\Interfaces\Users\User $User = null
    ) {
        if (!$price) {
            $price = 0;
        }

        $this->price = $price;
        $this->Currency = $Currency;

        $this->User = $User;
        $this->discounts = [];

        if (!QUI::getUsers()->isUser($User)) {
            $this->User = QUI::getUserBySession();
        }
    }

    /**
     * Return the price as array notation
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            'price' => $this->value(),
            'currency' => $this->getCurrency()->getCode(),
            'display' => $this->getDisplayPrice(),
            'isMinimalPrice' => $this->isMinimalPrice()
        ];
    }

    /**
     * Return the real price
     *
     * @return float|int
     */
    public function getPrice(): float | int
    {
        return round($this->price, QUI\ERP\Defaults::getPrecision());
    }

    /**
     * Alias for getPrice
     *
     * @return float|int
     */
    public function value(): float | int
    {
        return $this->getPrice();
    }

    /**
     * Alias for getPrice
     *
     * @return float|int
     */
    public function getValue(): float | int
    {
        return $this->getPrice();
    }

    /**
     * Return the price for the view / displaying
     *
     * @return string
     */
    public function getDisplayPrice(): string
    {
        return $this->Currency->format($this->getPrice());
    }

    /**
     * Add a discount to the price
     *
     * @param QUI\ERP\Discount\Discount $Discount
     * @throws QUI\Exception
     */
    public function addDiscount(Discount $Discount): void
    {
        /* @var $Disc Discount */
        foreach ($this->discounts as $Disc) {
            // der gleiche discount kann nur einmal enthalten sein
            if ($Disc->getId() == $Discount->getId()) {
                return;
            }

            if ($Disc->canCombinedWith($Discount) === false) {
                throw new QUI\Exception([
                    'quiqqer/products',
                    'exception.discount.not.combinable',
                    [
                        'id1' => $Disc->getId(),
                        'id2' => $Discount->getId()
                    ]
                ]);
            }
        }

        $this->discounts[] = $Discount;
    }

    /**
     * Return the assigned discounts
     *
     * @return array<mixed> [Discount, Discount, Discount]
     */
    public function getDiscounts(): array
    {
        return $this->discounts;
    }

    /**
     * Return the currency from the price
     *
     * @return QUI\ERP\Currency\Currency
     */
    public function getCurrency(): QUI\ERP\Currency\Currency
    {
        return $this->Currency;
    }

    /**
     * calculation
     */

    /**
     * Validates a price value
     *
     * @deprecated Use Price::parsePrice() instead.
     *
     * @param mixed $value
     * @param QUI\Locale|null $Locale - based locale, in which the price is
     * @return float|int|null
     */
    public static function validatePrice(
        mixed $value,
        null | QUI\Locale $Locale = null
    ): float | int | null {
        return self::parsePrice($value, $Locale);
    }

    /**
     * Parses and normalizes a price value
     *
     * @param mixed $value
     * @param QUI\Locale|null $Locale - based locale, in which the price is
     * @return float|int|null
     */
    public static function parsePrice(
        mixed $value,
        null | QUI\Locale $Locale = null
    ): float | int | null {
        if (is_float($value) || is_int($value)) {
            return round($value, QUI\ERP\Defaults::getPrecision());
        }

        if ($value instanceof Price) {
            $value = $value->getPrice();
        }

        $value = (string)$value;
        $isNegative = str_starts_with($value, '-');

        // value cleanup
        $value = preg_replace('#[^\d,.]#i', '', $value) ?? '';

        if (trim($value) === '') {
            return null;
        }

        if ($Locale === null) {
            $Locale = QUI::getSystemLocale();
        }

        $negativeTurn = 1;

        if ($isNegative) {
            $negativeTurn = -1;
        }

        $decimalSeparator = $Locale->getDecimalSeparator();
        $thousandSeparator = $Locale->getGroupingSeparator();

        if (is_array($decimalSeparator)) {
            $decimalSeparator = isset($decimalSeparator[0])
                ? (string)$decimalSeparator[0]
                : '';
        }

        $decimal = mb_strpos($value, $decimalSeparator);
        $thousands = mb_strpos($value, $thousandSeparator);

        if ($thousands === false && $decimal === false) {
            return round(floatval($value), 4) * $negativeTurn;
        }

        if ($thousands !== false && $decimal === false) {
            if (mb_substr($value, -4, 1) === $thousandSeparator) {
                $value = str_replace($thousandSeparator, '', $value);
            }
        }

        if ($thousands === false && $decimal !== false) {
            $value = str_replace($decimalSeparator, '.', $value);
        }

        if ($thousands !== false && $decimal !== false) {
            $value = str_replace($thousandSeparator, '', $value);
            $value = str_replace($decimalSeparator, '.', $value);
        }

        $value = floatval($value);
        $value = round($value, QUI\ERP\Defaults::getPrecision());

        return $value * $negativeTurn;
    }

    /**
     * Validate number input or a JSON envelope containing a value and its display locale.
     * Unlike parsePrice(), malformed input is rejected rather than cleaned up.
     */
    public static function parsePriceInput(mixed $input): ?float
    {
        $locale = QUI::getSystemLocale()->getCurrent();

        if (is_string($input) && str_starts_with(ltrim($input), '{')) {
            try {
                $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new QUI\ERP\Exception('Invalid price input', 400);
            }

            if (
                !is_array($data) || !array_key_exists('value', $data) ||
                !isset($data['locale']) || !is_string($data['locale']) ||
                !preg_match('/^[a-zA-Z]{2,3}(?:[-_][a-zA-Z0-9]{2,8})*$/D', $data['locale'])
            ) {
                throw new QUI\ERP\Exception('Invalid price input format', 400);
            }

            $input = $data['value'];
            $locale = $data['locale'];
        }

        if (is_int($input) || is_float($input)) {
            return round(self::validateNumericPrice($input), QUI\ERP\Defaults::getPrecision());
        }

        if (!is_string($input)) {
            throw new QUI\ERP\Exception('Invalid price input type', 400);
        }

        $input = trim($input);

        if ($input === '' || $input === '-') {
            return null;
        }

        // Discount inputs historically also accept a currency symbol at either edge.
        $input = trim(preg_replace('/^\p{Sc}\s*|\s*\p{Sc}$/u', '', $input, 1) ?? $input);

        $Formatter = new NumberFormatter(str_replace('_', '-', $locale), NumberFormatter::DECIMAL);
        $Formatter->setAttribute(NumberFormatter::LENIENT_PARSE, 0);
        $position = 0;
        $value = $Formatter->parse($input, NumberFormatter::TYPE_DOUBLE, $position);

        if ($value !== false && $position === self::getNumberFormatterInputLength($input)) {
            return round(self::validateNumericPrice($value), QUI\ERP\Defaults::getPrecision());
        }

        // Keep decimal-point input usable in comma locales, but never partially parse a value.
        // Locale-valid grouping (e.g. German "4.622") has already been handled above.
        if (preg_match('/^[+-]?[0-9]+(?:\.[0-9]+)?$/D', $input)) {
            return round(self::validateNumericPrice($input), QUI\ERP\Defaults::getPrecision());
        }

        throw new QUI\ERP\Exception('Invalid localized price input', 400);
    }

    /**
     * Match the offset unit used by the installed intl extension (PHP bug GH-23094).
     */
    private static function getNumberFormatterInputLength(string $input): int
    {
        static $usesByteOffsets = null;

        if ($usesByteOffsets === null) {
            $Probe = new NumberFormatter('en-US', NumberFormatter::DECIMAL);
            $position = 0;
            $Probe->parse('١', NumberFormatter::TYPE_DOUBLE, $position);
            $usesByteOffsets = $position === strlen('١');
        }

        // Older PHP builds return UTF-16 code units, including surrogate pairs.
        return $usesByteOffsets
            ? strlen($input)
            : intdiv(strlen(mb_convert_encoding($input, 'UTF-16LE', 'UTF-8')), 2);
    }

    /**
     * Validate canonical amounts received in article JSON, including legacy numeric strings.
     */
    public static function validateNumericPrice(mixed $value): float
    {
        if (
            !(is_int($value) || is_float($value) ||
                (is_string($value) && preg_match('/^[+-]?[0-9]+(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?$/D', $value)))
        ) {
            throw new QUI\ERP\Exception('Invalid numeric price', 400);
        }

        $value = (float)$value;

        // JSON numbers are consumed by JavaScript too; reject amounts outside its safe integer range.
        if (!is_finite($value) || abs($value) > 9007199254740991) {
            throw new QUI\ERP\Exception('Price outside the supported numeric range', 400);
        }

        return $value;
    }

    /**
     * Return if the price is minimal price and higher prices exists
     *
     * @return bool
     */
    public function isMinimalPrice(): bool
    {
        return $this->isMinimalPrice;
    }

    /**
     * enables the minimal price
     * -> price from
     * -> ab
     */
    public function enableMinimalPrice(): void
    {
        $this->isMinimalPrice = true;
    }

    /**
     * enables the minimal price
     * -> price from
     * -> ab
     */
    public function disableMinimalPrice(): void
    {
        $this->isMinimalPrice = false;
    }
}
