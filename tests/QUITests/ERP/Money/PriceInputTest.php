<?php

namespace QUITests\ERP\Money;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\ArticleDiscount;
use QUI\ERP\Exception;
use QUI\ERP\Money\Price;

class PriceInputTest extends TestCase
{
    public static function localizedPrices(): iterable
    {
        yield ['de-DE', '1.234,56', 1234.56];
        yield ['de-DE', '4.622', 4622.0];
        yield ['de-DE', '5.5', 5.5];
        yield ['de-DE', '0,5', 0.5];
        yield ['de-DE', '5.99 €', 5.99];
        yield ['de-DE', '5,50 €', 5.5];
        yield ['en-US', '$1,234.56', 1234.56];
        yield ['en-US', '1,234.56', 1234.56];
        yield ['en-US', '4.622', 4.622];
        yield ['en-GB', '1,234.56', 1234.56];
        yield ['de-CH', '1’234.56', 1234.56];
        yield ['fr-FR', '1 234,56', 1234.56];
        yield ['fr-FR', '1 234,56', 1234.56];
        yield ['hi-IN', '12,34,567.89', 1234567.89];
        yield ['ar-EG', '١٬٢٣٤٫٥٦', 1234.56];
        yield ['en-US', '𝟙𝟚𝟛.𝟜𝟝', 123.45];
        yield ['de-DE', '-5,5', -5.5];
        yield ['en-US', '0.5', 0.5];
        yield ['de-DE', '0', 0.0];
        yield ['en-US', '', null];
    }

    #[DataProvider('localizedPrices')]
    public function testExplicitLocaleOverridesSystemLanguage(string $locale, string $input, ?float $expected): void
    {
        $SystemLocale = QUI::getSystemLocale();
        $originalLanguage = $SystemLocale->getCurrent();

        try {
            foreach (['en', 'de'] as $systemLanguage) {
                $SystemLocale->setCurrent($systemLanguage);
                $payload = json_encode(['value' => $input, 'locale' => $locale], JSON_THROW_ON_ERROR);
                self::assertSame($expected, Price::parsePriceInput($payload));
            }
        } finally {
            $SystemLocale->setCurrent($originalLanguage);
        }
    }

    public function testJsonNumbersAreNeverReadAsLocalizedStrings(): void
    {
        foreach (['de-DE', 'en-US', 'de-CH', 'fr-FR'] as $locale) {
            foreach ([4.622, 0.5, -5.5, 0] as $value) {
                $payload = json_encode(['value' => $value, 'locale' => $locale], JSON_THROW_ON_ERROR);
                self::assertSame((float)$value, Price::parsePriceInput($payload));
            }
        }

        self::assertSame(1.23456789, Price::parsePriceInput('{"value":1.234567891,"locale":"en-US"}'));
    }

    public static function invalidInput(): iterable
    {
        foreach ([true, false, [], new \stdClass(), INF, -INF, NAN, 1.0e100] as $input) {
            yield [$input];
        }

        foreach ([true, null, [], '12abc', '12,34,56', 'NaN', 'Infinity', '1e999', '5,5'] as $value) {
            yield [json_encode(['value' => $value, 'locale' => 'en-US'], JSON_THROW_ON_ERROR)];
        }

        yield ['{"value":1e999,"locale":"en-US"}'];
        yield ['{"value":1,"locale":[]}'];
        yield ['{"value":1,"locale":"invalid!"}'];
        yield ['{"locale":"en-US"}'];
        yield ['{invalid json}'];
        yield ['€'];
        yield ['$5€'];

        foreach (
            [
                ['de-CH', '1’234.56abc'],
                ['fr-FR', '1 234,56abc'],
                ['fr-FR', '1 234,56abc'],
                ['ar-EG', '١٬٢٣٤٫٥٦abc'],
                ['en-US', '١a'],
                ['en-US', '𝟙𝟚𝟛.𝟜𝟝abc'],
                ['en-US', '123💶']
            ] as [$locale, $value]
        ) {
            yield [json_encode(['value' => $value, 'locale' => $locale], JSON_THROW_ON_ERROR)];
        }
    }

    #[DataProvider('invalidInput')]
    public function testInvalidInputIsRejectedInsteadOfPartiallyParsed(mixed $input): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        Price::parsePriceInput($input);
    }

    public static function invalidArticleAmounts(): iterable
    {
        foreach ([true, [], '12oops', '4,622', '1e999', INF, NAN] as $value) {
            foreach (['unitPrice', 'nettoPriceNotRounded', 'discount'] as $field) {
                // A bare discount string is legacy localized input, not a canonical amount.
                $input = $field === 'discount' && is_string($value)
                    ? json_encode(['value' => $value, 'type' => 2], JSON_THROW_ON_ERROR)
                    : $value;
                yield [[$field => $input]];
            }
        }
    }

    #[DataProvider('invalidArticleAmounts')]
    public function testArticleRejectsTamperedAmountsWithoutCallingValidationEndpoint(array $attributes): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        new Article($attributes);
    }

    public function testPercentageDiscountIsCanonicalAfterInputValidation(): void
    {
        $value = Price::parsePriceInput('{"value":"5,5","locale":"de-DE"}');
        self::assertSame(5.5, ArticleDiscount::unserialize($value . '%')?->getValue());
    }

    public function testMalformedPercentageIsRejected(): void
    {
        $this->expectException(Exception::class);
        ArticleDiscount::unserialize('5%5');
    }
}
