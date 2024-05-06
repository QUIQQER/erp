<?php

/**
 * This file contains QUI\ERP\Accounting\ArticleList
 */

namespace QUI\ERP\Accounting;

use IteratorAggregate;
use QUI;
use QUI\ERP\Accounting\PriceFactors\Factor;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Exception;
use QUI\Interfaces\Users\User;

use function count;
use function is_null;
use function key;
use function round;

/**
 * Class ArticleList
 *
 * @package QUI\ERP\Accounting
 */
class ArticleList extends ArticleListUnique implements IteratorAggregate
{
    /**
     * is the article list calculated?
     * @var bool
     */
    protected bool $calculated = false;

    /**
     * @var int|float
     */
    protected int|float $sum;

    /**
     * @var int|float
     */
    protected int|float $grandSubSum;

    /**
     * @var ?QUI\Interfaces\Users\User
     */
    protected QUI\Interfaces\Users\User|null $User = null;

    /**
     * @var ?QUI\ERP\Order\AbstractOrder
     */
    protected ?QUI\ERP\Order\AbstractOrder $Order = null;

    /**
     * @var ?QUI\ERP\Currency\Currency
     */
    protected ?QUI\ERP\Currency\Currency $Currency = null;

    /**
     * @var int|float
     */
    protected int|float $subSum;

    /**
     * @var int|float
     */
    protected int|float $nettoSum;

    /**
     * @var int|float
     */
    protected int|float $nettoSubSum;

    /**
     * key 19% value[sum] = sum value[text] = text value[display_sum] formatted
     *
     * @var array
     */
    protected array $vatArray = [];

    /**
     * key 19% value[sum] = sum value[text] = text value[display_sum] formatted
     *
     * @var array
     */
    protected array $vatText;

    /**
     * Prüfen ob EU Vat für den Benutzer in Frage kommt
     */
    protected bool $isEuVat = false;

    /**
     * Wird Brutto oder Netto gerechnet
     *
     * @var bool
     */
    protected bool $isNetto = true;

    /**
     * Currency information
     *
     * @var array
     */
    protected array $currencyData = [
        'currency_sign' => '',
        'currency_code' => '',
        'user_currency' => '',
        'currency_rate' => ''
    ];

    /**
     * ArticleList constructor.
     *
     * @param array $attributes
     * @throws Exception|QUI\Exception
     */
    public function __construct(array $attributes = [])
    {
        if (!isset($attributes['calculations'])) {
            $attributes['calculations'] = [];
        }

        if (!isset($attributes['articles'])) {
            $attributes['articles'] = [];
        }

        if (!isset($attributes['priceFactors'])) {
            $attributes['priceFactors'] = [];
        }

        parent::__construct($attributes);

        if (!empty($this->calculations)) {
            $this->calculated = true;
        }
    }

    /**
     * Set the user for the list
     * User for calculation
     *
     * @param QUI\Interfaces\Users\User $User
     */
    public function setUser(QUI\Interfaces\Users\User $User): void
    {
        if ($this->User === $User) {
            return;
        }

        $this->calculated = false;
        $this->User = $User;

        foreach ($this->articles as $Article) {
            $Article->setUser($User);
        }

        $this->calc();
    }

    /**
     * Return the list user
     *
     * @return User|null
     */
    public function getUser(): ?QUI\Interfaces\Users\User
    {
        return $this->User;
    }

    /**
     * Return the currency
     *
     * @return Currency|null
     */
    public function getCurrency(): ?QUI\ERP\Currency\Currency
    {
        if (!is_null($this->Currency)) {
            return $this->Currency;
        }

        if (!empty($this->currencyData['currency_code'])) {
            try {
                $this->Currency = QUI\ERP\Currency\Handler::getCurrency(
                    $this->currencyData['currency_code']
                );

                return $this->Currency;
            } catch (QUI\Exception) {
            }
        }

        return QUI\ERP\Defaults::getCurrency();
    }

    /**
     * Set the currency for the list
     *
     * @param QUI\ERP\Currency\Currency $Currency
     */
    public function setCurrency(QUI\ERP\Currency\Currency $Currency): void
    {
        if ($this->Currency === $Currency) {
            return;
        }

        $this->Currency = $Currency;

        $this->currencyData = [
            'currency_sign' => $this->Currency->getSign(),
            'currency_code' => $this->Currency->getCode(),
            'user_currency' => '',
            'currency_rate' => $this->Currency->getExchangeRate()
        ];

        if (isset($this->calculations['currencyData'])) {
            $this->calculations['currencyData'] = [
                'code' => $this->Currency->getCode(),
                'sign' => $this->Currency->getSign(),
                'rate' => $this->Currency->getExchangeRate()
            ];
        }

        if (count($this->articles)) {
            foreach ($this->articles as $Article) {
                $Article->setCurrency($Currency);
            }
        }

        $PriceFactors = $this->getPriceFactors();
        $priceFactors = $PriceFactors->toArray();

        foreach ($priceFactors as $k => $factor) {
            $priceFactors[$k]['sumFormatted'] = $Currency->format($factor['sum']);
            $priceFactors[$k]['nettoSumFormatted'] = $Currency->format($factor['nettoSum']);
            $priceFactors[$k]['valueText'] = $priceFactors[$k]['sumFormatted'];

            if ($factor['sum'] > 0) {
                $priceFactors[$k]['valueText'] = '+' . $priceFactors[$k]['sumFormatted'];
            }
        }

        $this->PriceFactors = new QUI\ERP\Accounting\PriceFactors\FactorList($priceFactors);
    }

    /**
     * Return the list as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        if (empty($data['calculations'])) {
            return $data;
        }

        $Currency = $this->getCurrency();

        // format
        $articles = $data['articles'];
        $calculations = $data['calculations'];

        $calculations['currencyData'] = $Currency->toArray();

        $calculations['vatSum'] = QUI\ERP\Accounting\Calc::calculateTotalVatOfInvoice(
            $calculations['vatArray']
        );

        $calculations['display_subSum'] = $Currency->format($calculations['subSum']);
        $calculations['display_sum'] = $Currency->format($calculations['sum']);
        $calculations['display_vatSum'] = $Currency->format($calculations['vatSum']);

        foreach ($articles as $key => $article) {
            $articles[$key]['position'] = $key + 1;
            $articles[$key]['display_sum'] = $Currency->format($article['sum']);
            $articles[$key]['display_unitPrice'] = $Currency->format($article['unitPrice']);
        }

        $data['articles'] = $articles;
        $data['calculations'] = $calculations;

        /* @var $Factor PriceFactors\Factor */
        foreach ($this->PriceFactors as $Factor) {
            if (!$Factor->isVisible()) {
                continue;
            }

            $key = 'attributes';

            if ($Factor->getCalculationBasis() === QUI\ERP\Accounting\Calc::CALCULATION_GRAND_TOTAL) {
                $key = 'grandTotalFactors';
            }

            $data[$key][] = [
                'title' => $Factor->getTitle(),
                'value' => $Factor->getSumFormatted(),
                'valueText' => ''
            ];
        }

        return $data;
    }

    /**
     * Parse this ArticleList to an ArticleListUnique
     *
     * @return ArticleListUnique
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function toUniqueList(): ArticleListUnique
    {
        $this->calc();

        $List = new ArticleListUnique($this->toArray(), $this->getUser());

        if ($this->ExchangeCurrency) {
            $List->setExchangeCurrency($this->ExchangeCurrency);
        }

        return $List;
    }

    /**
     * @param null $Calc
     */
    public function recalculate($Calc = null): void
    {
        $this->calculated = false;

        foreach ($this->articles as $Article) {
            if ($this->User) {
                $Article->setUser($this->User);
            }
        }

        $this->calc($Calc);
    }

    /**
     * @param null $Calc $Calc
     * @return ArticleList
     */
    public function calc($Calc = null): ArticleList
    {
        if ($this->calculated) {
            return $this;
        }

        if (!$Calc) {
            $Calc = Calc::getInstance();

            if ($this->User) {
                $Calc->setUser($this->User);
            }
        }

        $Calc->calcArticleList($this, function ($data) {
            $self = $this;
            $self->sum = $data['sum'];
            $self->grandSubSum = $data['grandSubSum'];
            $self->subSum = $data['subSum'];
            $self->nettoSum = $data['nettoSum'];
            $self->nettoSubSum = $data['nettoSubSum'];
            $self->vatArray = $data['vatArray'];
            $self->vatText = $data['vatText'];
            $self->isEuVat = $data['isEuVat'];
            $self->isNetto = $data['isNetto'];
            $self->currencyData = $data['currencyData'];

            $this->calculations = [
                'sum' => $self->sum,
                'grandSubSum' => $self->grandSubSum,
                'subSum' => $self->subSum,
                'nettoSum' => $self->nettoSum,
                'nettoSubSum' => $self->nettoSubSum,
                'vatArray' => $self->vatArray,
                'vatText' => $self->vatText,
                'isEuVat' => $self->isEuVat,
                'isNetto' => $self->isNetto,
                'currencyData' => $self->currencyData
            ];

            $self->setCurrency($self->getCurrency());
            $self->calculated = true;
        });

        return $this;
    }

    //region Article Management

    /**
     * Add an article to the list
     *
     * @param Article $Article
     */
    public function addArticle(Article $Article): void
    {
        $this->articles[] = $Article;
        $this->calculated = false;

        if ($this->User) {
            $Article->setUser($this->User);
        }

        if ($this->Currency) {
            $Article->setCurrency($this->Currency);
        }
    }

    /**
     * Remove an article by its index position
     *
     * @param integer $index
     */
    public function removeArticle(int $index): void
    {
        if (isset($this->articles[$index])) {
            unset($this->articles[$index]);
        }
    }

    /**
     * @param $pos
     * @return Article|null
     */
    public function getArticle($pos): ?Article
    {
        if (isset($this->articles[$pos])) {
            return $this->articles[$pos];
        }

        return null;
    }

    /**
     * Replace an article at a specific position
     *
     * @param Article $Article
     * @param integer $index
     */
    public function replaceArticle(Article $Article, int $index): void
    {
        $this->articles[$index] = $Article;
    }

    /**
     * Clears the list
     */
    public function clear(): void
    {
        $this->articles = [];
    }

    /**
     * Return the length of the list
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->articles);
    }

    //endregion

    //region Price Factors

    /**
     * Import a price factor list
     *
     * @param QUI\ERP\Accounting\PriceFactors\FactorList $PriceFactors
     */
    public function importPriceFactors(QUI\ERP\Accounting\PriceFactors\FactorList $PriceFactors): void
    {
        $this->PriceFactors = $PriceFactors;
    }

    /**
     * @param Factor|QUI\ERP\Products\Utils\PriceFactor $PriceFactor
     * @return void
     *
     * @throws Exception
     */
    public function addPriceFactor(Factor|QUI\ERP\Products\Utils\PriceFactor $PriceFactor): void
    {
        if ($PriceFactor instanceof QUI\ERP\Products\Utils\PriceFactor) {
            $isNetto = $this->isNetto;
            $vat = 0;
            $netto = $PriceFactor->getNettoSum();
            $precision = $this->getCurrency()->getPrecision();

            if (count($this->vatArray)) {
                $vat = key($this->vatArray);
            }

            if ($PriceFactor->getVat()) {
                $vat = $PriceFactor->getVat();
            }

            $PriceFactor->setVat($vat);

            $brutto = $netto * ((100 + $vat) / 100);
            $brutto = round($brutto, $precision);

            if ($isNetto) {
                $PriceFactor->setSum($netto);
            } else {
                $PriceFactor->setSum($brutto);
            }

            $PriceFactor = new Factor($PriceFactor->toArray());
        }

        $this->PriceFactors->addFactor($PriceFactor);
        $this->recalculate();
    }

    //endregion

    //region order

    /**
     * @param QUI\ERP\Order\AbstractOrder $Order
     */
    public function setOrder(QUI\ERP\Order\AbstractOrder $Order): void
    {
        $this->Order = $Order;
    }

    /**
     * @return QUI\ERP\Order\AbstractOrder|null
     */
    public function getOrder(): ?QUI\ERP\Order\AbstractOrder
    {
        return $this->Order;
    }

    //endregion
}
