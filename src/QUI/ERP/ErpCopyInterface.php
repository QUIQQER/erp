<?php

namespace QUI\ERP;

use QUI\Interfaces\Users\User;

/**
 * The ErpCopyInterface
 *
 * When a class implements this interface, it signals that instances of this class have the ability to duplicate its data.
 * This is particularly useful for handling ERP entities that often need to be reused or replicated
 * in different contexts within the same system.
 */
interface ErpCopyInterface
{
    public function copy(
        User $PermissionUser = null,
        bool|string $globalProcessId = false
    ): ErpEntityInterface;
}
