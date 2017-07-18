<?php

/**
 * This file contains QUI\ERP\Address
 */

namespace QUI\ERP;

use QUI;

/**
 * Class Address
 *
 * @package QUI\ERP
 */
class Address extends QUI\Users\Address
{
    /**
     * Address constructor.
     *
     * @param array $data
     * @param User|null $User
     */
    public function __construct($data = array(), $User = null)
    {
        $this->User = $User;
        $this->setAttributes($data);

        if (isset($data['id'])) {
            $this->id = (int)$data['id'];
        }
    }
}
