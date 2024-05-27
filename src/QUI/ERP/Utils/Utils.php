<?php

namespace QUI\ERP\Utils;

use function preg_replace;
use function strip_tags;
use function trim;

/**
 * Class Utils
 *
 * General utilities for quiqqer/erp
 */
class Utils
{
    public static array $entityIcons = [
        'QUI\ERP\Order\Order' => 'fa-shopping-basket',
        'QUI\ERP\Accounting\Invoice\InvoiceTemporary' => 'fa-file-text-o',
        'QUI\ERP\Accounting\Invoice\Invoice' => 'fa-file-text-o',
        'QUI\ERP\SalesOrders\SalesOrder' => 'fa-suitcase',
        'QUI\ERP\Accounting\Offers\Offer' => 'fa-file-text-o',
        'QUI\ERP\Accounting\Offers\OfferTemporary' => 'fa-file-text-o',
    ];

    /**
     * Sanitize article description.
     *
     * @param string $description
     * @return string - Sanitized description
     */
    public static function sanitizeArticleDescription(string $description): string
    {
        // Trim
        $description = trim($description);

        // Filter tag attributes
        $description = preg_replace('#<([a-z][a-z0-9]*)[^>]*?(\/?)>#i', '<$1$2>', $description);

        // Allow specific tags only
        return strip_tags(
            $description,
            [
                '<br>',
                '<b>',
                '<i>',
                '<pre>',
                '<u>',
                '<em>',
                '<strong>',
                '<li>',
                '<ul>',
                '<ol>',
                '<blockquote>',
                '<del>',
                '<hr>',
                '<p>',
                '<sup>',
                '<sub>'
            ]
        );
    }

    public static function getEntityIcon($className): string
    {
        if (isset(self::$entityIcons[$className])) {
            return self::$entityIcons[$className];
        }

        return '';
    }
}
