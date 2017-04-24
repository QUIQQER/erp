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
    protected $attributes = array();

    /**
     * @var bool
     */
    protected $calculated = false;

    /**
     * @var
     */
    protected $price;

    /**
     * @var
     */
    protected $basisPrice;

    /**
     * @var
     */
    protected $sum;

    /**
     * @var
     */
    protected $nettoSum;

    /**
     * @var
     */
    protected $vatArray;

    /**
     * @var
     */
    protected $isEuVat;

    /**
     * @var
     */
    protected $isNetto;

    /**
     * Article constructor.
     *
     * @param array $attributes - (id, articleNo, title, description, unitPrice, quantity)
     */
    public function __construct($attributes = array())
    {
        if (isset($attributes['id'])) {
            $this->attributes['id'] = $attributes['id'];
        }

        if (isset($attributes['articleNo'])) {
            $this->attributes['articleNo'] = $attributes['articleNo'];
        }

        if (isset($attributes['title'])) {
            $this->attributes['title'] = $attributes['title'];
        }

        if (isset($attributes['description'])) {
            $this->attributes['description'] = $attributes['description'];
        }

        if (isset($attributes['unitPrice'])) {
            $this->attributes['unitPrice'] = $attributes['unitPrice'];
        }

        if (isset($attributes['quantity'])) {
            $this->attributes['quantity'] = $attributes['quantity'];
        }

        if (isset($attributes['vat'])) {
            $this->attributes['vat'] = $attributes['vat'];
        }


        if (isset($attributes['calculated'])) {
            $calc = $attributes['calculated'];

            $this->price      = $calc['price'];
            $this->basisPrice = $calc['basisPrice'];
            $this->sum        = $calc['sum'];
            $this->nettoSum   = $calc['nettoSum'];
            $this->vatArray   = $calc['vatArray'];
            $this->isEuVat    = $calc['isEuVat'];
            $this->isNetto    = $calc['isNetto'];
        }
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
     * @return int
     */
    public function getVat()
    {
        if (isset($this->attributes['vat'])) {
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
     * @return null
     * @todo implement
     */
    public function getUser()
    {
        return null;
    }

    /**
     * Returns the article quantity
     *
     * @return int
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

    /**
     * @param null|Calc $Calc
     * @return self
     */
    public function calc($Calc = null)
    {
        if ($this->calculated) {
            return $this;
        }

        $self = $this;

        if (!$Calc) {
            $Calc = Calc::getInstance($this->getUser());
        }

        $Calc->calcArticlePrice($this, function ($data) use ($self) {
            $self->price      = $data['price'];
            $self->basisPrice = $data['basisPrice'];
            $self->sum        = $data['sum'];
            $self->nettoSum   = $data['nettoSum'];
            $self->vatArray   = $data['vatArray'];
            $self->isEuVat    = $data['isEuVat'];
            $self->isNetto    = $data['isNetto'];

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
        return array(
            'title'       => $this->getTitle(),
            'articleNo'   => $this->getArticleNo(),
            'description' => $this->getDescription(),
            'unitPrice'   => $this->getUnitPrice(),
            'quantity'    => $this->getQuantity(),
            'sum'         => $this->getSum(),

            'calculated_basisPrice' => $this->basisPrice,
            'calculated_price'      => $this->price,
            'calculated_sum'        => $this->sum,
            'calculated_nettoSum'   => $this->nettoSum,
            'calculated_isEuVat'    => $this->isEuVat,
            'calculated_isNetto'    => $this->isNetto,
            'calculated_vatArray'   => $this->vatArray
        );
    }
}
