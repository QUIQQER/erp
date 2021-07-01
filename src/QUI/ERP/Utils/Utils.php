<?php

namespace QUI\ERP\Utils;

/**
 * Class Utils
 *
 * General utilities for quiqqer/erp
 */
class Utils
{
    /**
     * Sanitize article description.
     *
     * @param string $description
     * @return string - Sanitized description
     */
    public static function sanitizeArticleDescription(string $description): string
    {
        // Trim
        $description = \trim($description);

        // Filter tag attributes
        $description = \preg_filter('#<([a-z][a-z0-9]*)[^>]*?(\/?)>#i', '<$1$2>', $description);

        // Allow specific tags only
        $description = \strip_tags(
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

        return $description;
    }
}
