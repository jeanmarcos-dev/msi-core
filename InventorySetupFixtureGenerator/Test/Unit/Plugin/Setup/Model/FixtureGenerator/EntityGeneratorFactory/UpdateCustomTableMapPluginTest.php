<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySetupFixtureGenerator\Test\Unit\Plugin\Setup\Model\FixtureGenerator\EntityGeneratorFactory;

use Magento\InventorySetupFixtureGenerator\Plugin\Setup\Model\FixtureGenerator as FixtureGeneratorPlugin;
use Magento\Setup\Model\FixtureGenerator\EntityGenerator;
use Magento\Setup\Model\FixtureGenerator\EntityGeneratorFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpdateCustomTableMapPluginTest extends TestCase
{
    private const ADDED_SKU_BOUND_TABLES = [
        'inventory_stock_item_configuration',
        'inventory_stock_1',
        'inventory_source_item_adjustment',
    ];

    /**
     * @var FixtureGeneratorPlugin\EntityGeneratorFactory\UpdateCustomTableMapPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->plugin = new FixtureGeneratorPlugin\EntityGeneratorFactory\UpdateCustomTableMapPlugin();
    }

    public function testMapsEveryInventoryTableWithoutEntityIdBinding(): void
    {
        $map = $this->customTableMap();

        foreach (array_merge(['inventory_source_item'], self::ADDED_SKU_BOUND_TABLES) as $table) {
            self::assertArrayHasKey($table, $map);
            self::assertSame(EntityGenerator::SKIP_ENTITY_ID_BINDING, $map[$table]['entity_id_field'], $table);
            self::assertIsCallable($map[$table]['handler'], $table);
        }
    }

    public function testKeepsTheCustomTablesThatAreAlreadyMapped(): void
    {
        $subject = $this->createStub(EntityGeneratorFactory::class);
        $existing = ['entity_id_field' => 'entity_id', 'handler' => null];

        [$data] = $this->plugin->beforeCreate($subject, ['customTableMap' => ['url_rewrite' => $existing]]);

        self::assertSame($existing, $data['customTableMap']['url_rewrite']);
    }

    #[DataProvider('addedSkuBoundTablesProvider')]
    public function testBindsTheRowToTheSkuOfTheGeneratedProduct(string $table): void
    {
        $handler = $this->customTableMap()[$table]['handler'];

        $binds = $handler(11, 4, $this->fixture(), [['sku' => 'template_sku', 'quantity' => 5]]);

        self::assertSame([['sku' => 'generated-4', 'quantity' => 5]], $binds);
    }

    #[DataProvider('addedSkuBoundTablesProvider')]
    public function testKeepsOneRowPerGeneratedSku(string $table): void
    {
        $handler = $this->customTableMap()[$table]['handler'];

        $first = $handler(11, 4, $this->fixture(), [['sku' => 'template_a'], ['sku' => 'template_b']]);
        $repeated = $handler(11, 4, $this->fixture(), [['sku' => 'template_a']]);
        $next = $handler(12, 5, $this->fixture(), [['sku' => 'template_a']]);

        self::assertSame([['sku' => 'generated-4']], $first);
        self::assertSame([], $repeated);
        self::assertSame([['sku' => 'generated-5']], $next);
    }

    #[DataProvider('addedSkuBoundTablesProvider')]
    public function testYieldsNoRowWhenTheTemplateWroteNone(string $table): void
    {
        $handler = $this->customTableMap()[$table]['handler'];

        self::assertSame([], $handler(11, 4, $this->fixture(), []));
    }

    public function testTracksProcessedSkusPerTable(): void
    {
        $map = $this->customTableMap();

        $binds = [['sku' => 'a']];
        $configuration = $map['inventory_stock_item_configuration']['handler'](11, 4, $this->fixture(), $binds);
        $index = $map['inventory_stock_1']['handler'](11, 4, $this->fixture(), $binds);

        self::assertSame([['sku' => 'generated-4']], $configuration);
        self::assertSame([['sku' => 'generated-4']], $index);
    }

    public function testTracksProcessedSkusPerGenerator(): void
    {
        $first = $this->customTableMap()['inventory_stock_1']['handler'];
        $second = $this->customTableMap()['inventory_stock_1']['handler'];

        self::assertSame([['sku' => 'generated-4']], $first(11, 4, $this->fixture(), [['sku' => 'a']]));
        self::assertSame([['sku' => 'generated-4']], $second(11, 4, $this->fixture(), [['sku' => 'a']]));
    }

    public static function addedSkuBoundTablesProvider(): array
    {
        return array_combine(
            self::ADDED_SKU_BOUND_TABLES,
            array_map(static fn (string $table): array => [$table], self::ADDED_SKU_BOUND_TABLES)
        );
    }

    private function customTableMap(): array
    {
        $subject = $this->createStub(EntityGeneratorFactory::class);

        return $this->plugin->beforeCreate($subject, [])[0]['customTableMap'];
    }

    private function fixture(): array
    {
        return [
            'sku' => static function ($productId, $entityNumber) {
                return 'generated-' . $entityNumber;
            },
        ];
    }
}
