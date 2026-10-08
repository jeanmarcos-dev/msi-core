<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\InventoryAdjustment\Model\ResourceModel\AdjustmentWriter;
use Magento\InventoryAdjustment\Model\ResourceModel\SourceItemSnapshot;
use Magento\InventoryAdjustmentApi\Api\AdjustmentContextInterface;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentMetadataInterface;
use Throwable;

class AdjustmentRecorder implements ResetAfterRequestInterface
{
    private int $depth = 0;

    private array $seen = [];

    private array $before = [];

    private ?AdjustmentMetadataInterface $metadata = null;

    /**
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param SourceItemSnapshot $snapshot
     * @param AdjustmentRowsBuilder $rowsBuilder
     * @param AdjustmentWriter $writer
     * @param AdjustmentContextInterface $context
     * @param DefaultMetadataProvider $defaultMetadataProvider
     * @param ActorResolver $actorResolver
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Config $config,
        private readonly SourceItemSnapshot $snapshot,
        private readonly AdjustmentRowsBuilder $rowsBuilder,
        private readonly AdjustmentWriter $writer,
        private readonly AdjustmentContextInterface $context,
        private readonly DefaultMetadataProvider $defaultMetadataProvider,
        private readonly ActorResolver $actorResolver
    ) {
    }

    /**
     * Run a source item write and record what it changed
     *
     * @param array $keys
     * @param callable $write
     * @return mixed
     * @throws Throwable
     */
    public function record(array $keys, callable $write): mixed
    {
        if ($keys === [] || !$this->config->isEnabled()) {
            return $write();
        }
        $connection = $this->resourceConnection->getConnection();
        $outermost = $this->depth === 0;
        if ($outermost) {
            $connection->beginTransaction();
            $this->metadata = $this->context->getCurrent() ?? $this->defaultMetadataProvider->get();
        }
        $this->depth++;
        try {
            $this->lockUnseen($keys);
            $result = $write();
            if ($outermost) {
                $this->writer->write($this->rowsBuilder->build(
                    $this->before,
                    $this->snapshot->read($this->getSeenKeys()),
                    $this->metadata,
                    $this->actorResolver->resolve()
                ));
            }
        } catch (Throwable $exception) {
            if ($outermost) {
                $connection->rollBack();
            }
            throw $exception;
        } finally {
            $this->depth--;
            if ($outermost) {
                $this->_resetState();
            }
        }
        if ($outermost) {
            $connection->commit();
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function _resetState(): void
    {
        $this->depth = 0;
        $this->seen = [];
        $this->before = [];
        $this->metadata = null;
    }

    /**
     * Lock the source items this recording has not locked yet and keep their state
     *
     * @param array $keys
     * @return void
     */
    private function lockUnseen(array $keys): void
    {
        $unseen = [];
        foreach ($keys as $key) {
            if (!isset($this->seen[$key['source_code']][$key['sku']])) {
                $this->seen[$key['source_code']][$key['sku']] = true;
                $unseen[] = $key;
            }
        }
        foreach ($this->snapshot->lock($unseen) as $sourceCode => $items) {
            foreach ($items as $sku => $item) {
                $this->before[$sourceCode][$sku] ??= $item;
            }
        }
    }

    /**
     * Every source item key this recording has seen
     *
     * @return array
     */
    private function getSeenKeys(): array
    {
        $keys = [];
        foreach ($this->seen as $sourceCode => $skus) {
            foreach (array_keys($skus) as $sku) {
                $keys[] = ['source_code' => (string)$sourceCode, 'sku' => (string)$sku];
            }
        }

        return $keys;
    }
}
