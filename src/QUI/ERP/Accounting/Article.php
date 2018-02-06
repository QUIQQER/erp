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
    protected $attributes = array(
        'control' => '',
        'class'   => ''
    );

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
    public function __construct($attributes = array())
    {
        $defaults = array(
            'id',
            'articleNo',
            'title',
            'description',
            'unitPrice',
            'control',
            'quantity'
        );

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
     * @return int|float
     */
    public function getUnitPrice()
    {
        if (isset($this->attributes['unitPrice'])) {
            return $this->attributes['unitPrice'];
        }

        return 0;
    }

    /**
     * Returns the article total sum
     *
     * @return int|float
     */
    public function getSum()
    {
        $this->calc();

        return $this->sum;
    }

    /**
     * Return the VAT for the article
     *
     * @return int
     *
     * @throws QUI\Exception
     */
    public function getVat()
    {
        if (isset($this->attributes['vat']) && $this->attributes['vat'] !== '') {
            return (int)$this->attributes['vat'];
        }

        if ($this->getUser()) {
            return QUI\ERP\Tax\Utils::getTaxByUser($this->getUser())->getValue();
        }

        // return default vat
        $Area     = QUI\ERP\Defaults::getArea();
        $TaxType  = QUI\ERP\Tax\Utils::getTaxTypeByArea($Area);
        $TaxEntry = QUI\ERP\Tax\Utils::getTaxEntry($TaxType, $Area);

        return $TaxEntry->getValue();
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
     *
     * @throws QUI\Exception
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

        return array(
            // article data
            'id'          => $this->getId(),
            'title'       => $this->getTitle(),
            'articleNo'   => $this->getArticleNo(),
            'description' => $this->getDescription(),
            'unitPrice'   => $this->getUnitPrice(),
            'quantity'    => $this->getQuantity(),
            'sum'         => $this->getSum(),
            'vat'         => $vat,
            'discount'    => $discount,
            'control'     => $this->attributes['control'],
            'class'       => self::class,

            // calculated data (old api)
//            'calculated_basisPrice'      => $this->basisPrice,
//            'calculated_price'           => $this->price,
//            'calculated_sum'             => $this->sum,
//            'calculated_nettoBasisPrice' => $this->nettoBasisPrice,
//            'calculated_nettoPrice'      => $this->nettoPrice,
//            'calculated_nettoSubSum'     => $this->nettoSubSum,
//            'calculated_nettoSum'        => $this->nettoSum,
//            'calculated_isEuVat'         => $this->isEuVat,
//            'calculated_isNetto'         => $this->isNetto,
//            'calculated_vatArray'        => $this->vatArray,

            // calculated data (new api)
            'calculated'  => array(
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
            )
        );
    }
}
