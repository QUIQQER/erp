<?php

/**
 * This file contains QUI\ERP\Accounting\ArticleView
 */

namespace QUI\ERP\Accounting;

use QUI;

/**
 * Class ArticleView
 *
 * @package QUI\ERP\Accounting
 */
class ArticleView extends QUI\QDOM
{
    /**
     * @var int
     */
    protected $position;

    /**
     * @var Article
     */
    protected $Article;

    /**
     * @var QUI\ERP\Currency\Currency
     */
    protected $Currency;

    /**
     * ArticleView constructor.
     * @param Article $Article
     */
    public function __construct(Article $Article)
    {
        $this->Article = $Article;
    }

    /**
     * Set the currency
     *
     * @param QUI\ERP\Currency\Currency $Currency
     */
    public function setCurrency(QUI\ERP\Currency\Currency $Currency)
    {
        $this->Currency = $Currency;
    }

    /**
     * Set the position
     *
     * @param $position
     */
    public function setPosition($position)
    {
        $this->position = (int)$position;
    }

    /**
     * @return QUI\ERP\Currency\Currency
     */
    public function getCurrency()
    {
        if ($this->Currency !== null) {
            return $this->Currency;
        }

        return QUI\ERP\Currency\Handler::getDefaultCurrency();
    }

    /**
     * Create the html
     *
     * @return string
     *
     * @throws QUI\Exception
     */
    public function toHTML()
    {
        $Engine   = QUI::getTemplateManager()->getEngine();
        $Currency = $this->getCurrency();

        $article = $this->Article->toArray();
        $calc    = $article['calculated'];

        $this->setAttributes($article);

        $Engine->assign([
            'this'                  => $this,
            'position'              => $this->position,
            'unitPrice'             => $Currency->format($article['unitPrice']),
            'sum'                   => $Currency->format($article['sum']),
            'calculated_basisPrice' => $Currency->format($calc['basisPrice']),
            'calculated_price'      => $Currency->format($calc['price']),
            'calculated_sum'        => $Currency->format($calc['sum']),
            'calculated_nettoSum'   => $Currency->format($calc['nettoSum'])
        ]);

        return $Engine->fetch(dirname(__FILE__).'/ArticleView.html');
    }
}
