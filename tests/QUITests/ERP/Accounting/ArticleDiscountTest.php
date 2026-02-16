<?php

namespace QUITests\ERP\Accounting;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\ArticleDiscount;
use QUI\ERP\Accounting\ArticleInterface;
use QUI\ERP\Accounting\ArticleView;
use QUI\ERP\Accounting\Calc;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Money\Price;
use QUI\Interfaces\Users\User as UserInterface;

class ArticleDiscountArticleStub implements ArticleInterface
{
    protected ?UserInterface $User = null;
    protected float $vat = 0.0;

    public function __construct(array $attributes = [])
    {
    }

    public function setUser(UserInterface $User): void
    {
        $this->User = $User;
    }

    public function getUser(): ?UserInterface
    {
        return $this->User;
    }

    public function setVat(float $vat): void
    {
        $this->vat = $vat;
    }

    public function getVat(): float
    {
        return $this->vat;
    }

    public function getView(): ArticleView
    {
        throw new \RuntimeException('Not used in this test');
    }

    public function getTitle(): string
    {
        throw new \RuntimeException('Not used in this test');
    }

    public function getDescription(): string
    {
        throw new \RuntimeException('Not used in this test');
    }

    public function getUnitPrice(): Price
    {
        throw new \RuntimeException('Not used in this test');
    }

    public function getUnitPriceUnRounded(): Price
    {
        throw new \RuntimeException('Not used in this test');
    }

    public function getSum(): Price
    {
        throw new \RuntimeException('Not used in this test');
    }

    public function getQuantity(): bool|int|float
    {
        throw new \RuntimeException('Not used in this test');
    }

    public function toArray(): array
    {
        throw new \RuntimeException('Not used in this test');
    }

    public function displayPrice(): bool
    {
        throw new \RuntimeException('Not used in this test');
    }
}

class ArticleDiscountTest extends TestCase
{
    public function testConstructorFallsBackToComplementForUnknownType(): void
    {
        $Discount = new ArticleDiscount(10.0, 999);

        $this->assertSame(Calc::CALCULATION_COMPLEMENT, $Discount->getDiscountType());
        $this->assertSame(Calc::CALCULATION_COMPLEMENT, $Discount->getCalculation());
    }

    public function testUnserializeHandlesNumericAndPercentage(): void
    {
        $numeric = ArticleDiscount::unserialize('5.99');
        $percentage = ArticleDiscount::unserialize('12.5%');

        $this->assertInstanceOf(ArticleDiscount::class, $numeric);
        $this->assertSame(Calc::CALCULATION_COMPLEMENT, $numeric->getDiscountType());
        $this->assertSame(5.99, $numeric->getValue());

        $this->assertInstanceOf(ArticleDiscount::class, $percentage);
        $this->assertSame(Calc::CALCULATION_PERCENTAGE, $percentage->getDiscountType());
        $this->assertSame(12.5, $percentage->getValue());
    }

    public function testUnserializeHandlesJsonAndInvalidData(): void
    {
        $json = ArticleDiscount::unserialize('{"value":10,"type":1}');
        $invalidJson = ArticleDiscount::unserialize('{invalid json}');
        $missingFields = ArticleDiscount::unserialize('{"foo":"bar"}');

        $this->assertInstanceOf(ArticleDiscount::class, $json);
        $this->assertSame(Calc::CALCULATION_PERCENTAGE, $json->getDiscountType());
        $this->assertSame(10.0, $json->getValue());

        $this->assertNull($invalidJson);
        $this->assertNull($missingFields);
    }

    public function testFormattedReturnsExpectedStrings(): void
    {
        $percentage = new ArticleDiscount(10.0, Calc::CALCULATION_PERCENTAGE);
        $this->assertSame('10%', $percentage->formatted());

        $zero = new ArticleDiscount(0.0, Calc::CALCULATION_COMPLEMENT);
        $this->assertSame('', $zero->formatted());
    }

    public function testFormattedComplementUsesCurrencyFormat(): void
    {
        $Currency = $this->createMock(Currency::class);
        $Currency->expects($this->once())
            ->method('format')
            ->with(10.0)
            ->willReturn('10.00 EUR');

        $Discount = new ArticleDiscount(10.0, Calc::CALCULATION_COMPLEMENT);
        $Discount->setCurrency($Currency);

        $this->assertSame('10.00 EUR', $Discount->formatted());
    }

    public function testFormattedComplementAddsVatForBruttoUserArticle(): void
    {
        $Currency = $this->createMock(Currency::class);
        $Currency->expects($this->once())
            ->method('format')
            ->with($this->callback(static function ($value) {
                return abs($value - 11.9) < 0.0001;
            }))
            ->willReturn('11.90 EUR');
        $Currency->method('toArray')->willReturn(['code' => 'EUR']);

        $User = $this->createMock(UserInterface::class);
        $User->method('getAttribute')->willReturnMap([
            ['RUNTIME_NETTO_BRUTTO_STATUS', 2]
        ]);

        $Article = new ArticleDiscountArticleStub();
        $Article->setUser($User);
        $Article->setVat(19.0);

        $Discount = new ArticleDiscount(10.0, Calc::CALCULATION_COMPLEMENT);
        $Discount->setCurrency($Currency);
        $Discount->setArticle($Article);

        $this->assertSame('11.90 EUR', $Discount->formatted());
    }

    public function testToArrayAndToJson(): void
    {
        $Currency = $this->createMock(Currency::class);
        $Currency->method('toArray')->willReturn(['code' => 'EUR']);
        $Currency->method('format')->willReturn('7.00 EUR');

        $Discount = new ArticleDiscount(7.0, Calc::CALCULATION_COMPLEMENT);
        $Discount->setCurrency($Currency);

        $data = $Discount->toArray();

        $this->assertSame(7.0, $data['value']);
        $this->assertSame(Calc::CALCULATION_COMPLEMENT, $data['type']);
        $this->assertSame('EUR', $data['currency']['code']);
        $this->assertJson($Discount->toJSON());
    }
}
