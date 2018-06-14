<?php

/**
 * This file contains QUI\ERP\Accounting\Article
 */

namespace QUI\ERP\Accounting;

use QUI;
use QUI\ERP\Money\Price;

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
    protected $attributes = [
        'control' => '',
        'class'   => ''
    ];

    /**
     * Custom fields are data which field out the customer
     * This data is not used for presentation or calculation
     *
     * in a custom field are only allowed string and numeric values
     *
     * @var array
     */
    protected $customFields = [];

    /**
     * @var bool
     */
    protected $calculated = false;

    /**
     * @var float|int
     */
    protected $price;

    /**
     * @var float|int
     */
    protected $basisPrice;

    /**
     * @var float|int
     */
    protected $sum;

    /**
     * The calculated netto sum with quantity and discount
     * @var float|int
     */
    protected $nettoSum;

    /**
     * Sum from the article, without discount and with quantity
     *
     * @var float|int
     */
    protected $nettoSubSum;

    /**
     * The article netto price, without discount, without quantity
     * comes from article
     *
     * @var float|int
     */
    protected $nettoPrice;

    /**
     * The article netto price, without discount, without quantity
     * comes from calc
     *
     * @var float|int
     */
    protected $nettoBasisPrice;

    /**
     * @var array
     */
    protected $vatArray;

    /**
     * @var bool
     */
    protected $isEuVat;

    /**
     * @var bool
     */
    protected $isNetto;

    /**
     * @var ArticleDiscount|null
     */
    protected $Discount = null;

    /**
     * @var null|QUI\Interfaces\Users\User
     */
    protected $User = null;

    /**
     * Article constructor.
     *
     * @param array $attributes - (id, articleNo, title, description, unitPrice, quantity, discount)
     */
    public function __construct($attributes = [])
    {
        $defaults = [
            'id',
            'articleNo',
            'title',
            'description',
            'unitPrice',
            'control',
            'quantity'
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

        if (isset($attributes['discount'])) {
            $this->Discount = ArticleDiscount::unserialize($attributes['discount']);
        }


        if (isset($attributes['calculated'])) {
            $calc = $attributes['calculated'];

            $this->price      = $calc['price'];
            $this->basisPrice = $calc['basisPrice'];
            $this->sum        = $calc['sum'];

            $this->nettoPrice      = $calc['nettoPrice'];
            $this->nettoBasisPrice = $calc['nettoBasisPrice'];
            $this->nettoSum        = $calc['nettoSum'];

            $this->vatArray = $calc['vatArray'];
            $this->isEuVat  = $calc['isEuVat'];
            $this->isNetto  = $calc['isNetto'];

            $this->calculated = true;
        }

        if (isset($attributes['customFields']) && is_array($attributes['customFields'])) {
            $this->customFields = $attributes['customFields'];
        }
    }

    /**
     * Return the article view
     *
     * @return ArticleView
     */
    public function getView()
    {
        return new ArticleView($this);
    }

    /**
     * Return the Article ID
     *
     * @return mixed|string
     */
    public function getId()
    {
        if (isset($this->attributes['id'])) {
            return $this->attributes['id'];
        }

        return '';
    }

    /**
     * Return the Article Number
     *
     * @return mixed|string
     */
    public function getArticleNo()
    {
        if (isset($this->attributes['articleNo'])) {
            return $this->attributes['articleNo'];
        }

        return '';
    }

    /**
     * Returns the article title
     *
     * @return string
     */
    public function getTitle()
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
    public function getImage()
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

        if (!empty($Product)) {
            try {
                return $Product->getImage();
            } catch (QUI\Exception $Exception) {
            }
        }

        try {
            $Project = QUI::getRewrite()->getProject();

            return $Project->getMedia()->getPlaceholderImage();
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
    public function getDescription()
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
    public function getUnitPrice()
    {
        $unitPrice = 0;

        if (isset($this->attributes['unitPrice'])) {
            $unitPrice = $this->attributes['unitPrice'];
        }

        return new Price($unitPrice, QUI\ERP\Defaults::getCurrency());
    }

    /**
     * Returns the article total sum
     *
     * @return QUI\ERP\Money\Price
     */
    public function getSum()
    {
        $this->calc();

        return new Price($this->sum, QUI\ERP\Defaults::getCurrency());
    }

    /**
     * Return the VAT for the article
     *
     * @return int
     */
    public function getVat()
    {
        if (isset($this->attributes['vat']) && $this->attributes['vat'] !== '') {
            return (int)$this->attributes['vat'];
        }

        try {
            if ($this->getUser()) {
                return QUI\ERP\Tax\Utils::getTaxByUser($this->getUser())->getValue();
            }

            // return default vat
            $Area     = QUI\ERP\Defaults::getArea();
            $TaxType  = QUI\ERP\Tax\Utils::getTaxTypeByArea($Area);
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
    public function getUser()
    {
        return $this->User;
    }

    /**
     * Set the user to the product, this user will be used for the calculation
     *
     * @param QUI\Interfaces\Users\User $User
     */
    public function setUser(QUI\Interfaces\Users\User $User)
    {
        $this->calculated = false;
        $this->User       = $User;
    }

    /**
     * Returns the article quantity
     *
     * @return int|bool
     */
    public function getQuantity()
    {
        if (isset($this->attributes['quantity'])) {
            return $this->attributes['quantity'];
        }

        return 1;
    }

    /**
     * Return the price from the article
     *
     * @return Price
     */
    public function getPrice()
    {
        $this->calc();

        return new Price(
            $this->sum,
            QUI\ERP\Currency\Handler::getDefaultCurrency()
        );
    }

    //region Discounts

    /**
     * Set a discount to the article
     *
     * @param int $discount
     * @param int $discountType - default = complement
     *
     * @todo überdenken, ganzer artikel ist eigentlich nicht änderbar
     */
    public function setDiscount($discount, $discountType = Calc::CALCULATION_COMPLEMENT)
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
    public function getDiscount()
    {
        return $this->Discount;
    }

    /**
     * Has the article a discount
     *
     * @return bool
     */
    public function hasDiscount()
    {
        return !!$this->getDiscount();
    }

    //endregion

    /**
     * @param null|Calc|QUI\ERP\User $Instance
     * @return self
     */
    public function calc($Instance = null)
    {
        if ($this->calculated) {
            return $this;
        }

        $self = $this;

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

        $Calc->calcArticlePrice($this, function ($data) use ($self) {
            $self->price      = $data['price'];
            $self->basisPrice = $data['basisPrice'];
            $self->sum        = $data['sum'];

            $self->nettoPrice      = $data['nettoPrice'];
            $self->nettoBasisPrice = $data['nettoBasisPrice'];
            $self->nettoSubSum     = $data['nettoSubSum'];
            $self->nettoSum        = $data['nettoSum'];

            $self->vatArray = $data['vatArray'];
            $self->isEuVat  = $data['isEuVat'];
            $self->isNetto  = $data['isNetto'];

            $self->calculated = true;
        });

        return $this;
    }

    /**
     * Return the article as an array
     *
     * @return array
     */
    public function toArray()
    {
        $vat      = $this->getVat();
        $discount = '';

        if (isset($this->attributes['vat']) && $this->attributes['vat'] !== '') {
            $vat = (int)$this->attributes['vat'];
        }

        if ($this->hasDiscount()) {
            $discount = $this->Discount->toJSON();
        }

        return [
            // article data
            'id'           => $this->getId(),
            'title'        => $this->getTitle(),
            'articleNo'    => $this->getArticleNo(),
            'description'  => $this->getDescription(),
            'unitPrice'    => $this->getUnitPrice()->value(),
            'quantity'     => $this->getQuantity(),
            'sum'          => $this->getSum()->value(),
            'vat'          => $vat,
            'discount'     => $discount,
            'control'      => $this->attributes['control'],
            'class'        => self::class,
            'customFields' => $this->customFields,

            // calculated data
            'calculated'   => [
                'price'           => $this->price,
                'basisPrice'      => $this->basisPrice,
                'sum'             => $this->sum,
                'nettoPrice'      => $this->nettoPrice,
                'nettoBasisPrice' => $this->nettoBasisPrice,
                'nettoSubSum'     => $this->nettoSubSum,
                'nettoSum'        => $this->nettoSum,
                'vatArray'        => $this->vatArray,
                'isEuVat'         => $this->isEuVat,
                'isNetto'         => $this->isNetto
            ]
        ];
    }

    //region custom fields

    /**
     * Return a article custom field
     *
     * @param string $key
     * @return mixed|null
     */
    public function getCustomField($key)
    {
        if (isset($this->customFields[$key])) {
            return $this->customFields[$key];
        }

        return null;
    }

    /**
     * @return array
     */
    public function getCustomFields()
    {
        return $this->customFields;
    }

    //endregion
}
