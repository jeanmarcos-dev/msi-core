<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model\Indexer;

use Magento\CatalogInventory\Model\Indexer\Stock as LegacyStockIndexer;
use Magento\Framework\Indexer\ActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\InventoryCatalog\Model\Indexer\InertStockIndexer;
use PHPUnit\Framework\TestCase;

class InertStockIndexerTest extends TestCase
{
    /**
     * @var InertStockIndexer
     */
    private $model;

    protected function setUp(): void
    {
        $this->model = new InertStockIndexer();
    }

    public function testItSatisfiesBothContractsOfTheIndexerItReplaces(): void
    {
        self::assertInstanceOf(ActionInterface::class, $this->model);
        self::assertInstanceOf(MviewActionInterface::class, $this->model);
    }

    /**
     * The di.xml preference names a concrete class, and consumers such as BulkSourceUnassign type-hint
     * that class rather than the two interfaces, so standing in for it means being of its type.
     */
    public function testItIsOfTheTypeItReplaces(): void
    {
        self::assertInstanceOf(LegacyStockIndexer::class, $this->model);
    }

    /**
     * A collaborator would mean the indexer still reaches the legacy tables, which is what this class exists
     * to prevent, so taking no constructor arguments is part of the contract — the more so now that the
     * parent declares three legacy actions this class must not build.
     */
    public function testItHasNoCollaborators(): void
    {
        $constructor = (new \ReflectionClass(InertStockIndexer::class))->getConstructor();
        self::assertSame(0, $constructor->getNumberOfParameters());
    }

    public function testEveryEntryPointOfTheLegacyIndexerIsCovered(): void
    {
        foreach (['execute', 'executeFull', 'executeList', 'executeRow'] as $method) {
            self::assertTrue(
                method_exists($this->model, $method),
                sprintf('%s does not implement %s()', InertStockIndexer::class, $method)
            );
            self::assertTrue(
                method_exists(LegacyStockIndexer::class, $method),
                sprintf('%s no longer declares %s()', LegacyStockIndexer::class, $method)
            );
        }
    }

    public function testExecutingItIsASilentNoOp(): void
    {
        $this->expectNotToPerformAssertions();

        $this->model->execute([1, 2]);
        $this->model->executeFull();
        $this->model->executeList([1, 2]);
        $this->model->executeRow(1);
    }
}
