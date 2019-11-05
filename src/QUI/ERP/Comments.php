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
 * - transaction comments
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
        if (\is_string($data)) {
            $data = \json_decode($data, true);
        }

        if (!\is_array($data)) {
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
        return \json_encode($this->toArray());
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
            $time = \time();
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

    /**
     * Sort all comments via its time
     */
    public function sort()
    {
        \usort($this->comments, function ($commentA, $commentB) {
            if ($commentA['time'] == $commentB['time']) {
                return 0;
            }

            return ($commentA['time'] < $commentB['time']) ? -1 : 1;
        });
    }

    /**
     * Import another Comments object into this Comments Object
     *
     * @param Comments $Comments
     */
    public function import(Comments $Comments)
    {
        $comments = $Comments->toArray();

        foreach ($comments as $comment) {
            $this->addComment($comment['message'], $comment['time']);
        }

        $this->sort();
    }

    // region utils

    /**
     * @param QUI\Users\User $User
     * @return Comments
     *
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     */
    public static function getCommentsByUser(QUI\Users\User $User)
    {
        $Comments = null;

        if ($User->getAttribute('comments')) {
            $json = \json_decode($User->getAttribute('comments'), true);

            if (\is_array($json)) {
                $Comments = new self($json);
            }
        }

        if ($Comments === null) {
            $Comments = new self();
        }


        QUI::getEvents()->fireEvent(
            'quiqqerErpGetCommentsByUser',
            [$User, $Comments]
        );

        $Comments->sort();

        return $Comments;
    }

    // endregion
}
