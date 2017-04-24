<?php

/**
 * This file contains QUI\ERP\Accounting\ArticleList
 */

namespace QUI\ERP\Accounting;

/**
 * Class ArticleList
 *
 * @package QUI\ERP\Accounting
 */
class ArticleList extends ArticleListUnique
{
    /**
     * Add an article to the list
     *
     * @param Article $Article
     */
    public function addArticle(Article $Article)
    {
        $this->articles[] = $Article;
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
     * Parse this ArticleList to an ArticleListUnique
     *
     * @return ArticleListUnique
     */
    public function toUniqueList()
    {
        return new ArticleListUnique($this->toArray());
    }
}
