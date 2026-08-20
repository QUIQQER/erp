<?php

namespace QUITests\ERP\Accounting;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\ArticleListUnique;
use QUI\Interfaces\Template\EngineInterface;
use QUI\Interfaces\Users\User as UserInterface;

class ArticleListUniqueTest extends TestCase
{
    public function testConstructorRequiresArticlesAndCalculationSnapshot(): void
    {
        $this->expectException(QUI\ERP\Exception::class);
        $this->expectExceptionCode(400);

        new ArticleListUnique(['articles' => []]);
    }

    public function testStoredArticlesAreSortedWithParentsBeforeTheirChildren(): void
    {
        $List = new ArticleListUnique([
            'articles' => [
                $this->articleData('child', 'Child', 'parent'),
                $this->articleData('standalone', 'Standalone'),
                $this->articleData('parent', 'Parent'),
                $this->articleData('orphan', 'Orphan', 'missing')
            ],
            'calculations' => []
        ]);

        $articles = $List->toArray()['articles'];

        self::assertSame(['standalone', 'parent', 'child'], array_column($articles, 'uuid'));
        self::assertSame([1.0, 2.0, 2.1], array_column($articles, 'position'));
        self::assertCount(3, $List);
        self::assertCount(3, iterator_to_array($List));
    }

    public function testUnknownAndInvalidStoredArticleClassesFallBackToErpArticle(): void
    {
        $unknown = $this->articleData('unknown', 'Unknown');
        $unknown['class'] = 'Missing\\Article';
        $invalid = $this->articleData('invalid', 'Invalid');
        $invalid['class'] = \stdClass::class;

        $List = new ArticleListUnique([
            'articles' => [$unknown, $invalid],
            'calculations' => []
        ]);

        self::assertContainsOnlyInstancesOf(Article::class, $List->getArticles());
    }

    public function testSerializationRoundTripPreservesObservableSnapshot(): void
    {
        $List = new ArticleListUnique([
            'articles' => [$this->articleData('stored', 'Stored article')],
            'calculations' => ['sum' => 23.8]
        ]);

        $copyFromJson = ArticleListUnique::unserialize($List->serialize());
        $copyFromArray = ArticleListUnique::unserialize($List->toArray());

        self::assertJson($List->toJSON());

        foreach ([$copyFromJson, $copyFromArray] as $Copy) {
            $data = $Copy->toArray();
            self::assertCount(1, $data['articles']);
            self::assertSame('stored', $data['articles'][0]['uuid']);
            self::assertSame(10.0, $data['articles'][0]['unitPrice']);
            self::assertSame(19.0, $data['articles'][0]['vat']);
            self::assertSame(1.0, $data['articles'][0]['position']);
            self::assertSame(11.9, $data['articles'][0]['calculated']['sum']);
            self::assertSame(['sum' => 23.8], $data['calculations']);
        }

        self::assertSame(['sum' => 23.8], $copyFromArray->getCalculations());
        self::assertSame($copyFromArray, $copyFromArray->calc());
        $copyFromArray->recalculate();
        self::assertSame(['sum' => 23.8], $copyFromArray->getCalculations());
    }

    public function testRenderingPublishesHeaderArticlesAndNumericCalculationSnapshot(): void
    {
        $List = $this->calculatedList()->toUniqueList();
        $List->hideHeader();
        $List->setExchangeCurrency(QUI\ERP\Defaults::getCurrency());
        $List->setExchangeRate(1.25);

        $assigned = [];
        $Engine = $this->createMock(EngineInterface::class);
        $Engine->method('assign')->willReturnCallback(
            static function (array $values) use (&$assigned): void {
                $assigned = $values;
            }
        );
        $Engine->method('fetch')->willReturn('<article-list>rendered</article-list>');

        $html = $List->toHTML(__FILE__, false, $Engine);

        self::assertSame('<article-list>rendered</article-list>', $html);
        self::assertFalse($assigned['showHeader']);
        self::assertFalse($assigned['showExchangeRate']);
        self::assertCount(1, $assigned['articles']);
        self::assertIsString($assigned['calculations']['sum']);
        self::assertSame('EUR', $assigned['Currency']->getCode());

        $List->displayHeader();
        self::assertStringContainsString(
            '<article-list>rendered</article-list>',
            $List->toHTMLWithCSS(__FILE__, false, $Engine)
        );
    }

    private function calculatedList(): ArticleList
    {
        $Currency = QUI\ERP\Defaults::getCurrency();
        $User = $this->createMock(UserInterface::class);
        $User->method('getUUID')->willReturn('unique-list-user');
        $User->method('getLocale')->willReturn(QUI::getLocale());
        $User->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => $key === 'RUNTIME_NETTO_BRUTTO_STATUS' ? 1 : null
        );

        $List = new ArticleList();
        $List->setCurrency($Currency);
        $List->setUser($User);
        $List->addArticle(new Article([
            'id' => 31,
            'uuid' => 'rendered',
            'articleNo' => 'RENDER-31',
            'title' => 'Rendered article',
            'unitPrice' => 20,
            'quantity' => 1,
            'vat' => 19
        ]));
        $List->calc();

        return $List;
    }

    /** @return array<string, mixed> */
    private function articleData(string $uuid, string $title, string $parent = ''): array
    {
        return [
            'id' => crc32($uuid),
            'uuid' => $uuid,
            'articleNo' => strtoupper($uuid),
            'title' => $title,
            'unitPrice' => 10,
            'quantity' => 1,
            'vat' => 19,
            'productSetParentUuid' => $parent
        ];
    }
}
