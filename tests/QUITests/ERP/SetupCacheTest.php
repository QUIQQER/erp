<?php

namespace QUITests\ERP;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Cache\Exception as CacheException;
use QUI\Cache\Manager as CacheManager;
use QUI\Config;
use QUI\Events\Event;
use QUI\Events\Manager as EventsManager;
use QUI\System\Output\VoidOutput;
use Stash\Driver\Ephemeral;
use Stash\Pool;

class SetupCacheTest extends TestCase
{
    private ?Config $originalConfig;
    private ?Pool $originalStash;
    private ?EventsManager $originalEvents;
    private bool $originalNoClearing;

    protected function setUp(): void
    {
        $this->originalConfig = CacheManager::$Config;
        $this->originalStash = CacheManager::$Stash;
        $this->originalEvents = QUI::$Events;
        $this->originalNoClearing = CacheManager::$noClearing;

        $Config = $this->createMock(Config::class);
        $Config->method('get')->willReturn(false);
        CacheManager::$Config = $Config;
        CacheManager::$Stash = new Pool(new Ephemeral());
        CacheManager::$noClearing = false;
        QUI::$Events = $this->createMock(EventsManager::class);
    }

    protected function tearDown(): void
    {
        CacheManager::$Config = $this->originalConfig;
        CacheManager::$Stash = $this->originalStash;
        CacheManager::$noClearing = $this->originalNoClearing;
        QUI::$Events = $this->originalEvents;
    }

    public function testSetupCompletionInvalidatesProviderCachesWithoutClearingOtherData(): void
    {
        $providerCaches = [
            'erp/provider/collection',
            'erp/provider/menuItems',
            'erp/provider/menuItems/de'
        ];

        foreach ($providerCaches as $key) {
            CacheManager::set($key, ['stale']);
            self::assertSame(['stale'], CacheManager::get($key));
        }

        CacheManager::set('erp/unrelated', 'keep');
        CacheManager::set('quiqqer/unrelated', 'keep');

        $Document = new DOMDocument();
        self::assertTrue($Document->load(dirname(__DIR__, 3) . '/events.xml'));
        $XPath = new DOMXPath($Document);
        $listeners = $XPath->query('/quiqqer/events/event[@on="onSetupAllEnd"]');
        self::assertNotFalse($listeners);
        self::assertSame(1, $listeners->length);
        $Listener = $listeners->item(0);
        self::assertInstanceOf(DOMElement::class, $Listener);
        $callback = $Listener->getAttribute('fire');
        self::assertIsCallable($callback);

        $Events = new Event();
        $Events->addEvent($Listener->getAttribute('on'), $callback);

        // Setup suppresses clearing while individual packages are imported.
        CacheManager::$noClearing = true;
        CacheManager::clear('erp/provider');
        self::assertSame(['stale'], CacheManager::get('erp/provider/menuItems'));
        CacheManager::$noClearing = false;

        $Events->fireEvent('setupAllEnd', [new VoidOutput()]);
        $Events->fireEvent('setupAllEnd', [new VoidOutput()]);

        foreach ($providerCaches as $key) {
            try {
                CacheManager::get($key);
                self::fail('Provider cache survived setup: ' . $key);
            } catch (CacheException) {
                self::assertTrue(true);
            }
        }

        self::assertSame('keep', CacheManager::get('erp/unrelated'));
        self::assertSame('keep', CacheManager::get('quiqqer/unrelated'));
    }
}
