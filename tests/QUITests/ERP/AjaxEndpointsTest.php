<?php

namespace QUITests\ERP;

use Closure;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Cache\Manager as CacheManager;
use QUI\Config;
use QUI\Controls\Sitemap\Item;
use QUI\Controls\Sitemap\Map;
use QUI\ERP\Api\AbstractErpProvider;
use QUI\ERP\Api\NumberRangeInterface;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Output\OutputProviderInterface;
use QUI\ERP\Output\OutputTemplateProviderInterface;
use QUI\Interfaces\Template\EngineInterface;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Locale;
use QUI\Package\Manager;
use QUI\Package\Package;
use QUI\Template;
use QUI\Users\Address;
use QUI\Users\Manager as UsersManager;
use QUI\Utils\Singleton;
use ReflectionProperty;
use RuntimeException;
use Stash\Interfaces\ItemInterface;
use Stash\Pool;

require_once __DIR__ . '/Fixtures/AjaxFixtureNumberRange.php';
require_once __DIR__ . '/Fixtures/AjaxFixtureProvider.php';
require_once __DIR__ . '/Fixtures/AjaxProcessingStatusFixture.php';
require_once __DIR__ . '/Fixtures/AjaxOutputEntityFixture.php';
require_once __DIR__ . '/Fixtures/AjaxOutputProviderFixture.php';
require_once __DIR__ . '/Fixtures/AjaxOutputTemplateProviderFixture.php';

class AjaxEndpointsTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalCallables;
    /** @var array<string, mixed> */
    private array $originalPermissions;
    private ?Manager $originalPackageManager;
    private ?UsersManager $originalUsers;
    private ?Config $originalCacheConfig;
    private ?Pool $originalStash;
    private ?Template $originalTemplate;
    /** @var array<array-key, mixed> */
    private array $originalSingletonInstances;

    public static function setUpBeforeClass(): void
    {
        foreach (
            [
            'QUI/ERP/Accounting/Invoice/Invoice.php',
            'QUI/ERP/Accounting/Invoice/InvoiceTemporary.php',
            'QUI/ERP/Accounting/Invoice/Handler.php'
            ] as $stubFile
        ) {
            $className = str_replace(['/', '.php'], ['\\', ''], $stubFile);

            if (!class_exists($className, false)) {
                require_once dirname(__DIR__, 2) . '/stubs/' . $stubFile;
            }
        }
    }

    protected function setUp(): void
    {
        QUI::getAjax();
        $this->originalCallables = (new ReflectionProperty(QUI\Ajax::class, 'callables'))->getValue();
        $this->originalPermissions = (new ReflectionProperty(QUI\Ajax::class, 'permissions'))->getValue();
        $this->originalPackageManager = QUI::$PackageManager;
        $this->originalUsers = QUI::$Users;
        $this->originalCacheConfig = CacheManager::$Config;
        $this->originalStash = CacheManager::$Stash;
        $this->originalTemplate = QUI::$Template;
        $this->originalSingletonInstances = (new ReflectionProperty(Singleton::class, 'instances'))->getValue();
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(QUI\Ajax::class, 'callables'))->setValue(null, $this->originalCallables);
        (new ReflectionProperty(QUI\Ajax::class, 'permissions'))->setValue(null, $this->originalPermissions);
        QUI::$PackageManager = $this->originalPackageManager;
        QUI::$Users = $this->originalUsers;
        CacheManager::$Config = $this->originalCacheConfig;
        CacheManager::$Stash = $this->originalStash;
        QUI::$Template = $this->originalTemplate;
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, $this->originalSingletonInstances);
    }

    public function testMoneyParsingAndArticleDescriptionEndpoints(): void
    {
        $validate = $this->endpoint(
            'money/validatePrice.php',
            'package_quiqqer_erp_ajax_money_validatePrice',
            ['value']
        );
        self::assertSame(1234.56, $validate(1234.56));
        self::assertNull($validate('not a price'));

        $sanitize = $this->endpoint(
            'utils/sanitizeArticleDescription.php',
            'package_quiqqer_erp_ajax_utils_sanitizeArticleDescription',
            ['description']
        );
        self::assertSame(
            '<p>Allowed</p>alert(1)',
            $sanitize('<p onclick="evil()">Allowed</p><script>alert(1)</script>')
        );
    }

    public function testBruttoAndNettoEndpointsCalculateExplicitVat(): void
    {
        $brutto = $this->endpoint(
            'calcBruttoPrice.php',
            'package_quiqqer_erp_ajax_calcBruttoPrice',
            ['price', 'formatted', 'vat'],
            false
        );
        self::assertSame(119.0, $brutto(100, false, 19));
        self::assertIsString($brutto(100, true, 19));

        $netto = $this->endpoint(
            'calcNettoPrice.php',
            'package_quiqqer_erp_ajax_calcNettoPrice',
            ['price', 'formatted', 'vat'],
            false
        );
        self::assertSame(100.0, $netto(119, false, 19));
        self::assertSame(0, $netto('', false, 19));
        self::assertIsString($netto(119, true, 19));
    }

    public function testPriceFactorEndpointReturnsNettoBruttoAndSignedDisplay(): void
    {
        $callback = $this->endpoint(
            'calcPriceFactor.php',
            'package_quiqqer_erp_ajax_calcPriceFactor',
            ['price', 'vat', 'currency']
        );
        $result = $callback('10.00', '19', 'EUR');

        self::assertSame(10.0, $result['nettoSum']);
        self::assertEqualsWithDelta(11.9, $result['sum'], 0.00001);
        self::assertStringStartsWith('+', $result['valueText']);

        $this->expectException(QUI\ERP\Exception::class);
        $this->expectExceptionCode(400);
        $callback('invalid', '19', 'EUR');
    }

    public function testProductSummaryAndCalculationExposeCalculatedContracts(): void
    {
        $articleData = [
            'id' => 501,
            'articleNo' => 'AJAX-501',
            'title' => 'Ajax article',
            'description' => 'Observable',
            'unitPrice' => 10,
            'quantity' => 2,
            'vat' => 19
        ];
        $article = json_encode($articleData, JSON_THROW_ON_ERROR);
        $summary = $this->endpoint(
            'products/summary.php',
            'package_quiqqer_erp_ajax_products_summary',
            ['article', 'user']
        );
        $summaryResult = $summary($article, '{}');
        self::assertSame(501, $summaryResult['id']);
        self::assertSame(23.8, $summaryResult['calculated']['sum']);

        $sessionUser = QUI::getUserBySession();
        $originalStatus = $sessionUser->getAttribute('RUNTIME_NETTO_BRUTTO_STATUS');

        try {
            $calculate = $this->endpoint(
                'products/calc.php',
                'package_quiqqer_erp_ajax_products_calc',
                ['articles', 'priceFactors', 'user', 'currency', 'nettoInput']
            );
            $result = $calculate(
                json_encode(['articles' => [$articleData]], JSON_THROW_ON_ERROR),
                '[]',
                '[]',
                'EUR',
                1
            );
        } finally {
            $sessionUser->setAttribute('RUNTIME_NETTO_BRUTTO_STATUS', $originalStatus);
        }

        self::assertSame(23.8, $result['calculations']['sum']);
        self::assertSame(23.8, $result['brutto']['calculations']['sum']);
        self::assertSame(10.0, $result['articles'][0]['calculated']['price']);
        self::assertSame(11.9, $result['brutto']['articles'][0]['calculated']['price']);
        self::assertSame(2, $result['articles'][0]['quantity']);
    }

    public function testProductCalculationHandlesDiscountsPriceFactorsAndInvalidAmounts(): void
    {
        $calculate = $this->endpoint(
            'products/calc.php',
            'package_quiqqer_erp_ajax_products_calc',
            ['articles', 'priceFactors', 'user', 'currency', 'nettoInput']
        );
        $articles = ['articles' => [
            [
                'id' => 601,
                'articleNo' => 'AJAX-DISCOUNT-PERCENT',
                'title' => 'Percentage discount',
                'unitPrice' => 100,
                'quantity' => 2,
                'vat' => 19,
                'discount' => json_encode(['value' => 10, 'type' => 1], JSON_THROW_ON_ERROR)
            ],
            [
                'id' => 602,
                'articleNo' => 'AJAX-DISCOUNT-FIXED',
                'title' => 'Fixed discount',
                'unitPrice' => 50,
                'quantity' => 2,
                'vat' => 19,
                'discount' => json_encode(['value' => 5, 'type' => 2], JSON_THROW_ON_ERROR)
            ]
        ]];
        $priceFactors = [[
            'title' => 'Shipping',
            'description' => '',
            'value' => 10,
            'sum' => '11.90',
            'sumFormatted' => '',
            'nettoSum' => '10.00',
            'nettoSumFormatted' => '',
            'visible' => 1,
            'calculation' => 2,
            'calculation_basis' => 1,
            'vat' => 19
        ]];
        $sessionUser = QUI::getUserBySession();
        $originalStatus = $sessionUser->getAttribute('RUNTIME_NETTO_BRUTTO_STATUS');

        try {
            $result = $calculate(
                json_encode($articles, JSON_THROW_ON_ERROR),
                json_encode($priceFactors, JSON_THROW_ON_ERROR),
                '[]',
                'INVALID-CURRENCY',
                0
            );

            self::assertSame('10%', $result['brutto']['articles'][0]['discount']);
            self::assertIsFloat($result['brutto']['articles'][0]['unitPrice']);
            self::assertIsFloat($result['brutto']['articles'][1]['discount']);
            self::assertGreaterThan(0, $result['brutto']['calculations']['sum']);

            $invalidFactors = $priceFactors;
            $invalidFactors[0]['sum'] = 'not-a-price';

            try {
                $calculate(
                    json_encode($articles, JSON_THROW_ON_ERROR),
                    json_encode($invalidFactors, JSON_THROW_ON_ERROR),
                    '[]',
                    'EUR',
                    1
                );
                self::fail('Invalid localized price-factor amounts must be rejected.');
            } catch (QUI\ERP\Exception $Exception) {
                self::assertSame(400, $Exception->getCode());
                self::assertStringContainsString('Invalid price factor sum', $Exception->getMessage());
            }
        } finally {
            $sessionUser->setAttribute('RUNTIME_NETTO_BRUTTO_STATUS', $originalStatus);
        }
    }

    public function testBankAccountAndInstalledPluginEndpointsUseConfiguredManagers(): void
    {
        $accounts = [10001 => ['id' => 10001, 'title' => 'Main']];
        $Config = $this->createMock(Config::class);
        $Config->method('getSection')->with('bankAccounts')->willReturn([
            'accounts' => json_encode($accounts, JSON_THROW_ON_ERROR)
        ]);
        $Package = $this->createMock(Package::class);
        $Package->method('getConfig')->willReturn($Config);
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->willReturn($Package);
        $Manager->method('isInstalled')->willReturnCallback(
            static fn(string $package): bool => in_array($package, ['quiqqer/order', 'quiqqer/invoice'], true)
        );
        QUI::$PackageManager = $Manager;

        $list = $this->endpoint(
            'settings/bankAccounts/getList.php',
            'package_quiqqer_erp_ajax_settings_bankAccounts_getList',
            []
        );
        self::assertSame($accounts, $list());

        $plugins = $this->endpoint(
            'dashboard/globalProcess/availablePlugins.php',
            'package_quiqqer_erp_ajax_dashboard_globalProcess_availablePlugins',
            [],
            ['Permission::checkAdminUser']
        );
        self::assertSame(['quiqqer/invoice', 'quiqqer/order'], $plugins());
    }

    public function testUserEmailAndDeliveryAddressEndpointsDeduplicateData(): void
    {
        $Address = $this->createMock(Address::class);
        $Address->method('getMailList')->willReturn(['billing@example.test', 'shared@example.test']);
        $Address->method('getAttributes')->willReturn(['id' => 77, 'city' => 'Berlin']);
        $User = $this->createMock(QUI\Users\User::class);
        $User->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                'email' => 'shared@example.test',
                'quiqqer.delivery.address' => 77,
                default => null
            }
        );
        $User->method('getAddressList')->willReturn([$Address]);
        $User->method('getAddress')->with(77)->willReturn($Address);
        $Users = $this->createMock(UsersManager::class);
        $Users->method('get')->with(42)->willReturn($User);
        QUI::$Users = $Users;

        $emails = $this->endpoint(
            'userData/getUserEmailAddresses.php',
            'package_quiqqer_erp_ajax_userData_getUserEmailAddresses',
            ['userId']
        );
        self::assertSame(['shared@example.test', 'billing@example.test'], $emails(42));

        $delivery = $this->endpoint(
            'userData/getDeliveryAddress.php',
            'package_quiqqer_erp_ajax_userData_getDeliveryAddress',
            ['userId']
        );
        self::assertSame(['id' => 77, 'city' => 'Berlin'], $delivery(42));
    }

    public function testMoneyFormattingAndCurrencyEndpointsExposeAdminDisplayContract(): void
    {
        $format = $this->endpoint(
            'money/formatPrice.php',
            'package_quiqqer_erp_ajax_money_formatPrice',
            ['price', 'language']
        );
        $SystemLocale = QUI::getSystemLocale();
        $originalLanguage = $SystemLocale->getCurrent();

        try {
            self::assertIsString($format('1234.50', 'en'));
            self::assertIsString($format('invalid', 'en'));
        } finally {
            $SystemLocale->setCurrent($originalLanguage);
        }

        $getCurrency = $this->endpoint(
            'money/getCurrency.php',
            'package_quiqqer_erp_ajax_money_getCurrency',
            []
        );
        $currency = $getCurrency();

        self::assertSame('EUR', $currency['code']);
        self::assertArrayHasKey('sign', $currency);
    }

    public function testManufacturerGroupEndpointUsesOnlyConfiguredGroupWithoutProducts(): void
    {
        $Config = $this->createMock(Config::class);
        $Config->method('get')->with('manufacturers', 'groupId')->willReturn('712');
        $Package = $this->createMock(Package::class);
        $Package->method('getConfig')->willReturn($Config);
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->willReturn($Package);
        $Manager->method('isInstalled')->with('quiqqer/products')->willReturn(false);
        QUI::$PackageManager = $Manager;

        $callback = $this->endpoint(
            'manufacturers/getGroupIds.php',
            'package_quiqqer_erp_ajax_manufacturers_getGroupIds',
            []
        );

        self::assertSame([712], $callback());
    }

    public function testCoordinatorEndpointsExposeMenusMailTextsAndNumberRanges(): void
    {
        $Config = $this->createMock(Config::class);
        $Config->method('get')->willReturn(false);
        CacheManager::$Config = $Config;

        $providerItem = $this->createMock(ItemInterface::class);
        $providerItem->method('get')->willReturn([AjaxFixtureProvider::class]);
        $providerItem->method('isMiss')->willReturn(false);
        $menuItem = $this->createMock(ItemInterface::class);
        $menuItem->method('get')->willReturn([
            'items' => [[
                'name' => 'erp-fixture',
                'text' => ['quiqqer/erp', 'fixture'],
                'items' => []
            ]]
        ]);
        $menuItem->method('isMiss')->willReturn(false);
        $Stash = $this->createMock(Pool::class);
        $Stash->method('getItem')->willReturnCallback(
            static fn(string $key): ItemInterface => str_contains($key, 'menuItems') ? $menuItem : $providerItem
        );
        CacheManager::$Stash = $Stash;

        $mail = $this->endpoint(
            'settings/mail/getMailTextProvider.php',
            'package_quiqqer_erp_ajax_settings_mail_getMailTextProvider',
            []
        );
        self::assertSame([['title' => 'Fixture mail']], $mail());

        $ranges = $this->endpoint(
            'settings/numberRanges/list.php',
            'package_quiqqer_erp_ajax_settings_numberRanges_list',
            []
        );
        self::assertSame([[
            'title' => 'Fixture range',
            'range' => 700,
            'class' => AjaxFixtureNumberRange::class
        ]], $ranges());

        AjaxFixtureNumberRange::$lastSetRange = null;
        $setRange = $this->endpoint(
            'settings/numberRanges/set.php',
            'package_quiqqer_erp_ajax_settings_numberRanges_set',
            ['className', 'newIndex']
        );
        $setRange(AjaxFixtureNumberRange::class, '845');
        self::assertSame(845, AjaxFixtureNumberRange::$lastSetRange);

        $panel = $this->endpoint(
            'panel/list.php',
            'package_quiqqer_erp_ajax_panel_list',
            []
        );
        self::assertSame('erp-fixture', $panel()['items'][0]['name']);
    }

    public function testOutputEndpointsExposeMailTemplatePreviewAndEntityContracts(): void
    {
        $this->installOutputPackages();
        $Engine = $this->createMock(EngineInterface::class);
        $Engine->method('assign');
        $Template = $this->createMock(Template::class);
        $Template->method('getEngine')->willReturn($Engine);
        QUI::$Template = $Template;

        $mailData = $this->endpoint(
            'output/getMailData.php',
            'package_quiqqer_erp_ajax_output_getMailData',
            ['entityId', 'entityType']
        );
        self::assertSame([
            'subject' => 'Ajax document 84',
            'content' => 'Mail body for 84'
        ], $mailData(84, 'ajax-document'));
        self::assertSame(['subject' => '', 'content' => ''], $mailData(84, 'missing-document'));

        $templates = $this->endpoint(
            'output/getTemplates.php',
            'package_quiqqer_erp_ajax_output_getTemplates',
            ['entityType']
        );
        $templateList = $templates('ajax-document');
        self::assertCount(1, $templateList);
        self::assertSame('ajax-layout', $templateList[0]['id']);
        self::assertSame('vendor/ajax-templates', $templateList[0]['provider']);

        $preview = $this->endpoint(
            'output/getPreview.php',
            'package_quiqqer_erp_ajax_output_getPreview',
            ['entity', 'template']
        );
        self::assertSame('', $preview('{"id":84,"type":"ajax-document"}', '{}'));
        self::assertStringContainsString(
            '<main>84</main>',
            $preview(
                '{"id":84,"type":"ajax-document"}',
                '{"id":"ajax-layout","provider":"vendor/ajax-templates"}'
            )
        );

        $entityData = $this->endpoint(
            'output/getEntityData.php',
            'package_quiqqer_erp_ajax_output_getEntityData',
            ['entityId', 'entityType', 'entityPlugin']
        );
        self::assertSame([
            'email' => 'ajax-recipient@example.test',
            'hideSystemDefaultTemplate' => true,
            'uuid' => 'ajax-output-84',
            'prefixedNumber' => 'AJAX-84'
        ], $entityData(84, 'ajax-document', 'fixture/unsupported'));
        self::assertFalse($entityData(84, 'missing-document', 'fixture/unsupported'));
    }

    public function testDeprecatedProcessInformationEndpointReturnsSafeEmptyResult(): void
    {
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->willThrowException(new QUI\Exception('not installed'));
        QUI::$PackageManager = $Manager;

        $processInformation = $this->endpoint(
            'getProcessInformation.php',
            'package_quiqqer_erp_ajax_getProcessInformation',
            ['hash']
        );

        self::assertSame([], $processInformation('missing-process'));
    }

    public function testProcessEndpointsUseSelectedDatabase(): void
    {
        $originalConnection = QUI::getDataBaseConnection();
        $usesCiDatabase = DatabaseEnvironment::usesCiDatabase();
        $Connection = $usesCiDatabase
            ? $originalConnection
            : DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $processTable = QUI::getDBTableName('process');
        $firstProcessId = 'ajax-process-' . bin2hex(random_bytes(8));
        $secondProcessId = 'ajax-process-' . bin2hex(random_bytes(8));

        if ($usesCiDatabase) {
            if ($Connection->isTransactionActive()) {
                throw new RuntimeException('ERP Ajax CI tests require a connection without a transaction.');
            }

            $Connection->beginTransaction();
        } else {
            $Connection->executeStatement(
                'CREATE TABLE ' . $Connection->quoteIdentifier($processTable)
                . " (id TEXT PRIMARY KEY, history TEXT DEFAULT '')"
            );
        }

        $Manager = $this->createMock(Manager::class);
        $Manager->method('isInstalled')->willReturn(false);
        QUI::$PackageManager = $Manager;

        try {
            (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(null, $Connection);

            $process = $this->endpoint(
                'process/getProcess.php',
                'package_quiqqer_erp_ajax_process_getProcess',
                ['globalProcessId', 'hash'],
                ['Permission::checkAdminUser']
            );
            $result = $process($firstProcessId, '');
            self::assertSame($firstProcessId, $result['globalProcessId']);
            self::assertCount(1, $result['history']);

            $dashboardProcess = $this->endpoint(
                'dashboard/globalProcess/getProcess.php',
                'package_quiqqer_erp_ajax_dashboard_globalProcess_getProcess',
                ['globalProcessId'],
                ['Permission::checkAdminUser']
            );
            self::assertCount(1, $dashboardProcess($secondProcessId)['history']);

            $processList = $this->endpoint(
                'dashboard/globalProcess/getList.php',
                'package_quiqqer_erp_ajax_dashboard_globalProcess_getList',
                [],
                ['Permission::checkAdminUser']
            );
            self::assertSame([], $processList());
            self::assertSame(2, (int)$Connection->fetchOne(
                'SELECT COUNT(*) FROM ' . $Connection->quoteIdentifier($processTable) . ' WHERE id IN (?, ?)',
                [$firstProcessId, $secondProcessId]
            ));
        } finally {
            (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(null, $originalConnection);

            if ($usesCiDatabase) {
                if (!$Connection->isTransactionActive()) {
                    throw new RuntimeException('The ERP Ajax CI transaction ended before cleanup.');
                }

                $Connection->rollBack();
            } else {
                $Connection->close();
            }
        }
    }

    public function testProcessEntitiesEndpointExposesDefaultAndResolvedProcessingStatus(): void
    {
        $WithoutStatus = $this->createMock(Invoice::class);
        $WithoutStatus->method('toArray')->willReturn([
            'uuid' => 'invoice-without-status',
            'type' => 'invoice'
        ]);

        $WithStatus = $this->createMock(Invoice::class);
        $WithStatus->method('toArray')->willReturn([
            'uuid' => 'invoice-with-status',
            'type' => 'invoice'
        ]);
        $WithStatus->method('getProcessingStatus')->willReturn(new AjaxProcessingStatusFixture());

        $Manager = $this->createMock(Manager::class);
        $Manager->method('isInstalled')->willReturnCallback(
            static fn(string $package): bool => $package === 'quiqqer/invoice'
        );
        QUI::$PackageManager = $Manager;

        $originalInvoices = InvoiceHandler::$invoicesByProcessId;
        InvoiceHandler::$invoicesByProcessId = [
            'ajax-entity-process' => [$WithoutStatus, $WithStatus]
        ];

        try {
            $getEntities = $this->endpoint(
                'process/getEntities.php',
                'package_quiqqer_erp_ajax_process_getEntities',
                ['globalProcessId', 'entityHash'],
                ['Permission::checkAdminUser']
            );
            $result = $getEntities('ajax-entity-process', '');
        } finally {
            InvoiceHandler::$invoicesByProcessId = $originalInvoices;
        }

        self::assertSame([
            'id' => 0,
            'title' => '---',
            'color' => '#999999'
        ], $result[0]['processing_status']);
        self::assertSame([
            'id' => 17,
            'title' => 'Ready for export',
            'color' => '#227744'
        ], $result[1]['processing_status']);
    }

    public function testSendMailEndpointRejectsMissingTemplateProviderBeforeSending(): void
    {
        $this->installOutputPackages(false);
        $sendMail = $this->endpoint(
            'output/sendMail.php',
            'package_quiqqer_erp_ajax_output_sendMail',
            [
                'entityId',
                'entityType',
                'template',
                'templateProvider',
                'mailRecipient',
                'mailSubject',
                'mailContent',
                'mailAttachmentMediaFileIds'
            ]
        );

        $this->expectException(QUI\ERP\Exception::class);
        $this->expectExceptionMessage('ERP output template provider is not available');
        $sendMail(84, 'ajax-document', '', 'missing/provider', '', '', '', '[]');
    }

    public function testFrontendBusinessTypeWindowIsHiddenForLoggedInUsers(): void
    {
        $User = $this->createMock(QUI\Users\User::class);
        $Users = $this->createMock(UsersManager::class);
        $Users->method('getUserBySession')->willReturn($User);
        QUI::$Users = $Users;
        $showWindow = $this->endpoint(
            'frontend/showB2BB2CWindow.php',
            'package_quiqqer_erp_ajax_frontend_showB2BB2CWindow',
            [],
            false
        );

        self::assertFalse($showWindow());
    }

    public function testEntityAndCustomerFileEndpointsUseResolvedInvoiceContract(): void
    {
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getUUID')->willReturn('customer-990');
        $Copy = $this->createMock(Invoice::class);
        $Copy->method('toArray')->willReturn(['uuid' => 'invoice-copy']);

        $addedFiles = [];
        $copyProcessIds = [];
        $Entity = $this->createMock(Invoice::class);
        $Entity->method('toArray')->willReturn(['uuid' => 'invoice-990', 'type' => 'invoice']);
        $Entity->method('getCustomer')->willReturn($Customer);
        $Entity->method('getCustomerFiles')->with(true)->willReturn([
            ['hash' => 'stored-file', 'options' => ['attachToEmail' => true]]
        ]);
        $Entity->method('getGlobalProcessId')->willReturn('process-990');
        $Entity->method('addCustomerFile')->willReturnCallback(
            static function (string $fileHash) use (&$addedFiles): void {
                $addedFiles[] = $fileHash;
            }
        );
        $Entity->expects(self::once())
            ->method('setCustomFiles')
            ->with([['hash' => 'stored-file', 'options' => ['attachToEmail' => false]]]);
        $Entity->method('copy')->willReturnCallback(
            static function (?UserInterface $PermissionUser, bool|string $processId) use (&$copyProcessIds, $Copy): Invoice {
                $copyProcessIds[] = $processId;
                return $Copy;
            }
        );

        $Invoices = $this->createMock(InvoiceHandler::class);
        $Invoices->method('getInvoiceByHash')->with('invoice-990')->willReturn($Entity);
        $instances = $this->originalSingletonInstances;
        $instances[InvoiceHandler::class] = $Invoices;
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, $instances);

        $getEntity = $this->endpoint(
            'getEntity.php',
            'package_quiqqer_erp_ajax_getEntity',
            ['uuid', 'entityPlugin']
        );
        self::assertSame(
            ['uuid' => 'invoice-990', 'type' => 'invoice'],
            $getEntity('invoice-990', 'quiqqer/invoice')
        );

        $getType = $this->endpoint(
            'getEntityType.php',
            'package_quiqqer_erp_ajax_getEntityType',
            ['uuid']
        );
        self::assertSame(get_class($Entity), $getType('invoice-990'));

        $getTitle = $this->endpoint(
            'getEntityTitle.php',
            'package_quiqqer_erp_ajax_getEntityTitle',
            ['uuid']
        );
        self::assertIsString($getTitle('invoice-990'));

        $addFile = $this->endpoint(
            'customerFiles/addFile.php',
            'package_quiqqer_erp_ajax_customerFiles_addFile',
            ['hash', 'fileHash'],
            false
        );
        $addFile('invoice-990', 'file-a');

        $addFiles = $this->endpoint(
            'customerFiles/addFiles.php',
            'package_quiqqer_erp_ajax_customerFiles_addFiles',
            ['hash', 'fileHashes'],
            false
        );
        $addFiles('invoice-990', '["file-b","file-c"]');
        self::assertSame(['file-a', 'file-b', 'file-c'], $addedFiles);

        $getCustomer = $this->endpoint(
            'customerFiles/getCustomer.php',
            'package_quiqqer_erp_ajax_customerFiles_getCustomer',
            ['hash'],
            false
        );
        self::assertSame('customer-990', $getCustomer('invoice-990'));

        $getFiles = $this->endpoint(
            'customerFiles/getFiles.php',
            'package_quiqqer_erp_ajax_customerFiles_getFiles',
            ['hash'],
            false
        );
        self::assertSame('stored-file', $getFiles('invoice-990')[0]['hash']);

        $update = $this->endpoint(
            'customerFiles/update.php',
            'package_quiqqer_erp_ajax_customerFiles_update',
            ['hash', 'files'],
            false
        );
        $update('invoice-990', '[{"hash":"stored-file","options":{"attachToEmail":false}}]');

        $copyEntity = $this->endpoint(
            'copyEntity.php',
            'package_quiqqer_erp_ajax_copyEntity',
            ['uuid', 'processKeepStatus', 'entityPlugin']
        );
        self::assertSame(['uuid' => 'invoice-copy'], $copyEntity('invoice-990', 'existing', 'quiqqer/invoice'));
        self::assertSame(['uuid' => 'invoice-copy'], $copyEntity('invoice-990', 'new', 'quiqqer/invoice'));
        self::assertSame(['process-990', false], $copyProcessIds);
    }

    /** @param list<string> $parameters */
    private function endpoint(
        string $file,
        string $name,
        array $parameters,
        bool|string|array $permission = 'Permission::checkAdminUser'
    ): Closure {
        require dirname(__DIR__, 3) . '/ajax/' . $file;

        $callables = QUI\Ajax::getRegisteredCallables();
        self::assertArrayHasKey($name, $callables);
        self::assertSame($parameters, $callables[$name]['params']);
        self::assertInstanceOf(Closure::class, $callables[$name]['callable']);

        $permissions = (new ReflectionProperty(QUI\Ajax::class, 'permissions'))->getValue();
        if ($permission === false) {
            self::assertArrayNotHasKey($name, $permissions);
        } else {
            self::assertSame($permission, $permissions[$name]);
        }

        return $callables[$name]['callable'];
    }

    private function installOutputPackages(bool $withTemplates = true): void
    {
        $Config = $this->createMock(Config::class);
        $Config->method('get')->willReturnCallback(
            static fn(string $section, string $key): mixed =>
                $section === 'output' && $key === 'default_templates'
                    ? json_encode([
                        'ajax-document' => [
                            'id' => 'ajax-layout',
                            'provider' => 'vendor/ajax-templates',
                            'hideSystemDefault' => true
                        ]
                    ], JSON_THROW_ON_ERROR)
                    : false
        );

        $providers = [
            'vendor/ajax-output' => ['erpOutput' => [AjaxOutputProviderFixture::class]],
            'quiqqer/erp' => []
        ];

        if ($withTemplates) {
            $providers['vendor/ajax-templates'] = [
                'erpOutputTemplate' => [AjaxOutputTemplateProviderFixture::class]
            ];
        }
        $packages = [];

        foreach ($providers as $name => $packageProviders) {
            $Package = $this->createMock(Package::class);
            $Package->method('isQuiqqerPackage')->willReturn(true);
            $Package->method('getProvider')->willReturn($packageProviders);
            $Package->method('getConfig')->willReturn($Config);
            $packages[$name] = $Package;
        }

        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalled')->willReturn(array_map(
            static fn(string $name): array => ['name' => $name],
            array_keys($packages)
        ));
        $Manager->method('getInstalledPackage')->willReturnCallback(
            static fn(string $name): Package => $packages[$name]
        );
        QUI::$PackageManager = $Manager;
    }
}
