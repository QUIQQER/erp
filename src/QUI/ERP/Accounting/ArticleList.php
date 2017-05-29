<?php

/**
 * This file contains QUI\ERP\Accounting\ArticleList
 */

namespace QUI\ERP\Accounting;

use QUI;

/**
 * Class ArticleList
 *
 * @package QUI\ERP\Accounting
 */
class ArticleList extends ArticleListUnique
{
    /**
     * is the article list calculated?
     * @var bool
     */
    protected $calculated = false;

    /**
     * @var int|float|double
     */
    protected $sum;

    /**
     * @var QUI\Interfaces\Users\User
     */
    protected $User = null;

    /**
     * @var int|float|double
     */
    protected $subSum;

    /**
     * @var int|float|double
     */
    protected $nettoSum;

    /**
     * @var int|float|double
     */
    protected $nettoSubSum;

    /**
     * key 19% value[sum] = sum value[text] = text value[display_sum] formatiert
     * @var array
     */
    protected $vatArray = array();

    /**
     * key 19% value[sum] = sum value[text] = text value[display_sum] formatiert
     * @var array()
     */
    protected $vatText;

    /**
     * Prüfen ob EU Vat für den Benutzer in Frage kommt
     * @var
     */
    protected $isEuVat = false;

    /**
     * Wird Brutto oder Netto gerechnet
     * @var bool
     */
    protected $isNetto = true;

    /**
     * Currency information
     * @var array
     */
    protected $currencyData = array(
        'currency_sign' => '',
        'currency_code' => '',
        'user_currency' => '',
        'currency_rate' => ''
    );

    /**
     * ArticleList constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = array())
    {
        if (!isset($attributes['calculations'])) {
            $attributes['calculations'] = array();
        }

        if (!isset($attributes['articles'])) {
            $attributes['articles'] = array();
        }

        parent::__construct($attributes);
    }

    /**
     * Set the user for the list
     * User for calculation
     *
     * @param QUI\Interfaces\Users\User $User
     */
    public function setUser(QUI\Interfaces\Users\User $User)
    {
        $this->calculated = false;
        $this->User       = $User;

        foreach ($this->articles as $Article) {
            /* @var $Article Article */
            $Article->setUser($User);
        }

        $this->calc();
    }

    /**
     * Return the list user
     *
     * @return QUI\Interfaces\Users\User|QUI\Users\User
     */
    public function getUser()
    {
        return $this->User;
    }

    /**
     * Parse this ArticleList to an ArticleListUnique
     *
     * @return ArticleListUnique
     */
    public function toUniqueList()
    {
        $this->calc();

        return new ArticleListUnique($this->toArray());
    }

    /**
     * @param null|Calc $Calc
     * @return $this
     */
    public function calc($Calc = null)
    {
        if ($this->calculated) {
            return $this;
        }

        $self = $this;

        if (!$Calc) {
            $Calc = Calc::getInstance();

            if ($this->User) {
                $Calc->setUser($this->User);
            }
        }

        $Calc->calcArticleList($this, function ($data) use ($self) {
            $self->sum          = $data['sum'];
            $self->subSum       = $data['subSum'];
            $self->nettoSum     = $data['nettoSum'];
            $self->nettoSubSum  = $data['nettoSubSum'];
            $self->vatArray     = $data['vatArray'];
            $self->vatText      = $data['vatText'];
            $self->isEuVat      = $data['isEuVat'];
            $self->isNetto      = $data['isNetto'];
            $self->currencyData = $data['currencyData'];

            $this->calculations = array(
                'sum'          => $self->sum,
                'subSum'       => $self->subSum,
                'nettoSum'     => $self->nettoSum,
                'nettoSubSum'  => $self->nettoSubSum,
                'vatArray'     => $self->vatArray,
                'vatText'      => $self->vatText,
                'isEuVat'      => $self->isEuVat,
                'isNetto'      => $self->isNetto,
                'currencyData' => $self->currencyData
            );

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
    public function addArticle(Article $Article)
    {
        $this->articles[] = $Article;

        if ($this->User) {
            $Article->setUser($this->User);
        }
    }

    /**
     * Remove an article by its index position
     *
     * @param integer $index
     */
    public function removeArticle($index)
    {
        if (isset($this->articles[$index])) {
            unset($this->articles[$index]);
        }
    }

    /**
     * Replace an article at a specific position
     *
     * @param Article $Article
     * @param integer $index
     */
    public function replaceArticle(Article $Article, $index)
    {
        $this->articles[$index] = $Article;
    }

    /**
     * Clears the list
     */
    public function clear()
    {
        $this->articles = array();
    }

    /**
     * Return the length of the list
     *
     * @return int
     */
    public function count()
    {
        return count($this->articles);
    }

    //endregion
}
