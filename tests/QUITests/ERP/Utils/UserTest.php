<?php

namespace QUITests\ERP\Utils;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Utils\User;
use QUI\Interfaces\Users\User as UserInterface;

class UserTest extends TestCase
{
    public function testIsNettoUserTrueWhenRuntimeStatusIsSet(): void
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getAttribute')->willReturnMap([
            ['RUNTIME_NETTO_BRUTTO_STATUS', User::IS_NETTO_USER]
        ]);

        $this->assertTrue(User::isNettoUser($User));
    }

    public function testIsNettoUserFalseWhenRuntimeStatusIsBrutto(): void
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getAttribute')->willReturnMap([
            ['RUNTIME_NETTO_BRUTTO_STATUS', User::IS_BRUTTO_USER]
        ]);

        $this->assertFalse(User::isNettoUser($User));
    }
}
