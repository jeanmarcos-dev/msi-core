<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Inventory\Test\Unit\Model\SourceItem\Command\Handler;

use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validation\ValidationResult;
use Magento\Framework\Validation\ValidationResultFactory;
use Magento\Inventory\Model\IsProductAssignedToStock\CacheStorage;
use Magento\Inventory\Model\ResourceModel\GetCanonicalSourceCodes;
use Magento\Inventory\Model\ResourceModel\SourceItem\SaveMultiple;
use Magento\Inventory\Model\SourceItem\Command\Handler\SourceItemsSaveHandler;
use Magento\Inventory\Model\SourceItem\Validator\SourceItemsValidator;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SourceItemsSaveHandlerTest extends TestCase
{
    /**
     * @var SourceItemsValidator|Stub
     */
    private $sourceItemsValidator;

    /**
     * @var SaveMultiple|MockObject
     */
    private $saveMultiple;

    /**
     * @var CacheStorage|Stub
     */
    private $cacheStorage;

    /**
     * @var GetCanonicalSourceCodes|Stub
     */
    private $getCanonicalSourceCodes;

    /**
     * @var SourceItemsSaveHandler
     */
    private $handler;

    protected function setUp(): void
    {
        $this->sourceItemsValidator = $this->createStub(SourceItemsValidator::class);
        $this->saveMultiple = $this->createMock(SaveMultiple::class);
        $this->cacheStorage = $this->createStub(CacheStorage::class);
        $this->getCanonicalSourceCodes = $this->createStub(GetCanonicalSourceCodes::class);

        $validationResult = $this->createStub(ValidationResult::class);
        $validationResult->method('isValid')->willReturn(true);
        $this->sourceItemsValidator->method('validate')->willReturn($validationResult);

        $validationResultFactory = $this->createStub(ValidationResultFactory::class);
        $validationResultFactory->method('create')->willReturnCallback(
            static fn (array $data): ValidationResult => new ValidationResult($data['errors'])
        );

        $this->handler = new SourceItemsSaveHandler(
            $this->sourceItemsValidator,
            $this->saveMultiple,
            $this->createStub(LoggerInterface::class),
            $this->cacheStorage,
            $this->getCanonicalSourceCodes,
            $validationResultFactory
        );
    }

    public function testExecuteRejectsSourceCodeThatDiffersFromTheStoredOneOnlyInCase(): void
    {
        $sourceItem = $this->createSourceItemMock('Warehouse_Bravo');
        $sourceItem->expects(self::never())->method('setSourceCode');
        $this->getCanonicalSourceCodes->method('execute')
            ->willReturn(['warehouse_bravo' => 'warehouse_bravo']);
        $this->saveMultiple->expects(self::never())->method('execute');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(
            'Source code "Warehouse_Bravo" does not match the stored source code "warehouse_bravo".'
        );

        $this->handler->execute([$sourceItem]);
    }

    public function testExecuteReportsEachMismatchedSourceCodeOnce(): void
    {
        $this->getCanonicalSourceCodes->method('execute')
            ->willReturn(['warehouse_bravo' => 'warehouse_bravo', 'warehouse_alpha' => 'warehouse_alpha']);
        $this->saveMultiple->expects(self::never())->method('execute');

        try {
            $this->handler->execute([
                $this->createSourceItem('Warehouse_Bravo', 'sku-1'),
                $this->createSourceItem('Warehouse_Bravo', 'sku-2'),
                $this->createSourceItem('WAREHOUSE_ALPHA', 'sku-1'),
                $this->createSourceItem('warehouse_alpha', 'sku-3'),
            ]);
            self::fail('Expected a ValidationException');
        } catch (ValidationException $exception) {
            $errors = array_map(static fn ($error): string => $error->getMessage(), $exception->getErrors());
            self::assertSame(
                [
                    'Source code "Warehouse_Bravo" does not match the stored source code "warehouse_bravo".',
                    'Source code "WAREHOUSE_ALPHA" does not match the stored source code "warehouse_alpha".',
                ],
                $errors
            );
        }
    }

    public function testExecuteSavesSourceCodeThatMatchesTheStoredOne(): void
    {
        $sourceItem = $this->createSourceItemMock('warehouse_bravo');
        $sourceItem->expects(self::never())->method('setSourceCode');
        $this->getCanonicalSourceCodes->method('execute')
            ->willReturn(['warehouse_bravo' => 'warehouse_bravo']);
        $this->saveMultiple->expects(self::once())->method('execute')->with([$sourceItem]);

        $this->handler->execute([$sourceItem]);
    }

    public function testExecuteLeavesUnknownSourceCodeToTheSave(): void
    {
        $sourceItem = $this->createSourceItem('unknown_source');
        $this->getCanonicalSourceCodes->method('execute')->willReturn([]);
        $this->saveMultiple->expects(self::once())->method('execute')->with([$sourceItem]);

        $this->handler->execute([$sourceItem]);
    }

    private function createSourceItem(string $sourceCode, string $sku = 'sku-1'): SourceItemInterface&Stub
    {
        $sourceItem = $this->createStub(SourceItemInterface::class);
        $sourceItem->method('getSourceCode')->willReturn($sourceCode);
        $sourceItem->method('getSku')->willReturn($sku);

        return $sourceItem;
    }

    private function createSourceItemMock(string $sourceCode): SourceItemInterface&MockObject
    {
        $sourceItem = $this->createMock(SourceItemInterface::class);
        $sourceItem->method('getSourceCode')->willReturn($sourceCode);
        $sourceItem->method('getSku')->willReturn('sku-1');

        return $sourceItem;
    }
}
