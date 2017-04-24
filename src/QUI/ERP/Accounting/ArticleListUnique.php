<?php

/**
 * This file contains QUI\ERP\Accounting\ArticleList
 */

namespace QUI\ERP\Accounting;

/**
 * Class ArticleListUnique
 * Nicht änderbare Artikel Liste
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
     * ArticleList constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
    {
        $articles = $attributes['articles'];

        foreach ($articles as $article) {
            $this->articles[] = new Article($article);
        }
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
            'articles' => $articles
        );
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
}
