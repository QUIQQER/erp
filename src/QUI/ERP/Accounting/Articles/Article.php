<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Articles\Article
 */

namespace QUI\ERP\Accounting\Articles;

use QUI;

/**
 * Article
 * An temporary invoice article
 *
 * - Freier Artikel
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Article extends QUI\ERP\Accounting\Article
{
    /**
     * @inheritdoc
     * @return array
     */
    public function toArray(): array
    {
        return \array_merge(parent::toArray(), [
            'class'   => \get_class($this),
            'control' => 'package/quiqqer/erp/bin/backend/controls/articles/Article'
        ]);
    }
}
