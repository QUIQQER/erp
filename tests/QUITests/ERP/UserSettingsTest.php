<?php

namespace QUITests\ERP;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use QUI\Utils\XML\Settings;

class UserSettingsTest extends TestCase
{
    public function testCustomerCategoryRendersWithoutLegacyTabsOrTemplates(): void
    {
        $file = dirname(__DIR__, 3) . '/user.xml';
        $Document = new DOMDocument();
        self::assertTrue($Document->load($file));
        $Path = new DOMXPath($Document);
        self::assertSame(0.0, $Path->evaluate('count(//window/tab | //template)'));
        self::assertSame(7.0, $Path->evaluate('count(/quiqqer/user/attributes/attribute)'));
        self::assertSame(1.0, $Path->evaluate('count(//window/categories/category[@name="ERP"])'));

        $Settings = new Settings();
        $Settings->setXMLPath('//user/window');
        $html = $Settings->getCategoriesHtml([$file], 'ERP');
        $Document->loadHTML('<meta charset="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $Path = new DOMXPath($Document);
        $Button = $Path->query('//button')->item(0);
        self::assertInstanceOf(DOMElement::class, $Button);
        self::assertSame('button', $Button->getAttribute('type'));
        self::assertSame(
            'qui/controls/buttons/Button',
            $Button->getAttribute('data-qui')
        );
        self::assertSame('open-customer', $Button->getAttribute('data-name'));
        self::assertSame(
            \QUI::getLocale()->get('quiqqer/erp', 'user.settings.customer.button'),
            trim($Button->textContent)
        );
        self::assertStringNotContainsString('[quiqqer/erp]', $Button->textContent);
        self::assertStringContainsString(
            'package/quiqqer/erp/bin/backend/controls/ErpUserData',
            $Button->getAttribute('data-qui-options-onclick')
        );
    }
}
