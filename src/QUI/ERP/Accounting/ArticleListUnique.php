<?php

/**
 * This file contains QUI\ERP\Accounting\ArticleList
 */

namespace QUI\ERP\Accounting;

use ArrayIterator;
use IteratorAggregate;
use QUI;
use QUI\ERP\Accounting\PriceFactors\FactorList as ErpFactorList;
use Traversable;

use function array_map;
use function class_exists;
use function class_implements;
use function count;
use function dirname;
use function file_exists;
use function file_get_contents;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Class ArticleListUnique
 * - Nicht änderbare Artikel Liste
 *
 * @package QUI\ERP\Accounting
 */
class ArticleListUnique implements IteratorAggregate
{
    /**
     * @var Article[]
     */
    protected array $articles = [];

    /**
     * @var array
     */
    protected mixed $calculations = [];

    /**
     * @var bool|mixed
     */
    protected mixed $showHeader;

    /**
     * PriceFactor List
     *
     * @var ErpFactorList
     */
    protected ErpFactorList $PriceFactors;

    /**
     * @var null|QUI\Locale
     */
    protected ?QUI\Locale $Locale = null;

    /**
     * @var ?QUI\Interfaces\Users\User
     */
    protected ?QUI\Interfaces\Users\User $User = null;

    /**
     * @var bool
     */
    protected bool $showExchangeRate = true;

    /**
     * @var null|QUI\ERP\Currency\Currency
     */
    protected ?QUI\ERP\Currency\Currency $ExchangeCurrency = null;

    /**
     * @var float|null
     */
    protected ?float $exchangeRate = null;

    /**
     * ArticleList constructor.
     *
     * @param array $attributes
     * @param ?QUI\Interfaces\Users\User $User
     * @throws QUI\ERP\Exception|QUI\Exception
     */
    public function __construct(array $attributes = [], QUI\Interfaces\Users\User $User = null)
    {
        $this->Locale = QUI::getLocale();

        $needles = ['articles', 'calculations'];

        foreach ($needles as $needle) {
            if (!isset($attributes[$needle])) {
                throw new QUI\ERP\Exception(
                    'Missing needle for ArticleListUnique',
                    400,
                    [
                        'class' => 'ArticleListUnique',
                        'missing' => $needle
                    ]
                );
            }
        }

        $articles = $attributes['articles'];
        $currency = QUI\ERP\Currency\Handler::getDefaultCurrency()->getCode();

        if (isset($attributes['calculations']['currencyData']['code'])) {
            $currency = $attributes['calculations']['currencyData']['code'];
        }

        // sorting
        $articles = $this->sortArticlesWithParents($articles);

        // adding
        foreach ($articles as $article) {
            if (!isset($article['currency'])) {
                $article['currency'] = $currency;
            }

            if (!isset($article['class'])) {
                $this->articles[] = new Article($article);
                continue;
            }

            $class = $article['class'];

            if (!class_exists($class)) {
                $this->articles[] = new Article($article);
                continue;
            }

            $interfaces = class_implements($class);

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
        $this->showHeader = $attributes['showHeader'] ?? true;

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
     * Sorts items within the list by parent-child relationship.
     *
     * Items without `productSetParentUuid` are considered parents and positioned before their children,
     * with each child directly assigned to its parent via `productSetParentUuid`.
     *
     * Children follow immediately after their parents in the sorted list.
     * Each item is assigned a consecutive position, which reflects its order in the sorted list.
     *
     * @param array $articles - The input list of items, articles
     * @return array The sorted list of items with added 'position' keys, starting with 1.
     */
    protected function sortArticlesWithParents(array $articles = []): array
    {
        if (empty($articles)) {
            return [];
        }

        $sortedArticles = [];
        $children = [];

        foreach ($articles as $article) {
            if (!empty($article['productSetParentUuid'])) {
                $children[$article['productSetParentUuid']][] = $article;
            }
        }

        $positionCounter = 1;

        foreach ($articles as $article) {
            if (!empty($article['productSetParentUuid'])) {
                continue;
            }

            if (empty($article['uuid'])) {
                $sortedArticles[] = $article;
                continue;
            }

            $article['position'] = $positionCounter;
            $sortedArticles[] = $article;
            $uuid = $article['uuid'];

            if (isset($children[$uuid])) {
                $subPosition = 0.1;
                foreach ($children[$uuid] as $child) {
                    $child['position'] = $positionCounter + $subPosition;
                    $sortedArticles[] = $child;
                    $subPosition += 0.1;
                }
            }

            $positionCounter++;
        }

        return $sortedArticles;
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
    public function calc($Calc = null): ArticleListUnique
    {
        // placeholder. unique list cant be calc
        return $this;
    }

    /**
     * Set locale
     *
     * @param QUI\Locale $Locale
     */
    public function setLocale(QUI\Locale $Locale): void
    {
        $this->Locale = $Locale;
    }

    /**
     * Creates a list from a stored representation
     *
     * @param array|string $data
     * @return ArticleListUnique
     *
     * @throws QUI\Exception
     */
    public static function unserialize(array|string $data): ArticleListUnique
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
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
        return json_encode($this->toArray());
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
        return count($this->articles);
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

        $articles = array_map(function ($Article) {
            return $Article->toArray();
        }, $this->articles);

        $this->PriceFactors->toArray();

        return [
            'articles' => $articles,
            'calculations' => $this->calculations,
            'priceFactors' => $this->PriceFactors->toArray()
        ];
    }

    /**
     * Display of the header = true
     */
    public function displayHeader(): void
    {
        $this->showHeader = true;
    }

    /**
     * Display of the header = false
     */
    public function hideHeader(): void
    {
        $this->showHeader = false;
    }

    /**
     * @param QUI\ERP\Currency\Currency $Currency
     */
    public function setExchangeCurrency(QUI\ERP\Currency\Currency $Currency): void
    {
        $this->ExchangeCurrency = $Currency;
    }

    /**
     * @param float $rate
     */
    public function setExchangeRate(float $rate): void
    {
        $this->exchangeRate = $rate;
    }

    /**
     * Return the Article List as HTML, without CSS
     *
     * @param bool|string $template - custom template
     * @return string
     *
     * @throws QUI\Exception
     */
    public function toHTML(
        bool|string $template = false,
        bool|string $articleTemplate = false
    ): string {
        $Engine = QUI::getTemplateManager()->getEngine();
        $vatArray = [];

        if (!$this->count()) {
            return '';
        }

        $Currency = QUI\ERP\Currency\Handler::getCurrency(
            $this->calculations['currencyData']['code']
        );

        if (isset($this->calculations['currencyData']['rate'])) {
            $Currency->setExchangeRate($this->calculations['currencyData']['rate']);
        }

        if ($this->calculations['vatArray']) {
            $vatArray = $this->calculations['vatArray'];
        }

        // price display
        foreach ($vatArray as $key => $vat) {
            $vatArray[$key]['sum'] = $Currency->format($vat['sum']);
        }

        $this->calculations['sum'] = $Currency->format($this->calculations['sum']);
        $this->calculations['subSum'] = $Currency->format($this->calculations['subSum']);

        // Fallback for older unique article lists
        if (!isset($this->calculations['grandSubSum'])) {
            $this->calculations['grandSubSum'] = $this->calculations['sum'];
        }

        $this->calculations['grandSubSum'] = $Currency->format($this->calculations['grandSubSum']);
        $this->calculations['nettoSum'] = $Currency->format($this->calculations['nettoSum']);
        $this->calculations['nettoSubSum'] = $Currency->format($this->calculations['nettoSubSum']);

        $articles = [];

        foreach ($this->articles as $Article) {
            $View = $Article->getView();
            $View->setCurrency($Currency);
            $position = $View->getPosition();

            if (floor($position) % 2) {
                $View->setAttribute('odd', true);
            } else {
                $View->setAttribute('even', true);
            }

            $articles[] = $View;
        }

        $ExchangeCurrency = $this->ExchangeCurrency;
        $showExchangeRate = $this->showExchangeRate;
        $exchangeRateText = '';

        if (!$ExchangeCurrency || $ExchangeCurrency->getCode() === $Currency->getCode()) {
            $showExchangeRate = false;
            $exchangeRate = false;
        } else {
            if ($this->exchangeRate) {
                $Currency->setExchangeRate($this->exchangeRate);
            }

            if (
                class_exists('QUI\ERP\CryptoCurrency\Currency')
                && $Currency instanceof QUI\ERP\CryptoCurrency\Currency
            ) {
                $ExchangeCurrency->setExchangeRate($this->exchangeRate);
                $exchangeRate = $Currency->convertFormat(1, $ExchangeCurrency);
            } else {
                $exchangeRate = $Currency->getExchangeRate($ExchangeCurrency);
                $exchangeRate = $ExchangeCurrency->format($exchangeRate);
            }

            $exchangeRateText = $this->Locale->get('quiqqer/erp', 'exchangerate.text', [
                'startCurrency' => $Currency->format(1),
                'rate' => $exchangeRate
            ]);
        }

        // if currency of list is other currency like the default one
        // currency = BTC, Default = EUR
        // exchange rate must be displayed
        if ($Currency->getCode() !== QUI\ERP\Defaults::getCurrency()->getCode()) {
            $showExchangeRate = true;
            $DefaultCurrency = QUI\ERP\Defaults::getCurrency();

            if (
                class_exists('QUI\ERP\CryptoCurrency\Currency')
                && $Currency instanceof QUI\ERP\CryptoCurrency\Currency
            ) {
                $DefaultCurrency->setExchangeRate($this->exchangeRate);
                $exchangeRate = $Currency->convertFormat(1, $DefaultCurrency);
            } else {
                $exchangeRate = $Currency->getExchangeRate($DefaultCurrency);
                $exchangeRate = $DefaultCurrency->format($exchangeRate);
            }

            $exchangeRateText = $this->Locale->get('quiqqer/erp', 'exchangerate.text', [
                'startCurrency' => $Currency->format(1),
                'rate' => $exchangeRate
            ]);
        }

        $priceFactors = [];
        $grandTotal = [];

        foreach ($this->PriceFactors as $Factor) {
            if ($Factor->getCalculationBasis() === QUI\ERP\Accounting\Calc::CALCULATION_GRAND_TOTAL) {
                $grandTotal[] = $Factor->toArray();
                continue;
            }

            $priceFactors[] = $Factor->toArray();
        }

        // output
        $Engine->assign([
            'priceFactors' => $priceFactors,
            'grandTotal' => $grandTotal,
            'showHeader' => $this->showHeader,
            'this' => $this,
            'articles' => $articles,
            'articleTemplate' => $articleTemplate,
            'calculations' => $this->calculations,
            'vatArray' => $vatArray,
            'Locale' => $this->Locale,
            'showExchangeRate' => $showExchangeRate,
            'exchangeRate' => $exchangeRate,
            'exchangeRateText' => $exchangeRateText,
            'Currency' => $Currency
        ]);

        if ($template && file_exists($template)) {
            return $Engine->fetch($template);
        }

        return $Engine->fetch(dirname(__FILE__) . '/ArticleList.html');
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    public function toMailHTML(): string
    {
        return $this->toHTML(dirname(__FILE__) . '/ArticleList.Mail.html');
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
        $style .= file_get_contents(dirname(__FILE__) . '/ArticleList.css');
        $style .= '</style>';

        return $style . $this->toHTML();
    }

    /**
     * Alias for toHTMLWithCSS
     *
     * @return string
     *
     * @throws QUI\Exception
     */
    public function render(): string
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
        $style .= file_get_contents(dirname(__FILE__) . '/ArticleList.Mail.css');
        $style .= '</style>';

        return $style . $this->toMailHTML();
    }

    //region Price Factors

    /**
     * Return the price factors list (list of price indicators)
     *
     * @return ErpFactorList
     */
    public function getPriceFactors(): ErpFactorList
    {
        return $this->PriceFactors;
    }

    //endregion

    //region iterator

    /**
     * Iterator helper
     *
     * @return ArrayIterator|Traversable
     */
    public function getIterator(): Traversable|ArrayIterator
    {
        return new ArrayIterator($this->articles);
    }

    //endregion
}
