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
     */
    public function __construct($data = array(), User $User)
    {
        $this->User = $User;
        $this->setAttributes($data);
    }
}
