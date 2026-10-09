<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\InventoryAdjustment\Model\Actor;
use Magento\InventoryAdjustment\Model\ActorResolver;
use Magento\InventoryAdjustment\Model\AdjustmentMetadata;
use Magento\InventoryAdjustment\Model\AdjustmentOrigin;
use Magento\InventoryAdjustment\Model\AdjustmentRecorder;
use Magento\InventoryAdjustment\Model\AdjustmentRowsBuilder;
use Magento\InventoryAdjustment\Model\Config;
use Magento\InventoryAdjustment\Model\DefaultMetadataProvider;
use Magento\InventoryAdjustment\Model\ResourceModel\AdjustmentWriter;
use Magento\InventoryAdjustment\Model\ResourceModel\SourceItemSnapshot;
use Magento\InventoryAdjustmentApi\Api\AdjustmentContextInterface;
use Magento\InventoryAdjustmentApi\Model\ActorType;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AdjustmentRecorderTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var SourceItemSnapshot|MockObject
     */
    private $snapshot;

    /**
     * @var bool
     */
    private bool $enabled = true;

    /**
     * @var int
     */
    private int $transactionLevel = 0;

    /**
     * @var array
     */
    private array $stored = [];

    /**
     * @var array
     */
    private array $written = [];

    /**
     * @var AdjustmentOrigin
     */
    private AdjustmentOrigin $origin;

    /**
     * @var AdjustmentRecorder
     */
    private AdjustmentRecorder $recorder;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('getTransactionLevel')->willReturnCallback(fn () => $this->transactionLevel);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturnCallback(fn () => $this->enabled);
        $this->snapshot = $this->createMock(SourceItemSnapshot::class);
        $this->snapshot->method('lock')->willReturnCallback(fn (array $keys) => $this->select($keys));
        $this->snapshot->method('read')->willReturnCallback(fn (array $keys) => $this->select($keys));
        $writer = $this->createMock(AdjustmentWriter::class);
        $writer->method('write')->willReturnCallback(function (array $rows): void {
            $this->written[] = $rows;
        });
        $context = $this->createMock(AdjustmentContextInterface::class);
        $context->method('getCurrent')->willReturn(new AdjustmentMetadata(AdjustmentReason::Count, 'order', '7'));
        $actorResolver = $this->createMock(ActorResolver::class);
        $actorResolver->method('resolve')->willReturn(new Actor(ActorType::System));
        $this->origin = new AdjustmentOrigin();

        $this->recorder = new AdjustmentRecorder(
            $resource,
            $config,
            $this->snapshot,
            new AdjustmentRowsBuilder(),
            $writer,
            $context,
            $this->createMock(DefaultMetadataProvider::class),
            $actorResolver,
            $this->origin
        );
    }

    public function testRecordsTheChangeInsideOneTransaction(): void
    {
        $this->stored = ['src' => ['SKU-1' => ['quantity' => 10.0, 'status' => 1]]];
        $this->connection->expects(self::once())->method('beginTransaction');
        $this->connection->expects(self::once())->method('commit');
        $this->connection->expects(self::never())->method('rollBack');

        $result = $this->recorder->record($this->keys('SKU-1'), function () {
            $this->stored['src']['SKU-1']['quantity'] = 7.0;
            return 'done';
        });

        self::assertSame('done', $result);
        self::assertCount(1, $this->written);
        self::assertSame(-3.0, $this->written[0][0]['delta']);
        self::assertSame('count', $this->written[0][0]['reason']);
        self::assertSame('7', $this->written[0][0]['reference_id']);
    }

    public function testDisabledHistoryOnlyRunsTheWrite(): void
    {
        $this->enabled = false;
        $this->snapshot->expects(self::never())->method('lock');
        $this->connection->expects(self::never())->method('beginTransaction');

        self::assertSame('done', $this->recorder->record($this->keys('SKU-1'), fn () => 'done'));
        self::assertSame([], $this->written);
    }

    public function testFailedWriteRollsBackAndRecordsNothing(): void
    {
        $this->stored = ['src' => ['SKU-1' => ['quantity' => 10.0, 'status' => 1]]];
        $this->connection->expects(self::once())->method('rollBack');
        $this->connection->expects(self::never())->method('commit');
        $this->expectException(RuntimeException::class);

        try {
            $this->recorder->record($this->keys('SKU-1'), fn () => throw new RuntimeException('write failed'));
        } finally {
            self::assertSame([], $this->written);
        }
    }

    public function testNestedWritesAreRecordedOnceByTheOutermostCall(): void
    {
        $this->stored = ['src' => [
            'SKU-1' => ['quantity' => 10.0, 'status' => 1],
            'SKU-2' => ['quantity' => 4.0, 'status' => 1],
        ]];
        $this->connection->expects(self::once())->method('commit');

        $this->recorder->record($this->keys('SKU-1'), function (): void {
            unset($this->stored['src']['SKU-1']);
            $this->recorder->record($this->keys('SKU-2'), function (): void {
                $this->stored['src']['SKU-2']['quantity'] = 6.0;
            });
        });

        self::assertCount(1, $this->written);
        self::assertSame([-10.0, 2.0], array_column($this->written[0], 'delta'));
    }

    public function testACaughtNestedFailureKeepsTheOuterRecording(): void
    {
        $this->stored = ['src' => ['SKU-1' => ['quantity' => 10.0, 'status' => 1]]];
        $this->connection->expects(self::never())->method('rollBack');
        $this->connection->expects(self::once())->method('commit');

        $this->recorder->record($this->keys('SKU-1'), function (): void {
            $this->stored['src']['SKU-1']['quantity'] = 8.0;
            try {
                $this->recorder->record($this->keys('SKU-2'), [$this, 'failWrite']);
            } catch (RuntimeException) {
            }
        });

        self::assertSame([-2.0], array_column($this->written[0], 'delta'));
    }

    public function testCaseVariantsOfOneSkuWriteOneRow(): void
    {
        $this->stored = ['src' => ['abc' => ['quantity' => 5.0, 'status' => 1]]];

        $this->recorder->record(
            [['source_code' => 'src', 'sku' => 'abc'], ['source_code' => 'src', 'sku' => 'ABC']],
            function (): void {
                $this->stored['src']['abc']['quantity'] = 6.0;
            }
        );

        self::assertCount(1, $this->written[0]);
    }

    public function testInsideAnOuterTransactionItNeitherOpensNorEndsOne(): void
    {
        $this->transactionLevel = 1;
        $this->stored = ['src' => ['SKU-1' => ['quantity' => 10.0, 'status' => 1]]];
        $this->connection->expects(self::never())->method('beginTransaction');
        $this->connection->expects(self::never())->method('commit');

        $this->recorder->record($this->keys('SKU-1'), function (): void {
            $this->stored['src']['SKU-1']['quantity'] = 9.0;
        });

        self::assertSame([-1.0], array_column($this->written[0], 'delta'));
    }

    public function testAFailureInsideAnOuterTransactionIsLeftToItsOwner(): void
    {
        $this->transactionLevel = 1;
        $this->stored = ['src' => ['SKU-1' => ['quantity' => 10.0, 'status' => 1]]];
        $this->connection->expects(self::never())->method('rollBack');
        $this->expectException(RuntimeException::class);

        $this->recorder->record($this->keys('SKU-1'), [$this, 'failWrite']);
    }

    public function testAFailedCommitRollsBack(): void
    {
        $this->stored = ['src' => ['SKU-1' => ['quantity' => 10.0, 'status' => 1]]];
        $this->connection->method('commit')->willThrowException(new RuntimeException('commit failed'));
        $this->connection->expects(self::once())->method('rollBack');
        $this->expectException(RuntimeException::class);

        $this->recorder->record($this->keys('SKU-1'), function (): void {
            $this->stored['src']['SKU-1']['quantity'] = 9.0;
        });
    }

    public function failWrite(): void
    {
        throw new RuntimeException('bad row');
    }

    public function testTheOriginGivesTheActorAndTheRequestId(): void
    {
        $this->stored = ['src' => ['SKU-1' => ['quantity' => 10.0, 'status' => 1]]];

        $this->origin->run(
            new Actor(ActorType::Import, '3', 'jane'),
            'run-1',
            fn () => $this->recorder->record($this->keys('SKU-1'), function (): void {
                $this->stored['src']['SKU-1']['quantity'] = 12.0;
            })
        );

        $row = $this->written[0][0];
        self::assertSame('import', $row['actor_type']);
        self::assertSame('3', $row['actor_id']);
        self::assertSame('jane', $row['actor_label']);
        self::assertSame('run-1', $row['request_id']);
    }

    public function testANetRecordingLetsEveryWriteCommitOnItsOwn(): void
    {
        $this->stored = ['src' => ['SKU-1' => ['quantity' => 7.0, 'status' => 1]]];
        $this->connection->expects(self::never())->method('beginTransaction');
        $this->connection->expects(self::never())->method('commit');

        $this->recorder->recordNet($this->keys('SKU-1'), function (): void {
            $this->recorder->record($this->keys('SKU-1'), function (): void {
                unset($this->stored['src']['SKU-1']);
            });
            $this->recorder->record($this->keys('SKU-1'), function (): void {
                $this->stored['src']['SKU-1'] = ['quantity' => 4.0, 'status' => 1];
            });
        });

        self::assertCount(1, $this->written);
        self::assertSame([-3.0], array_column($this->written[0], 'delta'));
    }

    public function testANetRecordingKeepsWhatWasDoneBeforeAFailure(): void
    {
        $this->stored = ['src' => ['SKU-1' => ['quantity' => 7.0, 'status' => 1]]];

        try {
            $this->recorder->recordNet($this->keys('SKU-1'), [$this, 'deleteThenFail']);
            self::fail('The failure was swallowed');
        } catch (RuntimeException) {
        }

        self::assertSame([-7.0], array_column($this->written[0], 'delta'));
    }

    public function testANetRecordingInsideAnotherRecordingIsPartOfIt(): void
    {
        $this->stored = ['src' => ['SKU-1' => ['quantity' => 7.0, 'status' => 1]]];
        $this->connection->expects(self::once())->method('beginTransaction');

        $this->recorder->record($this->keys('SKU-1'), function (): void {
            $this->recorder->recordNet($this->keys('SKU-1'), function (): void {
                $this->stored['src']['SKU-1']['quantity'] = 5.0;
            });
        });

        self::assertCount(1, $this->written);
        self::assertSame([-2.0], array_column($this->written[0], 'delta'));
    }

    public function deleteThenFail(): void
    {
        unset($this->stored['src']['SKU-1']);
        throw new RuntimeException('save failed');
    }

    private function keys(string $sku): array
    {
        return [['source_code' => 'src', 'sku' => $sku]];
    }

    private function select(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            foreach ($this->stored[$key['source_code']] ?? [] as $sku => $item) {
                if (strcasecmp((string)$sku, $key['sku']) === 0) {
                    $result[$key['source_code']][$sku] = $item;
                }
            }
        }

        return $result;
    }
}
