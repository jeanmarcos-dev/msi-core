<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventorySales\Model\ResourceModel\AcquireStockItemLocks;
use Magento\InventorySales\Model\ResourceModel\InventoryLockName;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test the deadlock-safe lock-name ordering of AcquireStockItemLocks.
 */
class AcquireStockItemLocksTest extends TestCase
{
    /**
     * @var AcquireStockItemLocks
     */
    private $model;

    /**
     * @var GetSourcesAssignedToStockOrderedByPriorityInterface|MockObject
     */
    private $getSourcesMock;

    /**
     * @var InventoryLockName
     */
    private InventoryLockName $lockName;

    /**
     * @var array
     */
    private array $queries = [];

    /**
     * @var array<string,bool>
     */
    private array $refused = [];

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->getSourcesMock = $this->createMock(GetSourcesAssignedToStockOrderedByPriorityInterface::class);
        $deploymentConfig = $this->createStub(DeploymentConfig::class);
        $deploymentConfig->method('get')->willReturn('shop');
        $this->lockName = new InventoryLockName($deploymentConfig);
        $this->model = new AcquireStockItemLocks(
            $this->createMock(ResourceConnection::class),
            $this->getSourcesMock,
            $this->lockName
        );
    }

    /**
     * One lock name per (sku, enabled source); disabled sources are excluded.
     */
    public function testBuildsOneLockPerSkuAndEnabledSource(): void
    {
        $this->getSourcesMock->method('execute')->willReturn([
            $this->source('src_a', true),
            $this->source('src_b', true),
            $this->source('src_disabled', false),
        ]);

        $names = $this->model->buildOrderedLockNames(['sku1', 'sku2'], 5);

        // 2 skus x 2 enabled sources, none from the disabled source.
        $this->assertCount(4, $names);
        $this->assertSame(array_values(array_unique($names)), $names);
    }

    /**
     * The result is globally sorted and independent of the input ordering, which
     * is what guarantees concurrent acquisitions cannot deadlock.
     */
    public function testOrderingIsGlobalAndInputIndependent(): void
    {
        $this->getSourcesMock->method('execute')->willReturnCallback(
            function () {
                // Priority order differs from the global (sorted) order on purpose.
                return [$this->source('src_b', true), $this->source('src_a', true)];
            }
        );

        $forward = $this->model->buildOrderedLockNames(['sku1', 'sku2'], 5);
        $reversed = $this->model->buildOrderedLockNames(['sku2', 'sku1'], 5);

        $sorted = $forward;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $forward, 'lock names must be globally sorted');
        $this->assertSame($forward, $reversed, 'ordering must not depend on the input sku order');
    }

    /**
     * With no enabled sources the lock falls back to a per-stock name.
     */
    public function testFallsBackToStockLockWhenNoEnabledSources(): void
    {
        $this->getSourcesMock->method('execute')->willReturn([$this->source('src_a', false)]);

        $names = $this->model->buildOrderedLockNames(['sku1'], 7);

        $this->assertSame([$this->lockName->build(InventoryLockName::STOCK, '7', 'sku1')], $names);
    }

    public function testSourceLockNamesComeFromTheInstallationScopedBuilder(): void
    {
        $this->getSourcesMock->method('execute')->willReturn([
            $this->source('src_b', true),
            $this->source('src_a', true),
        ]);
        $expected = [
            $this->lockName->build(InventoryLockName::SOURCE, 'src_a', 'sku1'),
            $this->lockName->build(InventoryLockName::SOURCE, 'src_b', 'sku1'),
        ];
        sort($expected, SORT_STRING);

        $this->assertSame($expected, $this->model->buildOrderedLockNames(['sku1'], 5));
        $this->assertSame($expected, $this->model->buildOrderedSourceLockNames(['sku1'], ['src_b', 'src_a']));
    }

    /**
     * Two stocks sharing a source produce the SAME lock name for that source, so
     * cross-stock orders serialise on it.
     */
    public function testSharedSourceProducesSameLockNameAcrossStocks(): void
    {
        $this->getSourcesMock->method('execute')->willReturnCallback(
            function (int $stockId) {
                return $stockId === 5
                    ? [$this->source('shared', true), $this->source('only_5', true)]
                    : [$this->source('shared', true)];
            }
        );

        $stock5 = $this->model->buildOrderedLockNames(['sku1'], 5);
        $stock6 = $this->model->buildOrderedLockNames(['sku1'], 6);

        $shared = array_values(array_intersect($stock5, $stock6));
        $this->assertCount(1, $shared, 'the shared source must yield a common lock name');
    }

    public function testSourceLockNamesMatchTheCheckoutLockNamesForTheSameSource(): void
    {
        $this->getSourcesMock->method('execute')->willReturn([$this->source('slr_a', true)]);

        $checkout = $this->model->buildOrderedLockNames(['sku1'], 5);
        $transfer = $this->model->buildOrderedSourceLockNames(['sku1', 'sku1'], ['slr_b', 'slr_a', 'slr_a']);

        $this->assertCount(2, $transfer);
        $this->assertContains($checkout[0], $transfer);
        $sorted = $transfer;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $transfer);
    }

    public function testAcquiresEverySourceLockAndReleasesThem(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $model = new AcquireStockItemLocks($resourceConnection, $this->getSourcesMock, $this->lockName);
        $queries = [];
        $connection->method('fetchOne')->willReturnCallback(function (string $sql, array $bind) use (&$queries) {
            $queries[] = [strtok($sql, '('), $bind[0]];
            return 1;
        });

        $names = $model->buildOrderedSourceLockNames(['sku1'], ['slr_a', 'slr_b']);
        $model->executeForSources(['sku1'], ['slr_a', 'slr_b']);
        $model->releaseAll();

        $this->assertSame(
            [
                ['SELECT GET_LOCK', $names[0]],
                ['SELECT GET_LOCK', $names[1]],
                ['SELECT RELEASE_LOCK', $names[0]],
                ['SELECT RELEASE_LOCK', $names[1]],
            ],
            $queries
        );
    }

    public function testExecuteReturnsTheLockNamesItTook(): void
    {
        $this->getSourcesMock->method('execute')->willReturn([$this->source('slr_a', true)]);
        $model = $this->modelRecordingQueries();

        $taken = $model->execute(['sku1', 'sku2'], 5);

        $this->assertSame($model->buildOrderedLockNames(['sku1', 'sku2'], 5), $taken);
    }

    public function testReleaseAllReleasesEachLockAsManyTimesAsItWasTaken(): void
    {
        $this->getSourcesMock->method('execute')->willReturn([$this->source('slr_a', true)]);
        $model = $this->modelRecordingQueries();
        [$name] = $model->buildOrderedLockNames(['sku1'], 5);

        $model->execute(['sku1'], 5);
        $model->execute(['sku1'], 5);
        $model->releaseAll();

        $this->assertSame(
            [['SELECT RELEASE_LOCK', $name], ['SELECT RELEASE_LOCK', $name]],
            $this->releases()
        );
    }

    public function testReleaseReleasesOnlyTheGivenTakes(): void
    {
        $this->getSourcesMock->method('execute')->willReturn([$this->source('slr_a', true)]);
        $model = $this->modelRecordingQueries();
        [$outer] = $model->buildOrderedLockNames(['outer'], 5);
        [$inner] = $model->buildOrderedLockNames(['inner'], 5);

        $model->execute(['outer'], 5);
        $taken = $model->execute(['outer', 'inner'], 5);
        $model->release($taken);
        $afterRelease = $this->releases();
        $model->releaseAll();

        $this->assertEqualsCanonicalizing(
            [['SELECT RELEASE_LOCK', $outer], ['SELECT RELEASE_LOCK', $inner]],
            $afterRelease
        );
        $this->assertSame([['SELECT RELEASE_LOCK', $outer]], array_slice($this->releases(), 2));
    }

    public function testFailedAcquisitionReleasesOnlyItsOwnTakes(): void
    {
        $this->getSourcesMock->method('execute')->willReturn([$this->source('slr_a', true)]);
        $model = $this->modelRecordingQueries();
        [$held] = $model->buildOrderedLockNames(['held'], 5);
        $names = $model->buildOrderedLockNames(['sku1', 'sku2'], 5);

        $model->execute(['held'], 5);
        $this->refused[$names[1]] = true;
        try {
            $model->execute(['sku1', 'sku2'], 5);
            $this->fail('expected the acquisition to give up');
        } catch (CouldNotSaveException $e) {
            $this->assertNotContains(['SELECT RELEASE_LOCK', $held], $this->releases());
        }

        $model->releaseAll();
        $this->assertSame(['SELECT RELEASE_LOCK', $held], array_slice($this->releases(), -1)[0]);
    }

    public function testResetReleasesEveryPendingTakeOnTheConnectionThatTookIt(): void
    {
        $this->getSourcesMock->method('execute')->willReturn([$this->source('slr_a', true)]);
        $taking = $this->connectionRecording('taking');
        $later = $this->connectionRecording('later');
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturnOnConsecutiveCalls($taking, $later);
        $model = new AcquireStockItemLocks($resourceConnection, $this->getSourcesMock, $this->lockName);
        [$name] = $model->buildOrderedLockNames(['sku1'], 5);

        $model->execute(['sku1'], 5);
        $model->execute(['sku1'], 5);
        $model->_resetState();

        $this->assertSame(
            [['taking', 'SELECT RELEASE_LOCK', $name], ['taking', 'SELECT RELEASE_LOCK', $name]],
            array_values(array_filter($this->queries, fn(array $query) => $query[1] === 'SELECT RELEASE_LOCK'))
        );
    }

    public function testAReleaseAfterTheResetHasNothingLeftToRelease(): void
    {
        $this->getSourcesMock->method('execute')->willReturn([$this->source('slr_a', true)]);
        $model = $this->modelRecordingQueries();
        $taken = $model->execute(['sku1'], 5);
        $model->_resetState();
        $releasesAfterReset = count($this->releases());

        $model->release($taken);

        $this->assertCount($releasesAfterReset, $this->releases());
    }

    public function testAResetWithoutTakesDoesNotTouchTheDatabase(): void
    {
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->expects($this->never())->method('getConnection');
        $model = new AcquireStockItemLocks($resourceConnection, $this->getSourcesMock, $this->lockName);

        $model->_resetState();
    }

    /**
     * @param string $label
     * @return AdapterInterface
     */
    private function connectionRecording(string $label): AdapterInterface
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('fetchOne')->willReturnCallback(
            function (string $sql, array $bind) use ($label) {
                $this->queries[] = [$label, strtok($sql, '('), $bind[0]];
                return 1;
            }
        );

        return $connection;
    }

    /**
     * @return AcquireStockItemLocks
     */
    private function modelRecordingQueries(): AcquireStockItemLocks
    {
        $connection = $this->createMock(AdapterInterface::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $connection->method('fetchOne')->willReturnCallback(
            function (string $sql, array $bind) {
                $this->queries[] = [strtok($sql, '('), $bind[0]];
                return isset($this->refused[$bind[0]]) && str_starts_with($sql, 'SELECT GET_LOCK') ? 0 : 1;
            }
        );

        return new AcquireStockItemLocks($resourceConnection, $this->getSourcesMock, $this->lockName);
    }

    /**
     * @return array
     */
    private function releases(): array
    {
        return array_values(
            array_filter($this->queries, fn(array $query) => $query[0] === 'SELECT RELEASE_LOCK')
        );
    }

    /**
     * @param string $code
     * @param bool $enabled
     * @return SourceInterface|MockObject
     */
    private function source(string $code, bool $enabled)
    {
        $source = $this->createMock(SourceInterface::class);
        $source->method('getSourceCode')->willReturn($code);
        $source->method('isEnabled')->willReturn($enabled);

        return $source;
    }
}
