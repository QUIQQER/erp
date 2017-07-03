<?php

/**
 * This file contains QUI\ERP\Accounting\ArticleList
 */

namespace QUI\ERP\Accounting;

use QUI;

/**
 * Class ArticleListUnique
 * - Nicht änderbare Artikel Liste
 *
 * @package QUI\ERP\Accounting
 */
class ArticleListUnique
{
    /**
     * @var array
     */
    protected $articles = array();

    /**
     * @var array
     */
    protected $calculations = array();

    /**
     * ArticleList constructor.
     *
     * @param array $attributes
     * @throws QUI\ERP\Exception
     */
    public function __construct($attributes = array())
    {
        $needles = array('articles', 'calculations');

        foreach ($needles as $needle) {
            if (!isset($attributes[$needle])) {
                throw new QUI\ERP\Exception(
                    'Missing needle for ArticleListUnique',
                    400,
                    array(
                        'class'   => 'ArticleListUnique',
                        'missing' => $needle
                    )
                );
            }
        }

        $articles = $attributes['articles'];

        foreach ($articles as $article) {
            $this->articles[] = new Article($article);
        }

        $this->calculations = $attributes['calculations'];
    }

    /**
     * Creates a list from a stored representation
     *
     * @param string $data
     * @return ArticleListUnique
     */
    public static function unserialize($data)
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
    public function serialize()
    {
        return json_encode($this->toArray());
    }

    /**
     * Return the calculation array
     *
     * @return array
     */
    public function getCalculations()
    {
        return $this->calculations;
    }

    /**
     * Return the list articles
     *
     * @return array
     */
    public function getArticles()
    {
        return $this->articles;
    }

    /**
     * Generates a storable json representation of the list
     * Alias for serialize()
     *
     * @return string
     */
    public function toJSON()
    {
        return $this->serialize();
    }

    /**
     * Return the list as an array
     *
     * @return array
     */
    public function toArray()
    {
        $articles = array_map(function ($Article) {
            /* @var $Article Article */
            return $Article->toArray();
        }, $this->articles);

        return array(
            'articles'     => $articles,
            'calculations' => $this->calculations
        );
    }

    /**
     * Return the Article List as HTML, without CSS
     *
     * @param string|bool $template - custom template
     * @return string
     */
    public function toHTML($template = false)
    {
        $Engine   = QUI::getTemplateManager()->getEngine();
        $vatArray = array();

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

        $articles = array_map(function ($Article) use ($Currency, &$pos) {
            /* @var $Article Article */
            $View = $Article->getView();
            $View->setCurrency($Currency);
            $View->setPosition($pos);

            $pos++;

            return $View;
        }, $this->articles);

        // output
        $Engine->assign(array(
            'this'         => $this,
            'articles'     => $articles,
            'calculations' => $this->calculations,
            'vatArray'     => $vatArray
        ));

        if ($template && file_exists($template)) {
            return $Engine->fetch($template);
        }

        return $Engine->fetch(dirname(__FILE__) . '/ArticleList.html');
    }

    /**
     * Return the Article List as HTML, with CSS
     *
     * @return string
     */
    public function toHTMLWithCSS()
    {
        $style = '<style>';
        $style .= file_get_contents(dirname(__FILE__) . '/ArticleList.css');
        $style .= '</style>';

        return $style . $this->toHTML();
    }
}
