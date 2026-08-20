<?php

namespace QUITests\ERP;

use QUI\Interfaces\Users\User as UserInterface;
use QUI\Users\Manager as UsersManager;
use QUI\Users\SystemUser;

class ManufacturerUsersManagerFixture extends UsersManager
{
    public bool|string $createdUsername = false;
    public ?UserInterface $creatingUser = null;

    public function __construct(
        private SystemUser $SystemUser,
        private UserInterface $Manufacturer,
        private bool $usernameExists = false
    ) {
    }

    public function usernameExists(string $username): bool
    {
        return $this->usernameExists;
    }

    public function getSystemUser(): SystemUser
    {
        return $this->SystemUser;
    }

    public function createChild(
        bool|string $username = false,
        ?UserInterface $ParentUser = null
    ): UserInterface {
        $this->createdUsername = $username;
        $this->creatingUser = $ParentUser;

        return $this->Manufacturer;
    }
}
