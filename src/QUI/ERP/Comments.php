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
        if (!$comments) {
            return;
        }

        foreach ($comments as $comment) {
            if (!isset($comment['id'])) {
                $comment['id'] = QUI\Utils\Uuid::get();
            }

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
     * Comments are empty?
     *
     * @retun bool
     */
    public function isEmpty()
    {
        return empty($this->comments);
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
     * @param string $source - optional, name of the package
     * @param string $sourceIcon - optional, source icon
     * @param string|false $id - optional, comment id, if needed, it will set one
     */
    public function addComment(
        $message,
        $time = false,
        $source = '',
        $sourceIcon = '',
        $id = false
    ) {
        if ($time === false) {
            $time = \time();
        }

        if ($id === false) {
            $id = QUI\Utils\Uuid::get();
        }

        $message = \strip_tags(
            $message,
            '<div><span><pre><p><br><hr>
            <ul><ol><li><dl><dt><dd><strong><em><b><i><u>
            <img><table><tbody><td><tfoot><th><thead><tr>'
        );

        $this->comments[] = [
            'message'    => $message,
            'time'       => (int)$time,
            'source'     => $source,
            'sourceIcon' => $sourceIcon,
            'id'         => $id
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
            if (!isset($comment['source'])) {
                $comment['source'] = '';
            }

            if (!isset($comment['sourceIcon'])) {
                $comment['sourceIcon'] = '';
            }

            if (!isset($comment['id'])) {
                $comment['id'] = false;
            }

            $this->addComment(
                $comment['message'],
                $comment['time'],
                $comment['source'],
                $comment['sourceIcon'],
                $comment['id']
            );
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
            $isEditable = QUI\Permissions\Permission::hasPermission('quiqqer.customer.editComments');
            $json       = \json_decode($User->getAttribute('comments'), true);

            if (!\is_array($json)) {
                $json = [];
            }

            foreach ($json as $key => $entry) {
                if (!empty($json[$key]['id']) && !empty($json[$key]['source'])) {
                    $json[$key]['editable'] = true;
                }

                if ($isEditable === false) {
                    $json[$key]['editable'] = false;
                }
            }

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
