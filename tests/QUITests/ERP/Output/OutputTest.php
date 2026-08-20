<?php

namespace QUITests\ERP\Output;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Config;
use QUI\ERP\Output\Output;
use QUI\ERP\Output\OutputProviderInterface;
use QUI\ERP\Output\OutputTemplate;
use QUI\ERP\Output\OutputTemplateProviderInterface;
use QUI\Interfaces\Template\EngineInterface;
use QUI\Interfaces\Users\User;
use QUI\Locale;
use QUI\Package\Manager;
use QUI\Package\Package;
use QUI\Projects\Project;
use QUI\Projects\Manager as ProjectManager;
use QUI\Rewrite;
use QUI\Template;
use ReflectionMethod;

require_once __DIR__ . '/Fixtures/OutputProviderFixture.php';
require_once __DIR__ . '/Fixtures/OutputTemplateProviderFixture.php';
require_once __DIR__ . '/Fixtures/SystemTemplateProviderFixture.php';
require_once __DIR__ . '/Fixtures/EmptyTemplateProviderFixture.php';
require_once __DIR__ . '/Fixtures/ThrowingOutputProviderFixture.php';
require_once __DIR__ . '/Fixtures/ProjectAwareCustomerFixture.php';
require_once __DIR__ . '/Fixtures/ProjectAwareEntityFixture.php';
require_once __DIR__ . '/Fixtures/ProjectAwareOutputProviderFixture.php';

class OutputTest extends TestCase
{
    private ?Manager $originalPackageManager;
    private ?Template $originalTemplate;
    private ?Locale $originalLocale;
    private ?QUI\Events\Manager $originalEvents;
    private ?Rewrite $originalRewrite;
    private ?ProjectManager $originalProjectManager;
    /** @var array<string, array<string, Project>> */
    private array $originalProjectCache;

    protected function setUp(): void
    {
        $this->originalPackageManager = QUI::$PackageManager;
        $this->originalTemplate = QUI::$Template;
        $this->originalLocale = QUI::$Locale;
        $this->originalEvents = QUI::$Events;
        $this->originalRewrite = QUI::$Rewrite;
        $this->originalProjectManager = QUI::$ProjectManager;
        $this->originalProjectCache = ProjectManager::$projects;

        $Locale = $this->createMock(Locale::class);
        $Locale->method('getCurrent')->willReturn('en');
        $Locale->method('get')->willReturnCallback(
            static fn(string $package, string $key): string => $key
        );
        QUI::$Locale = $Locale;
        OutputProviderFixture::$Locale = $Locale;

        $Engine = $this->createMock(EngineInterface::class);
        $Engine->method('assign');
        $Template = $this->createMock(Template::class);
        $Template->method('getEngine')->willReturn($Engine);
        QUI::$Template = $Template;

        $Events = $this->createMock(QUI\Events\Manager::class);
        QUI::$Events = $Events;

        $this->installProviderPackages();
    }

    protected function tearDown(): void
    {
        QUI::$PackageManager = $this->originalPackageManager;
        QUI::$Template = $this->originalTemplate;
        QUI::$Locale = $this->originalLocale;
        QUI::$Events = $this->originalEvents;
        QUI::$Rewrite = $this->originalRewrite;
        QUI::$ProjectManager = $this->originalProjectManager;
        ProjectManager::$projects = $this->originalProjectCache;
    }

    public function testProviderDiscoveryAndTemplateMetadata(): void
    {
        self::assertSame(OutputProviderFixture::class, Output::getOutputProviderByEntityType('test-document'));
        self::assertSame(OutputProviderFixture::class, Output::getOutputProviderByPackage('vendor/output'));
        self::assertFalse(Output::getOutputProviderByEntityType('missing'));
        self::assertFalse(Output::getOutputProviderByPackage('missing'));

        $TemplateProvider = Output::getOutputTemplateProviderByPackage('vendor/templates');
        self::assertInstanceOf(OutputTemplateProviderFixture::class, $TemplateProvider);
        self::assertNull(Output::getOutputTemplateProviderByPackage('missing'));

        $templates = Output::getTemplates('test-document');
        self::assertSame(['system_default', 'modern'], array_column($templates, 'id'));
        self::assertSame('test-document', $templates[0]['entityType']);
        self::assertTrue($templates[0]['isSystemDefault']);
        self::assertFalse($templates[1]['isSystemDefault']);
        self::assertSame('Test document', $templates[0]['entityTypeTitle']);
    }

    public function testDefaultTemplateConfigurationAndProviderFallback(): void
    {
        self::assertSame([
            'id' => 'system_default',
            'provider' => 'quiqqer/erp-accounting-templates',
            'hideSystemDefault' => false
        ], Output::getDefaultOutputTemplateForEntityType('unknown'));
        self::assertSame(
            SystemTemplateProviderFixture::class,
            Output::getDefaultOutputTemplateProviderForEntityType('test-document')
        );

        $this->installProviderPackages(json_encode([
            'test-document' => ['id' => 'modern', 'provider' => 'vendor/templates']
        ], JSON_THROW_ON_ERROR));
        self::assertSame([
            'id' => 'modern',
            'provider' => 'vendor/templates'
        ], Output::getDefaultOutputTemplateForEntityType('test-document'));
        self::assertSame(
            OutputTemplateProviderFixture::class,
            Output::getDefaultOutputTemplateProviderForEntityType('test-document')
        );
    }

    public function testProviderDiscoverySkipsInvalidOptionalPackageProviders(): void
    {
        $NonQuiqqerPackage = $this->createMock(Package::class);
        $NonQuiqqerPackage->method('isQuiqqerPackage')->willReturn(false);

        $EmptyPackage = $this->createMock(Package::class);
        $EmptyPackage->method('isQuiqqerPackage')->willReturn(true);
        $EmptyPackage->method('getProvider')->willReturn([]);

        $UnknownProviderPackage = $this->createMock(Package::class);
        $UnknownProviderPackage->method('isQuiqqerPackage')->willReturn(true);
        $UnknownProviderPackage->method('getProvider')->willReturn([
            'erpOutput' => ['Vendor\\Missing\\OutputProvider'],
            'erpOutputTemplate' => ['Vendor\\Missing\\TemplateProvider']
        ]);

        $ValidPackage = $this->createMock(Package::class);
        $ValidPackage->method('isQuiqqerPackage')->willReturn(true);
        $ValidPackage->method('getProvider')->willReturn([
            'erpOutput' => [OutputProviderFixture::class],
            'erpOutputTemplate' => [OutputTemplateProviderFixture::class]
        ]);

        $packages = [
            'vendor/non-quiqqer' => $NonQuiqqerPackage,
            'vendor/empty' => $EmptyPackage,
            'vendor/unknown' => $UnknownProviderPackage,
            'vendor/valid' => $ValidPackage
        ];
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalled')->willReturn([
            ['name' => 'vendor/non-quiqqer'],
            ['name' => 'vendor/empty'],
            ['name' => 'vendor/unknown'],
            ['name' => 'vendor/broken'],
            ['name' => 'vendor/valid']
        ]);
        $Manager->method('getInstalledPackage')->willReturnCallback(
            static function (string $name) use ($packages): Package {
                if ($name === 'vendor/broken') {
                    throw new QUI\Exception('Optional package metadata unavailable');
                }

                return $packages[$name];
            }
        );
        QUI::$PackageManager = $Manager;

        self::assertSame(
            OutputProviderFixture::class,
            Output::getOutputProviderByEntityType('test-document')
        );
        self::assertInstanceOf(
            OutputTemplateProviderFixture::class,
            Output::getOutputTemplateProviderByPackage('vendor/valid')
        );
    }

    public function testOutputTemplateRendersObservableEntityDataAndPreviewFooter(): void
    {
        $Template = new OutputTemplate(
            OutputTemplateProviderFixture::class,
            OutputProviderFixture::class,
            42,
            'test-document',
            'missing-template'
        );

        self::assertSame(['id' => 42, 'kind' => 'document'], $Template->getEntity());
        self::assertSame(OutputTemplateProviderFixture::class, $Template->getTemplateProvider());
        self::assertSame('<header>42</header>', $Template->getHTMLHeader());
        self::assertSame('<main>42</main>', $Template->getHTMLBody());

        $html = $Template->getHTML(true);
        self::assertStringContainsString('quiqqer-erp-output-html-header', $html);
        self::assertStringContainsString('<header>42</header>', $html);
        self::assertStringContainsString('<main>42</main>', $html);
        self::assertStringContainsString('<footer>42</footer>', $html);
        self::assertStringContainsString('position: absolute', $html);
        self::assertInstanceOf(EngineInterface::class, $Template->getEngine());
    }

    public function testDocumentFacadeRendersHtmlPdfAndDownloadUrl(): void
    {
        $html = Output::getDocumentHtml(7, 'test-document', null, null, 'modern', true);
        self::assertStringContainsString('<main>7</main>', $html);

        $Document = Output::getDocumentPdf(7, 'test-document', null, null, 'modern');
        self::assertInstanceOf(QUI\HtmlToPdf\Document::class, $Document);
        self::assertSame('document-7.pdf', $Document->options->filename);
        self::assertSame(['id' => 7, 'kind' => 'document'], $Document->getAttribute('Entity'));

        $url = Output::getDocumentPdfDownloadUrl('doc/7', 'test document');
        self::assertStringContainsString('id=doc%2F7', $url);
        self::assertStringContainsString('t=test+document', $url);
    }

    public function testDocumentFacadeRejectsMissingProvidersAndTemplates(): void
    {
        try {
            Output::getDocumentHtml(1, 'missing');
            self::fail('Missing output providers must be rejected.');
        } catch (QUI\Exception $Exception) {
            self::assertStringContainsString('No output provider', $Exception->getMessage());
        }

        $this->expectException(QUI\ERP\Exception::class);
        new OutputTemplate(
            EmptyTemplateProviderFixture::class,
            OutputProviderFixture::class,
            1,
            'test-document'
        );
    }

    public function testMailProjectResolutionFallsBackToRewriteProject(): void
    {
        $fallback = $this->createMock(Project::class);
        $Rewrite = $this->createMock(Rewrite::class);
        $Rewrite->method('getProject')->willReturn($fallback);
        QUI::$Rewrite = $Rewrite;

        $Method = new ReflectionMethod(Output::class, 'getProjectForMail');

        self::assertSame($fallback, $Method->invoke(null, OutputProviderFixture::class, 42));
        self::assertSame($fallback, $Method->invoke(null, ThrowingOutputProviderFixture::class, 42));
    }

    public function testMailProjectResolutionUsesEntityProjectAndSupportedCustomerLanguage(): void
    {
        $currentProject = $this->createMock(Project::class);
        $currentProject->method('getName')->willReturn('current-project');
        $Rewrite = $this->createMock(Rewrite::class);
        $Rewrite->method('getProject')->willReturn($currentProject);
        QUI::$Rewrite = $Rewrite;

        $baseProject = $this->createMock(Project::class);
        $baseProject->method('getLanguages')->willReturn(['de', 'en']);
        $localizedProject = $this->createMock(Project::class);
        ProjectManager::$projects['entity-project'] = [
            '_standard' => $baseProject,
            'de' => $localizedProject
        ];

        $Method = new ReflectionMethod(Output::class, 'getProjectForMail');

        self::assertSame(
            $localizedProject,
            $Method->invoke(null, ProjectAwareOutputProviderFixture::class, 42)
        );
    }

    public function testMailProjectResolutionKeepsEntityProjectWithoutUsableLanguage(): void
    {
        $currentProject = $this->createMock(Project::class);
        $currentProject->method('getName')->willReturn('current-project');
        $Rewrite = $this->createMock(Rewrite::class);
        $Rewrite->method('getProject')->willReturn($currentProject);
        QUI::$Rewrite = $Rewrite;

        $baseProject = $this->createMock(Project::class);
        $baseProject->method('getLanguages')->willReturn(['en']);
        ProjectManager::$projects['entity-project'] = ['_standard' => $baseProject];

        $Method = new ReflectionMethod(Output::class, 'getProjectForMail');

        self::assertSame(
            $baseProject,
            $Method->invoke(null, ProjectAwareOutputProviderFixture::class, 42)
        );
    }

    private function installProviderPackages(string|false $defaultTemplates = false): void
    {
        $Config = $this->createMock(Config::class);
        $Config->method('get')->willReturnCallback(
            static fn(string $section, string $key): mixed =>
                $section === 'output' && $key === 'default_templates' ? $defaultTemplates : false
        );

        $packages = [];
        foreach (
            [
            'vendor/output' => ['erpOutput' => [OutputProviderFixture::class]],
            'quiqqer/erp-accounting-templates' => [
                'erpOutputTemplate' => [SystemTemplateProviderFixture::class]
            ],
            'vendor/templates' => ['erpOutputTemplate' => [OutputTemplateProviderFixture::class]],
            'quiqqer/erp' => [],
            'quiqqer/htmltopdf' => []
            ] as $name => $providers
        ) {
            $Package = $this->createMock(Package::class);
            $Package->method('isQuiqqerPackage')->willReturn(true);
            $Package->method('getProvider')->willReturn($providers);
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
