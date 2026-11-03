<?php

namespace QUITests\ERP;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Product\Types\AbstractType;
use QUI\ERP\Products\Product\Types\VariantParent;
use ReflectionProperty;

class VariantGridAjaxTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalCallables;

    /** @var array<string, mixed> */
    private array $originalPermissions;

    /** @var array<int, AbstractType> */
    private array $originalProducts;

    public static function setUpBeforeClass(): void
    {
        foreach (
            [
                AbstractType::class,
                VariantParent::class,
                Products::class
            ] as $className
        ) {
            if (!class_exists($className)) {
                $stubFile = str_replace('\\', '/', $className) . '.php';
                require dirname(__DIR__, 2) . '/stubs/' . $stubFile;
            }
        }
    }

    protected function setUp(): void
    {
        QUI::getAjax();
        $this->originalCallables = (new ReflectionProperty(QUI\Ajax::class, 'callables'))->getValue();
        $this->originalPermissions = (new ReflectionProperty(QUI\Ajax::class, 'permissions'))->getValue();
        $this->originalProducts = (new ReflectionProperty(Products::class, 'list'))->getValue();
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(QUI\Ajax::class, 'callables'))->setValue(null, $this->originalCallables);
        (new ReflectionProperty(QUI\Ajax::class, 'permissions'))->setValue(null, $this->originalPermissions);
        (new ReflectionProperty(Products::class, 'list'))->setValue(null, $this->originalProducts);
    }

    public function testVariantSelectionEnrichesAllRowsWithTheSharedEvent(): void
    {
        $rows = [
            [
                'id' => 31,
                'title' => 'First',
                'fields' => []
            ],
            [
                'id' => 32,
                'title' => 'Second',
                'fields' => []
            ],
            [
                'id' => 33,
                'title' => 'Third',
                'fields' => []
            ]
        ];
        $children = [];

        foreach ($rows as $row) {
            $Child = $this->createMock(AbstractType::class);
            $Child->method('getAttributes')->willReturn($row);
            $children[] = $Child;
        }

        $Parent = $this->createMock(VariantParent::class);
        $Parent->method('getVariants')->willReturn($children);
        (new ReflectionProperty(Products::class, 'list'))->setValue(null, [21 => $Parent]);
        $calls = 0;
        $Enrich = static function (
            array &$eventRows,
            array $ids,
            VariantParent $EventParent,
            string $context
        ) use (
            $rows,
            $Parent,
            &$calls
        ): void {
            self::assertSame($rows, $eventRows);
            self::assertSame([31, 32, 33], $ids);
            self::assertSame($Parent, $EventParent);
            self::assertSame('erp.variant-selection', $context);
            ++$calls;

            foreach ([0, -1.25, 12.3456] as $index => $value) {
                $eventRows[$index]['packageQuantity'] = $value;
            }
        };
        $Events = QUI::getEvents();
        $Events->addEvent('onQuiqqerProductsVariantGridData', $Enrich);

        try {
            $result = $this->invoke(21);
            self::assertSame([0, -1.25, 12.3456], array_column($result, 'packageQuantity'));
            self::assertSame([31, 32, 33], array_column($result, 'id'));
            self::assertSame(1, $calls);
        } finally {
            $Events->removeEvent('onQuiqqerProductsVariantGridData', $Enrich);
        }

        self::assertSame($rows, $this->invoke(21));
    }

    public function testEmptyVariantSelectionDispatchesTheEventButOrdinaryProductsDoNot(): void
    {
        $Parent = $this->createMock(VariantParent::class);
        $Parent->method('getVariants')->willReturn([]);
        $Ordinary = $this->createMock(AbstractType::class);
        (new ReflectionProperty(Products::class, 'list'))->setValue(null, [
            21 => $Parent,
            22 => $Ordinary
        ]);
        $calls = 0;
        $Enrich = static function (array &$rows, array $ids) use (&$calls): void {
            self::assertSame([], $rows);
            self::assertSame([], $ids);
            ++$calls;
        };
        $Events = QUI::getEvents();
        $Events->addEvent('onQuiqqerProductsVariantGridData', $Enrich);

        try {
            self::assertSame([], $this->invoke(21));
            self::assertSame([], $this->invoke(22));
            self::assertSame(1, $calls);
        } finally {
            $Events->removeEvent('onQuiqqerProductsVariantGridData', $Enrich);
        }
    }

    /** @return array<mixed> */
    private function invoke(int $productId): array
    {
        require dirname(__DIR__, 3) . '/ajax/products/getVariantChildren.php';

        $name = 'package_quiqqer_erp_ajax_products_getVariantChildren';
        $callables = QUI\Ajax::getRegisteredCallables();
        $permissions = (new ReflectionProperty(QUI\Ajax::class, 'permissions'))->getValue();
        self::assertSame('Permission::checkAdminUser', $permissions[$name]);

        return $callables[$name]['callable']($productId);
    }
}
