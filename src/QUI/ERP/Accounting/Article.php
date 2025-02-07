<?php

/**
 * This file contains QUI\ERP\Accounting\Article
 */

namespace QUI\ERP\Accounting;

use QUI;
use QUI\ERP\Money\Price;
use QUI\ERP\Tax\Utils as TaxUtils;
use QUI\Exception;

use function floatval;
use function get_called_class;
use function is_array;
use function method_exists;

/**
 * Class Article
 *
 * @package QUI\ERP\Accounting
 */
class Article implements ArticleInterface
{
    /**
     * @var array
     */
    protected array $attributes = [
        'id' => '',
        'control' => '',
        'class' => ''
    ];

    /**
     * Custom fields are data which field out the customer
     * This data is not used for presentation or calculation
     *
     * in a custom field are only allowed string and numeric values
     *
     * @var array
     */
    protected array $customFields = [];

    /**
     * Custom data for plugins and modules
     *
     * @var array
     */
    protected array $customData = [];

    /**
     * Should the price displayed?
     * default = yes
     */
    protected bool $displayPrice = true;

    /**
     * @var bool
     */
    protected bool $calculated = false;

    /**
     * @var float|int
     */
    protected float|int $price;

    /**
     * @var float|int
     */
    protected float|int $basisPrice;

    /**
     * @var float|int|null
     */
    protected float|int|null $nettoPriceNotRounded = null;

    /**
     * @var float|int
     */
    protected float|int $sum;

    /**
     * The calculated netto sum with quantity and discount
     * @var float|int
     */
    protected float|int $nettoSum = 0;

    /**
     * Sum from the article, without discount and with quantity
     *
     * @var float|int
     */
    protected float|int $nettoSubSum = 0;

    /**
     * The article netto price, without discount, without quantity
     * comes from article
     *
     * @var float|int
     */
    protected float|int $nettoPrice = 0;

    /**
     * The article netto price, without discount, without quantity
     * comes from calc
     *
     * @var float|int
     */
    protected float|int $nettoBasisPrice = 0;

    /**
     * @var array
     */
    protected mixed $vatArray = [];

    /**
     * @var bool
     */
    protected mixed $isEuVat;

    /**
     * @var bool
     */
    protected mixed $isNetto;

    protected float $position = 0;

    /**
     * @var ArticleDiscount|null
     */
    protected ?ArticleDiscount $Discount = null;

    /**
     * @var null|QUI\Interfaces\Users\User
     */
    protected ?QUI\Interfaces\Users\User $User = null;

    /**
     * @var null|QUI\ERP\Currency\Currency
     */
    protected ?QUI\ERP\Currency\Currency $Currency = null;

    /**
     * Article constructor.
     *
     * @param array $attributes - (id, articleNo, title, description, unitPrice, nettoPriceNotRounded, quantity, discount, customData)
     * @throws Exception
     */
    public function __construct(array $attributes = [])
    {
        $defaults = [
            'id',
            'uuid',
            'productSetParentUuid',
            'articleNo',
            'gtin',
            'title',
            'description',
            'unitPrice',
            'nettoPriceNotRounded', // optional
            'control',
            'quantity',
            'quantityUnit'
        ];

        foreach ($defaults as $key) {
            if (isset($attributes[$key])) {
                $this->attributes[$key] = $attributes[$key];
            }
        }

        if (isset($attributes['vat']) && $attributes['vat'] !== '') {
            $this->attributes['vat'] = $attributes['vat'];
        } else {
            $this->attributes['vat'] = '';
        }
        if (isset($attributes['position'])) {
            $this->position = (float)$attributes['position'];
        }

        if (isset($attributes['discount'])) {
            $this->Discount = ArticleDiscount::unserialize($attributes['discount']);
            $this->Discount?->setArticle($this);
        }


        if (isset($attributes['calculated'])) {
            $calc = $attributes['calculated'];

            if (isset($calc['nettoPriceNotRounded'])) {
                $this->nettoPriceNotRounded = $calc['nettoPriceNotRounded'];
            }

            $this->price = $calc['price'];
            $this->basisPrice = $calc['basisPrice'];
            $this->sum = $calc['sum'];

            $this->nettoPrice = $calc['nettoPrice'];
            $this->nettoBasisPrice = $calc['nettoBasisPrice'];
            $this->nettoSum = $calc['nettoSum'];

            if (isset($calc['nettoSubSum'])) {
                $this->nettoSubSum = $calc['nettoSubSum'];
            }

            $this->vatArray = $calc['vatArray'];
            $this->isEuVat = $calc['isEuVat'];
            $this->isNetto = $calc['isNetto'];

            $this->calculated = true;
        }

        if (isset($attributes['customFields']) && is_array($attributes['customFields'])) {
            $this->customFields = $attributes['customFields'];
        }

        if (isset($attributes['customData']) && is_array($attributes['customData'])) {
            $this->customData = $attributes['customData'];
        }

        if (isset($attributes['displayPrice'])) {
            $this->displayPrice = (bool)$attributes['displayPrice'];
        }

        if (isset($attributes['currency'])) {
            try {
                $this->Currency = QUI\ERP\Currency\Handler::getCurrency($attributes['currency']);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        if (!$this->Currency) {
            $this->Currency = QUI\ERP\Currency\Handler::getDefaultCurrency();
        }
    }

    /**
     * Return the article view
     *
     * @return ArticleView
     */
    public function getView(): ArticleView
    {
        return new ArticleView($this);
    }

    /**
     * Return the Article ID
     *
     * @return int
     */
    public function getId(): int
    {
        if (isset($this->attributes['id'])) {
            return (int)$this->attributes['id'];
        }

        return 0;
    }

    /**
     * Return the Article UUID
     *
     * @return string|null
     */
    public function getUuid(): ?string
    {
        return !empty($this->attributes['uuid']) ? $this->attributes['uuid'] : null;
    }

    /**
     * Return the UUID of a parent article, if this article is part of a product set.
     *
     * @return string|null
     */
    public function getProductSetParentUuid(): ?string
    {
        return !empty($this->attributes['productSetParentUuid']) ? $this->attributes['productSetParentUuid'] : null;
    }

    /**
     * Return the Article Number
     *
     * @return int|string
     */
    public function getArticleNo(): int|string
    {
        if (isset($this->attributes['articleNo'])) {
            return $this->attributes['articleNo'];
        }

        return '';
    }

    /**
     * Return the GTIN Number, if the article has one
     *
     * @return string
     */
    public function getGTIN(): string
    {
        if (isset($this->attributes['gtin'])) {
            return $this->attributes['gtin'];
        }

        return '';
    }

    /**
     * Returns the article title
     *
     * @return string
     */
    public function getTitle(): string
    {
        if (isset($this->attributes['title'])) {
            return $this->attributes['title'];
        }

        return '';
    }

    /**
     * Return the article image
     *
     * @return null|QUI\Projects\Media\Image
     */
    public function getImage(): ?QUI\Projects\Media\Image
    {
        if (isset($this->attributes['image'])) {
            try {
                return QUI\Projects\Media\Utils::getImageByUrl(
                    $this->attributes['image']
                );
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        $Product = null;

        try {
            $Product = QUI\ERP\Products\Handler\Products::getProductByProductNo(
                $this->getArticleNo()
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        if (!empty($Product) && method_exists($Product, 'getImage')) {
            try {
                return $Product->getImage();
            } catch (QUI\Exception) {
            }
        }

        try {
            $Project = QUI::getRewrite()->getProject();
            $PlaceholderImage = $Project->getMedia()->getPlaceholderImage();

            if ($PlaceholderImage instanceof QUI\Projects\Media\Image) {
                return $PlaceholderImage;
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return null;
    }

    /**
     * Returns the article description
     *
     * @return string
     */
    public function getDescription(): string
    {
        if (isset($this->attributes['description'])) {
            return $this->attributes['description'];
        }

        return '';
    }

    /**
     * Returns the article unit price
     *
     * @return QUI\ERP\Money\Price
     */
    public function getUnitPrice(): Price
    {
        $unitPrice = 0;

        if (isset($this->attributes['unitPrice'])) {
            $unitPrice = $this->attributes['unitPrice'];
        }

        return new Price($unitPrice, $this->Currency);
    }

    /**
     * Returns the article unit price
     *
     * @return QUI\ERP\Money\Price
     */
    public function getUnitPriceUnRounded(): Price
    {
        if (isset($this->attributes['nettoPriceNotRounded'])) {
            return new Price($this->attributes['nettoPriceNotRounded'], $this->Currency);
        }

        if ($this->nettoPriceNotRounded !== null) {
            return new Price($this->nettoPriceNotRounded, $this->Currency);
        }

        if (isset($this->attributes['unitPrice'])) {
            $this->nettoPriceNotRounded = $this->attributes['unitPrice'];
        }

        return new Price($this->nettoPriceNotRounded, $this->Currency);
    }

    /**
     * Returns the article total sum
     *
     * @return QUI\ERP\Money\Price
     */
    public function getSum(): Price
    {
        $this->calc();

        return new Price($this->sum, $this->Currency);
    }

    /**
     * Return the VAT for the article
     *
     * @return float|int
     */
    public function getVat(): float|int
    {
        if (isset($this->attributes['vat']) && $this->attributes['vat'] !== '') {
            return (float)$this->attributes['vat'];
        }

        // check if product exists and has a vat
        if (!empty($this->attributes['id'])) {
            try {
                $Area = null;

                if ($this->getUser()) {
                    $Area = QUI\ERP\Areas\Utils::getAreaByCountry($this->getUser()->getCountry());
                }

                if (!$Area) {
                    $Area = QUI\ERP\Defaults::getArea();
                }

                $Product = QUI\ERP\Products\Handler\Products::getProduct((int)$this->attributes['id']);
                $Vat = $Product->getField(QUI\ERP\Products\Handler\Fields::FIELD_VAT);
                $TaxType = new QUI\ERP\Tax\TaxType($Vat->getValue());
                $TaxEntry = TaxUtils::getTaxEntry($TaxType, $Area);

                return $TaxEntry->getValue();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        try {
            if ($this->getUser()) {
                return QUI\ERP\Tax\Utils::getTaxByUser($this->getUser())->getValue();
            }

            // return default vat
            $Area = QUI\ERP\Defaults::getArea();
            $TaxType = QUI\ERP\Tax\Utils::getTaxTypeByArea($Area);
            $TaxEntry = QUI\ERP\Tax\Utils::getTaxEntry($TaxType, $Area);

            return $TaxEntry->getValue();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addCritical($Exception->getMessage());
            QUI\System\Log::writeException($Exception);

            return 0;
        }
    }

    /**
     * @return null|QUI\Interfaces\Users\User
     */
    public function getUser(): ?QUI\Interfaces\Users\User
    {
        return $this->User;
    }

    /**
     * Set the user to the product, this user will be used for the calculation
     *
     * @param QUI\Interfaces\Users\User $User
     */
    public function setUser(QUI\Interfaces\Users\User $User): void
    {
        $this->calculated = false;
        $this->User = $User;
    }

    /**
     * Return the currency of the article
     *
     * @return QUI\ERP\Currency\Currency|null
     */
    public function getCurrency(): ?QUI\ERP\Currency\Currency
    {
        return $this->Currency;
    }

    /**
     * Set the currency for the article
     *
     * @param QUI\ERP\Currency\Currency $Currency
     */
    public function setCurrency(QUI\ERP\Currency\Currency $Currency): void
    {
        $this->Currency = $Currency;
    }

    /**
     * Returns the article quantity
     *
     * @return float|int|bool
     */
    public function getQuantity(): float|int|bool
    {
        if (isset($this->attributes['quantity'])) {
            return $this->attributes['quantity'];
        }

        return 1;
    }

    /**
     * Returns the article quantity
     *
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getQuantityUnit(QUI\Locale $Locale = null): string
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        try {
            if (empty($this->attributes['quantityUnit']['id'])) {
                return '';
            }

            // @todo cache unit field entries
            $current = $Locale->getCurrent();
            $unitId = $this->attributes['quantityUnit']['id'];
            $UnitField = QUI\ERP\Products\Handler\Fields::getField(QUI\ERP\Products\Handler\Fields::FIELD_UNIT);
            $options = $UnitField->getOptions();

            if (isset($options['entries'][$unitId])) {
                $titles = $options['entries'][$unitId]['title'];

                if (!empty($titles[$current])) {
                    return $titles[$current];
                }
            }
        } catch (QUI\Exception) {
        }

        return $this->attributes['quantityUnit']['title'];
    }

    /**
     * Return the price from the article
     *
     * @return Price
     */
    public function getPrice(): Price
    {
        $this->calc();

        return new Price($this->sum, $this->Currency);
    }

    /**
     * @return bool
     */
    public function displayPrice(): bool
    {
        return $this->displayPrice;
    }

    //region Discounts

    /**
     * Set a discount to the article
     *
     * @param float|int $discount
     * @param int $discountType - default = complement
     *
     * @todo überdenken, ganzer artikel ist eigentlich nicht änderbar
     */
    public function setDiscount(float|int $discount, int $discountType = Calc::CALCULATION_COMPLEMENT): void
    {
        switch ($discountType) {
            case Calc::CALCULATION_PERCENTAGE:
            case Calc::CALCULATION_COMPLEMENT:
                break;

            default:
                $discountType = Calc::CALCULATION_COMPLEMENT;
        }

        $this->Discount = new ArticleDiscount($discount, $discountType);
    }

    /**
     * Return the current discount
     *
     * @return ArticleDiscount|null
     */
    public function getDiscount(): ?ArticleDiscount
    {
        $this->Discount?->setArticle($this);

        return $this->Discount;
    }

    /**
     * Has the article a discount
     *
     * @return bool
     */
    public function hasDiscount(): bool
    {
        return !!$this->getDiscount();
    }

    //endregion

    /**
     * @param null|Calc|QUI\ERP\User $Instance
     * @return self
     */
    public function calc(Calc|QUI\ERP\User $Instance = null): Article
    {
        if ($this->calculated) {
            return $this;
        }

        if ($Instance instanceof QUI\ERP\User) {
            $Calc = Calc::getInstance($Instance);
        } elseif ($Instance instanceof Calc) {
            $Calc = $Instance;
        } else {
            $Calc = Calc::getInstance();
        }

        if ($this->getUser()) {
            $Calc->setUser($this->getUser());
        }

        $Calc->calcArticlePrice($this, function ($data) {
            $self = $this;
            $self->price = $data['price'];
            $self->sum = $data['sum'];

            if (isset($data['nettoPriceNotRounded'])) {
                $self->nettoPriceNotRounded = $data['nettoPriceNotRounded'];
            }

            $self->basisPrice = $data['basisPrice'];

            $self->nettoPrice = $data['nettoPrice'];
            $self->nettoBasisPrice = $data['nettoBasisPrice'];
            $self->nettoSubSum = $data['nettoSubSum'];
            $self->nettoSum = $data['nettoSum'];

            $self->vatArray = $data['vatArray'];
            $self->isEuVat = $data['isEuVat'];
            $self->isNetto = $data['isNetto'];

            $self->calculated = true;
        });

        return $this;
    }

    /**
     * Return the article as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $vat = $this->getVat();
        $discount = '';

        if (isset($this->attributes['vat']) && $this->attributes['vat'] !== '') {
            $vat = floatval($this->attributes['vat']);
        }

        if ($this->hasDiscount()) {
            $discount = $this->Discount->toJSON();
        }

        $class = get_called_class();

        if (!empty($this->attributes['control'])) {
            $class = $this->attributes['control'];
        }


        $quantityUnit = '';

        if (isset($this->attributes['quantityUnit'])) {
            $quantityUnit = $this->attributes['quantityUnit'];
        }


        return [
            // article data
            'id' => $this->getId(),
            'uuid' => $this->getUuid(),
            'productSetParentUuid' => $this->getProductSetParentUuid(),
            'title' => $this->getTitle(),
            'articleNo' => $this->getArticleNo(),
            'gtin' => $this->getGTIN(),
            'description' => $this->getDescription(),
            'unitPrice' => $this->getUnitPrice()->value(),
            'displayPrice' => $this->displayPrice(),
            'quantity' => $this->getQuantity(),
            'quantityUnit' => $quantityUnit,
            'sum' => $this->getSum()->value(),
            'vat' => $vat,
            'discount' => $discount,
            'control' => $this->attributes['control'],
            'class' => $class,
            'customFields' => $this->customFields,
            'customData' => $this->customData,
            'position' => $this->position,

            // calculated data
            'calculated' => [
                'price' => $this->price,
                'basisPrice' => $this->basisPrice,
                'nettoPriceNotRounded' => $this->nettoPriceNotRounded,
                'sum' => $this->sum,
                'nettoPrice' => $this->nettoPrice,
                'nettoBasisPrice' => $this->nettoBasisPrice,
                'nettoSubSum' => $this->nettoSubSum,
                'nettoSum' => $this->nettoSum,
                'vatArray' => $this->vatArray,
                'isEuVat' => $this->isEuVat,
                'isNetto' => $this->isNetto
            ]
        ];
    }

    //region custom fields

    /**
     * Return an article custom field
     *
     * @param string $key
     * @return mixed|null
     */
    public function getCustomField(string $key): mixed
    {
        if (isset($this->customFields[$key])) {
            return $this->customFields[$key];
        }

        return null;
    }

    /**
     * @return array
     */
    public function getCustomFields(): array
    {
        return $this->customFields;
    }

    /**
     * @return array
     */
    public function getCustomData(): array
    {
        return $this->customData;
    }

    //endregion
}
