<?php

/**
 * This file contains QUI\ERP\Comments
 */

namespace QUI\ERP;

use QUI;

/**
 * Class Comments
 * - Invoice comments
 * - order comments
 *
 * Helper class to manage comment arrays
 *
 * @package QUI\ERP
 */
class Comments
{
    /**
     * @var array
     */
    protected $comments = [];

    /**
     * Comments constructor.
     *
     * @param array $comments
     */
    public function __construct($comments = [])
    {
        foreach ($comments as $comment) {
            if (isset($comment['message']) && isset($comment['time'])) {
                $this->comments[] = $comment;
            }
        }
    }

    /**
     * Creates a comment list from a stored representation
     *
     * @param string $data
     * @return Comments
     */
    public static function unserialize($data)
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (!is_array($data)) {
            return new self();
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
        return $this->comments;
    }

    /**
     * Add a comment
     *
     * @param string $message
     * @param int|false $time - optional, unix timestamp
     */
    public function addComment($message, $time = false)
    {
        if ($time === false) {
            $time = time();
        }

        $message = QUI\Utils\Security\Orthos::clearFormRequest($message);

        $this->comments[] = [
            'message' => $message,
            'time'    => (int)$time
        ];
    }

    /**
     * Clear all comments
     */
    public function clear()
    {
        $this->comments = [];
    }
}
