<?php

namespace QUITests\ERP\Utils;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Utils\Utils;

class UtilsTest extends TestCase
{
    public function testSanitizeArticleDescription(): void
    {
        $input = ' <p class="x">Hello <script>alert(1)</script><b style="color:red">World</b></p> ';
        $result = Utils::sanitizeArticleDescription($input);

        $this->assertSame('<p>Hello alert(1)<b>World</b></p>', $result);
    }

    public function testGetEntityIconReturnsKnownAndUnknownIcon(): void
    {
        $this->assertSame('fa-shopping-basket', Utils::getEntityIcon('QUI\ERP\Order\Order'));
        $this->assertSame('', Utils::getEntityIcon('Unknown\\Class\\Name'));
    }
}
