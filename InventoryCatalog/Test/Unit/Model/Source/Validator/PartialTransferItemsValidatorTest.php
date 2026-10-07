<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model\Source\Validator;

use Magento\Framework\Validation\ValidationResult;
use Magento\Framework\Validation\ValidationResultFactory;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryCatalog\Model\GetSourceItemsBySkuAndSourceCodes;
use Magento\InventoryCatalog\Model\Source\Validator\PartialTransferItemsValidator;
use Magento\InventoryCatalogApi\Api\Data\PartialInventoryTransferItemInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PartialTransferItemsValidatorTest extends TestCase
{
    /**
     * @var GetSourceItemsBySkuAndSourceCodes|MockObject
     */
    private $getSourceItems;

    /**
     * @var array
     */
    private $quantities = [];

    /**
     * @var PartialTransferItemsValidator
     */
    private $model;

    protected function setUp(): void
    {
        $this->getSourceItems = $this->createMock(GetSourceItemsBySkuAndSourceCodes::class);
        $this->getSourceItems->method('execute')->willReturnCallback(
            function (string $sku, array $sourceCodes) {
                $sourceCode = $sourceCodes[0];
                if (!isset($this->quantities[$sourceCode][$sku])) {
                    return [];
                }
                $sourceItem = $this->createMock(SourceItemInterface::class);
                $sourceItem->method('getQuantity')->willReturn($this->quantities[$sourceCode][$sku]);
                return [$sourceItem];
            }
        );

        $validationResultFactory = $this->createMock(ValidationResultFactory::class);
        $validationResultFactory->method('create')
            ->willReturnCallback(fn (array $data) => new ValidationResult($data['errors']));

        $this->model = new PartialTransferItemsValidator($validationResultFactory, $this->getSourceItems);
    }

    public function testAcceptsATransferCoveredByTheOrigin(): void
    {
        $this->quantities = ['slr_a' => ['SKU-1' => 3.0], 'slr_b' => ['SKU-1' => 3.0]];

        $result = $this->model->validate('slr_a', 'slr_b', [$this->item('SKU-1', 3.0)]);

        self::assertTrue($result->isValid());
    }

    public function testRejectsARepeatedSkuWhoseTotalExceedsTheOrigin(): void
    {
        $this->quantities = ['slr_a' => ['SKU-1' => 3.0], 'slr_b' => ['SKU-1' => 3.0]];

        $result = $this->model->validate('slr_a', 'slr_b', [$this->item('SKU-1', 3.0), $this->item('SKU-1', 3.0)]);

        self::assertSame(
            ['Requested transfer amount for sku SKU-1 is not available'],
            $this->messages($result)
        );
    }

    /**
     * @dataProvider nonPositiveQuantities
     */
    public function testRejectsANonPositiveQuantity(float $qty): void
    {
        $this->quantities = ['slr_a' => ['SKU-1' => 3.0], 'slr_b' => ['SKU-1' => 3.0]];

        $result = $this->model->validate('slr_a', 'slr_b', [$this->item('SKU-1', $qty)]);

        self::assertSame(
            ['Transfer quantity for sku SKU-1 must be greater than zero'],
            $this->messages($result)
        );
    }

    public static function nonPositiveQuantities(): array
    {
        return ['zero' => [0.0], 'negative' => [-5.0]];
    }

    public function testRejectsAMissingDestinationSourceItem(): void
    {
        $this->quantities = ['slr_a' => ['SKU-1' => 3.0]];

        $result = $this->model->validate('slr_a', 'slr_b', [$this->item('SKU-1', 1.0)]);

        self::assertSame(['Source item for SKU-1 and slr_b does not exist'], $this->messages($result));
    }

    public function testKeepsNumericSkusAsStrings(): void
    {
        $this->quantities = ['slr_a' => ['123' => 3.0], 'slr_b' => ['123' => 0.0]];

        $result = $this->model->validate('slr_a', 'slr_b', [$this->item('123', 2.0)]);

        self::assertTrue($result->isValid());
    }

    private function item(string $sku, float $qty): PartialInventoryTransferItemInterface
    {
        $item = $this->createMock(PartialInventoryTransferItemInterface::class);
        $item->method('getSku')->willReturn($sku);
        $item->method('getQty')->willReturn($qty);
        return $item;
    }

    private function messages(ValidationResult $result): array
    {
        return array_map(fn ($error) => (string)$error, $result->getErrors());
    }
}
