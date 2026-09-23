<?php

namespace QUITests\ERP\Accounting;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\ArticleListUnique;
use QUI\Locale;

class ArticleListLocaleTest extends TestCase
{
    private string $originalLanguage;

    protected function setUp(): void
    {
        $this->originalLanguage = QUI::getLocale()->getCurrent();
        QUI::getLocale()->setCurrent('de');
    }

    protected function tearDown(): void
    {
        QUI::getLocale()->setCurrent($this->originalLanguage);
    }

    public static function taxContexts(): iterable
    {
        yield 'net prices' => [true, false, 19.5, 'message.vat.text.netto'];
        yield 'gross prices' => [false, false, 19.5, 'message.vat.text.brutto'];
        yield 'net EU VAT' => [true, true, 0, 'message.vat.text.netto.EUVAT'];
        yield 'gross EU VAT' => [false, true, 0, 'message.vat.text.brutto.EUVAT'];
    }

    #[DataProvider('taxContexts')]
    public function testMailAndHtmlUseTheTargetLanguageAndPreserveTheStoredTaxContext(
        bool $net,
        bool $euVat,
        float $rate,
        string $key
    ): void {
        $snapshot = $this->snapshot();
        $snapshot['calculations']['isNetto'] = $net;
        $snapshot['calculations']['isEuVat'] = $euVat;
        $savedText = QUI::getLocale()->get('quiqqer/tax', $key, ['vat' => $rate]);
        $snapshot['calculations']['vatArray'] = [(string)$rate => [
            'vat' => $rate, 'sum' => $euVat ? 0 : 1.95, 'text' => $savedText
        ]];
        $snapshot['calculations']['vatText'] = [(string)$rate => $savedText];
        // Deliberately omit the customer: display must depend on the saved context only.
        $List = new ArticleListUnique($snapshot);
        $before = $List->serialize();

        foreach (['en', 'de', 'en'] as $language) {
            $Locale = $this->locale($language);
            $List->setLocale($Locale);

            foreach ([$List->renderForMail(), $List->toHTML()] as $html) {
                self::assertStringContainsString($Locale->get('quiqqer/tax', $key, ['vat' => $rate]), $html);
                self::assertStringContainsString(
                    $Locale->get('quiqqer/erp', 'article.list.articles.subtotal'),
                    $html
                );
                self::assertStringContainsString('Gespeicherter Artikel', $html);
                self::assertSame('de', QUI::getLocale()->getCurrent());
                self::assertSame($before, $List->serialize());
            }
        }
    }

    public function testMailConversionRetainsTheExplicitListLocale(): void
    {
        $English = $this->locale('en');
        $List = new ArticleList($this->snapshot());
        $List->setLocale($English);

        self::assertStringContainsString(
            $English->get('quiqqer/erp', 'article.list.articles.subtotal'),
            $List->renderForMail()
        );
        self::assertStringContainsString(
            $English->get('quiqqer/erp', 'article.list.articles.sumtotal'),
            $List->toUniqueList()->renderForMail()
        );
    }

    public function testCustomFieldDisplayValuesAndRowLabelsUseTheListLocale(): void
    {
        $English = $this->locale('en');
        $snapshot = $this->snapshot();
        $snapshot['articles'][0]['customFields'] = [[
            'title' => 'Option',
            'custom_calc' => [
                'value' => 0,
                'valueText' => ['de' => 'Deutsche Auswahl', 'en' => 'English selection']
            ]
        ]];
        $List = new ArticleListUnique($snapshot);
        $List->setLocale($English);
        $before = $List->serialize();

        foreach ([$List->toHTML(), $List->renderForMail()] as $html) {
            self::assertStringContainsString('English selection', $html);
            self::assertStringNotContainsString('Deutsche Auswahl', $html);
            self::assertStringContainsString(
                $English->get('quiqqer/erp', 'article.list.articles.header.articleNo'),
                $html
            );
        }

        self::assertSame($before, $List->serialize());
    }

    public function testLegacySnapshotWithoutTaxContextKeepsItsStoredLabel(): void
    {
        $snapshot = $this->snapshot();
        unset($snapshot['calculations']['isNetto'], $snapshot['calculations']['isEuVat']);
        $snapshot['calculations']['vatArray']['19.5']['text'] = 'Legacy tax explanation';
        $List = new ArticleListUnique($snapshot);
        $List->setLocale($this->locale('en'));

        self::assertStringContainsString('Legacy tax explanation', $List->renderForMail());
    }

    private function locale(string $language): Locale
    {
        $Locale = new Locale();
        $Locale->setCurrent($language);
        return $Locale;
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'articles' => [[
                'id' => 158,
                'uuid' => 'language-snapshot',
                'articleNo' => 'LANG-158',
                'title' => 'Gespeicherter Artikel',
                'description' => 'Gespeicherte Beschreibung',
                'unitPrice' => 10,
                'quantity' => 1,
                'vat' => 19.5,
                'calculated' => [
                    'price' => 10.0, 'basisPrice' => 10.0, 'nettoPriceNotRounded' => 10.0,
                    'sum' => 11.95, 'nettoPrice' => 10.0, 'nettoBasisPrice' => 10.0,
                    'nettoSubSum' => 10.0, 'nettoSum' => 10.0,
                    'vatArray' => ['vat' => 19.5, 'sum' => 1.95],
                    'isEuVat' => false, 'isNetto' => true
                ]
            ]],
            'calculations' => [
                'sum' => 11.95, 'subSum' => 10.0, 'grandSubSum' => 11.95,
                'nettoSum' => 10.0, 'nettoSubSum' => 10.0,
                'vatArray' => ['19.5' => ['vat' => 19.5, 'sum' => 1.95, 'text' => 'zzgl. 19.5% MwSt.']],
                'vatText' => ['19.5' => 'zzgl. 19.5% MwSt.'],
                'isNetto' => true, 'isEuVat' => false,
                'currencyData' => QUI\ERP\Defaults::getCurrency()->toArray()
            ]
        ];
    }
}
