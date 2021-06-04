<?php

/**
 * This file contains QUI\ERP\Accounting\ArticleList
 */

namespace QUI\ERP\Accounting;

use QUI;
use QUI\ERP\Accounting\PriceFactors\FactorList as ErpFactorList;

/**
 * Class ArticleListUnique
 * - Nicht änderbare Artikel Liste
 *
 * @package QUI\ERP\Accounting
 */
class ArticleListUnique implements \IteratorAggregate
{
    /**
     * @var Article[]
     */
    protected $articles = [];

    /**
     * @var array
     */
    protected $calculations = [];

    /**
     * @var bool|mixed
     */
    protected $showHeader;

    /**
     * PriceFactor List
     *
     * @var QUI\ERP\Accounting\PriceFactors\FactorList
     */
    protected $PriceFactors = false;

    /**
     * @var null
     */
    protected $Locale = null;

    /**
     * @var QUI\Interfaces\Users\User
     */
    protected $User = null;

    /**
     * @var bool
     */
    protected $showExchangeRate = true;

    /**
     * ArticleList constructor.
     *
     * @param array $attributes
     * @param null|QUI\Interfaces\Users\User|QUI\Users\User $User
     * @throws QUI\ERP\Exception
     */
    public function __construct($attributes = [], $User = null)
    {
        $this->Locale = QUI::getLocale();

        $needles = ['articles', 'calculations'];

        foreach ($needles as $needle) {
            if (!isset($attributes[$needle])) {
                throw new QUI\ERP\Exception(
                    'Missing needle for ArticleListUnique',
                    400,
                    [
                        'class'   => 'ArticleListUnique',
                        'missing' => $needle
                    ]
                );
            }
        }

        $articles = $attributes['articles'];

        foreach ($articles as $article) {
            if (!isset($article['class'])) {
                $this->articles[] = new Article($article);
                continue;
            }

            $class = $article['class'];

            if (!\class_exists($class)) {
                $this->articles[] = new Article($article);
                continue;
            }

            $interfaces = \class_implements($class);

            if (isset($interfaces[ArticleInterface::class])) {
                $this->articles[] = new $class($article);
                continue;
            }

            $this->articles[] = new Article($article);
        }

        if ($User) {
            $this->User = $User;

            foreach ($this->articles as $Article) {
                $Article->setUser($this->User);
            }
        }

        $this->calculations = $attributes['calculations'];
        $this->showHeader   = isset($attributes['showHeader']) ? $attributes['showHeader'] : true;

        // price factors
        $this->PriceFactors = new ErpFactorList();

        if (isset($attributes['priceFactors'])) {
            try {
                $this->PriceFactors = new ErpFactorList($attributes['priceFactors']);
            } catch (QUI\ERP\Exception $Exception) {
                QUI\System\Log::writeRecursive(
                    $attributes['priceFactors'],
                    QUI\System\Log::LEVEL_DEBUG
                );

                QUI\System\Log::writeDebugException($Exception);
            }
        }
    }

    /**
     * placeholder. unique list cant be recalculate
     * recalculate makes the unique article list compatible to the article list
     *
     * @param $Calc
     */
    public function recalculate($Calc = null)
    {
        // placeholder. unique list cant be recalculate
    }

    /**
     * placeholder. unique list cant be calc
     * calc makes the unique article list compatible to the article list
     *
     * @param $Calc
     * @return ArticleListUnique
     */
    public function calc($Calc = null)
    {
        // placeholder. unique list cant be calc
        return $this;
    }

    /**
     * Set locale
     *
     * @param QUI\Locale $Locale
     */
    public function setLocale(QUI\Locale $Locale)
    {
        $this->Locale = $Locale;
    }

    /**
     * Creates a list from a stored representation
     *
     * @param string $data
     * @return ArticleListUnique
     *
     * @throws QUI\Exception
     */
    public static function unserialize(string $data): ArticleListUnique
    {
        if (\is_string($data)) {
            $data = \json_decode($data, true);
        }

        return new self($data);
    }

    /**
     * Generates a storable representation of the list
     *
     * @return string
     */
    public function serialize(): string
    {
        return \json_encode($this->toArray());
    }

    /**
     * Return the calculation array
     *
     * @return array
     */
    public function getCalculations(): array
    {
        return $this->calculations;
    }

    /**
     * Return the list articles
     *
     * @return Article[]
     */
    public function getArticles(): array
    {
        return $this->articles;
    }

    /**
     * Return the number of articles
     *
     * @return int
     */
    public function count(): int
    {
        return \count($this->articles);
    }

    /**
     * Generates a storable json representation of the list
     * Alias for serialize()
     *
     * @return string
     */
    public function toJSON(): string
    {
        return $this->serialize();
    }

    /**
     * Return the list as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $this->calc();

        $articles = \array_map(function ($Article) {
            return $Article->toArray();
        }, $this->articles);

        $this->PriceFactors->toArray();

        return [
            'articles'     => $articles,
            'calculations' => $this->calculations,
            'priceFactors' => $this->PriceFactors->toArray()
        ];
    }

    /**
     * Display of the header = true
     */
    public function displayHeader()
    {
        $this->showHeader = true;
    }

    /**
     * Display of the header = false
     */
    public function hideHeader()
    {
        $this->showHeader = false;
    }

    /**
     * Return the Article List as HTML, without CSS
     *
     * @param string|bool $template - custom template
     * @return string
     *
     * @throws QUI\Exception
     */
    public function toHTML($template = false): string
    {
        $Engine   = QUI::getTemplateManager()->getEngine();
        $vatArray = [];

        if (!$this->count()) {
            return '';
        }

        $Currency = QUI\ERP\Currency\Handler::getCurrency(
            $this->calculations['currencyData']['code']
        );

        if ($this->calculations['vatArray']) {
            $vatArray = $this->calculations['vatArray'];
        }

        // price display
        foreach ($vatArray as $key => $vat) {
            $vatArray[$key]['sum'] = $Currency->format($vatArray[$key]['sum']);
        }

        $this->calculations['sum']         = $Currency->format($this->calculations['sum']);
        $this->calculations['subSum']      = $Currency->format($this->calculations['subSum']);
        $this->calculations['nettoSum']    = $Currency->format($this->calculations['nettoSum']);
        $this->calculations['nettoSubSum'] = $Currency->format($this->calculations['nettoSubSum']);

        $pos = 1;

        $articles = \array_map(function ($Article) use ($Currency, &$pos) {
            $View = $Article->getView();
            $View->setCurrency($Currency);
            $View->setPosition($pos);

            $pos++;

            return $View;
        }, $this->articles);

        $ExchangeCurrency = QUI\ERP\Currency\Conf::getAccountingCurrency();
        $showExchangeRate = $this->showExchangeRate;
        $exchangeRateText = '';

        if ($ExchangeCurrency->getCode() === $Currency->getCode()) {
            $showExchangeRate = false;
            $exchangeRate     = false;
        } else {
            $exchangeRate = $ExchangeCurrency->getExchangeRate($Currency);
            $exchangeRate = $Currency->format($exchangeRate);

            $exchangeRateText = $this->Locale->get('quiqqer/erp', 'exchangerate.text', [
                'startCurrency' => $ExchangeCurrency->format(1),
                'rate'          => $exchangeRate
            ]);
        }

        // output
        $Engine->assign([
            'priceFactors'     => $this->PriceFactors->toArray(),
            'showHeader'       => $this->showHeader,
            'this'             => $this,
            'articles'         => $articles,
            'calculations'     => $this->calculations,
            'vatArray'         => $vatArray,
            'Locale'           => $this->Locale,
            'showExchangeRate' => $showExchangeRate,
            'exchangeRate'     => $exchangeRate,
            'exchangeRateText' => $exchangeRateText
        ]);

        if ($template && \file_exists($template)) {
            return $Engine->fetch($template);
        }

        return $Engine->fetch(\dirname(__FILE__).'/ArticleList.html');
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    public function toMailHTML(): string
    {
        return $this->toHTML(\dirname(__FILE__).'/ArticleList.Mail.html');
    }

    /**
     * Return the Article List as HTML, with CSS
     *
     * @return string
     *
     * @throws QUI\Exception
     */
    public function toHTMLWithCSS(): string
    {
        $style = '<style>';
        $style .= \file_get_contents(\dirname(__FILE__).'/ArticleList.css');
        $style .= '</style>';

        return $style.$this->toHTML();
    }

    /**
     * Alias for toHTMLWithCSS
     *
     * @return string
     *
     * @throws QUI\Exception
     */
    public function render()
    {
        return $this->toHTMLWithCSS();
    }

    /**
     * Render the article list for mails
     *
     * @return string
     *
     * @throws QUI\Exception
     */
    public function renderForMail(): string
    {
        $style = '<style>';
        $style .= \file_get_contents(\dirname(__FILE__).'/ArticleList.Mail.css');
        $style .= '</style>';

        return $style.$this->toMailHTML();
    }

    //region Price Factors

    /**
     * Return the price factors list (list of price indicators)
     *
     * @return QUI\ERP\Accounting\PriceFactors\FactorList
     */
    public function getPriceFactors()
    {
        return $this->PriceFactors;
    }

    //endregion

    //region iterator

    /**
     * Iterator helper
     *
     * @return \ArrayIterator|\Traversable
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->articles);
    }

    //endregion
}
