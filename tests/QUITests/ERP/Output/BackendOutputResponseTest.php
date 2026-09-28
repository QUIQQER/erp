<?php

namespace QUITests\ERP\Output;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Output\BackendOutputResponse;

class BackendOutputResponseTest extends TestCase
{
    public function testDeniedResponseHasStatusMessageAndSafeFrameNotification(): void
    {
        $Request = QUI::getRequest();
        $originalQuery = $Request->query->all();
        $attack = '</script><script>alert("xss")</script>';

        try {
            $Request->query->set('oid', $attack);
            $Response = BackendOutputResponse::permissionDenied();
        } finally {
            $Request->query->replace($originalQuery);
        }

        self::assertSame(403, $Response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $Response->headers->get('Content-Type'));
        self::assertTrue($Response->headers->hasCacheControlDirective('no-store'));
        $html = $Response->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('<p role="alert">', $html);
        self::assertStringContainsString(
            htmlspecialchars(
                QUI::getLocale()->get('quiqqer/core', 'exception.no.permission'),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ),
            $html
        );
        self::assertStringNotContainsString($attack, $html);
        self::assertStringContainsString('\\u003C', $html);
        self::assertStringContainsString('Messages.addError(', $html);
        self::assertStringContainsString('Control.Loader.hide()', $html);
    }

    public function testArrayControlIdDoesNotBreakPermissionResponse(): void
    {
        $Request = QUI::getRequest();
        $originalQuery = $Request->query->all();

        try {
            $Request->query->set('oid', ['invalid']);
            $Response = BackendOutputResponse::permissionDenied();
        } finally {
            $Request->query->replace($originalQuery);
        }

        self::assertSame(403, $Response->getStatusCode());
        self::assertStringContainsString('QUI.Controls.getById("")', (string)$Response->getContent());
    }
}
