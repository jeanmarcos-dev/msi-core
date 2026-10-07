<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;

/**
 * Acquire per-source advisory locks around reservation placement.
 *
 * The reservation salability check and the reservation write are not atomic, so
 * concurrent orders can oversell. A per-(sku,stockId) lock only serialises orders
 * on the same stock; when two stocks share a physical source, orders on different
 * stocks race on that source. This locks one advisory lock per (sku, enabled
 * source of the stock) instead, so any orders that could draw from the same
 * source serialise regardless of stock.
 *
 * All locks a call needs are taken up front in a single GLOBAL total order (the
 * sorted lock names), which makes circular waits - and therefore deadlocks -
 * impossible no matter which sources the allocation algorithm ends up using.
 */
class AcquireStockItemLocks implements ResetAfterRequestInterface
{
    /**
     * Lock wait timeout, in seconds, per acquisition attempt.
     */
    private const LOCK_TIMEOUT = 10;

    /**
     * Attempts before giving up when a lock cannot be taken within the timeout.
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var GetSourcesAssignedToStockOrderedByPriorityInterface
     */
    private $getSourcesAssignedToStock;

    /**
     * @var array<string,bool>
     */
    private $acquiredLocks = [];

    /**
     * @var AdapterInterface|null
     */
    private $connection;

    /**
     * @param ResourceConnection $resourceConnection
     * @param GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->getSourcesAssignedToStock = $getSourcesAssignedToStock;
    }

    /**
     * @inheritdoc
     */
    public function _resetState(): void
    {
        $this->connection = null;
        $this->acquiredLocks = [];
    }

    /**
     * Acquire every (sku, source) lock the given SKUs need on the stock.
     *
     * @param string[] $skus
     * @param int $stockId
     * @return void
     * @throws CouldNotSaveException
     */
    public function execute(array $skus, int $stockId): void
    {
        $lockNames = $this->buildOrderedLockNames($skus, $stockId);
        if (!$lockNames) {
            return;
        }
        $this->acquireOrdered($lockNames);
    }

    /**
     * Build the globally-ordered list of lock names for the SKUs on the stock.
     *
     * Ordering by the lock name is a stock-independent total order, so two orders
     * on different stocks that share a source request the shared lock in the same
     * position and cannot form a cycle.
     *
     * @param string[] $skus
     * @param int $stockId
     * @return string[]
     */
    public function buildOrderedLockNames(array $skus, int $stockId): array
    {
        $sourceCodes = $this->getEnabledSourceCodes($stockId);
        $names = [];
        foreach ($skus as $sku) {
            $sku = (string)$sku;
            if ($sourceCodes) {
                foreach ($sourceCodes as $sourceCode) {
                    $names[] = $this->sourceLockName($sku, $sourceCode);
                }
            } else {
                $names[] = $this->stockLockName($sku, $stockId);
            }
        }
        $names = array_values(array_unique($names));
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * Acquire the (sku, source) locks of the SKUs on the given sources, the same locks checkout takes.
     *
     * @param string[] $skus
     * @param string[] $sourceCodes
     * @return void
     * @throws CouldNotSaveException
     */
    public function executeForSources(array $skus, array $sourceCodes): void
    {
        $lockNames = $this->buildOrderedSourceLockNames($skus, $sourceCodes);
        if (!$lockNames) {
            return;
        }
        $this->acquireOrdered($lockNames);
    }

    /**
     * Build the globally-ordered list of (sku, source) lock names.
     *
     * @param string[] $skus
     * @param string[] $sourceCodes
     * @return string[]
     */
    public function buildOrderedSourceLockNames(array $skus, array $sourceCodes): array
    {
        $names = [];
        foreach ($skus as $sku) {
            foreach ($sourceCodes as $sourceCode) {
                $names[] = $this->sourceLockName((string)$sku, (string)$sourceCode);
            }
        }
        $names = array_values(array_unique($names));
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * Release every lock acquired by this instance.
     *
     * @return void
     */
    public function releaseAll(): void
    {
        if (!$this->acquiredLocks) {
            return;
        }
        try {
            foreach (array_keys($this->acquiredLocks) as $lockName) {
                $this->releaseLock($lockName);
            }
        } catch (\Throwable $e) { //phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
            // Locks are released by MySQL when the connection closes.
        } finally {
            $this->acquiredLocks = [];
        }
    }

    /**
     * Acquire the given lock names in order, releasing and retrying on timeout.
     *
     * @param string[] $lockNames
     * @return void
     * @throws CouldNotSaveException
     */
    private function acquireOrdered(array $lockNames): void
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $acquiredAll = true;
            foreach ($lockNames as $lockName) {
                if (!$this->acquireLock($lockName)) {
                    $acquiredAll = false;
                    break;
                }
                $this->acquiredLocks[$lockName] = true;
            }
            if ($acquiredAll) {
                return;
            }
            $this->releaseAll();
            // Back off before retrying so contending requests do not lock-step.
            usleep(50000 * $attempt);
        }

        throw new CouldNotSaveException(
            __('Could not acquire inventory lock for the requested items. Please try again.')
        );
    }

    /**
     * Run GET_LOCK for a name; a deadlock verdict is treated as "not acquired".
     *
     * @param string $lockName
     * @return bool
     */
    private function acquireLock(string $lockName): bool
    {
        try {
            return (bool)$this->getConnection()->fetchOne('SELECT GET_LOCK(?, ?)', [$lockName, self::LOCK_TIMEOUT]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Run RELEASE_LOCK for a name.
     *
     * @param string $lockName
     * @return void
     */
    private function releaseLock(string $lockName): void
    {
        $this->getConnection()->fetchOne('SELECT RELEASE_LOCK(?)', [$lockName]);
    }

    /**
     * Enabled source codes assigned to the stock.
     *
     * @param int $stockId
     * @return string[]
     */
    private function getEnabledSourceCodes(int $stockId): array
    {
        $codes = [];
        foreach ($this->getSourcesAssignedToStock->execute($stockId) as $source) {
            if ($source->isEnabled()) {
                $codes[] = (string)$source->getSourceCode();
            }
        }

        return $codes;
    }

    /**
     * Stock-independent advisory lock name for a sku on a source.
     *
     * Being stock-independent is what makes a shared source contend across stocks.
     *
     * @param string $sku
     * @param string $sourceCode
     * @return string
     */
    private function sourceLockName(string $sku, string $sourceCode): string
    {
        // phpcs:ignore Magento2.Security.InsecureFunction
        return sprintf('inv_src_%s_%s', md5($sourceCode), md5($sku));
    }

    /**
     * Fallback advisory lock name for a sku on a stock with no enabled sources.
     *
     * @param string $sku
     * @param int $stockId
     * @return string
     */
    private function stockLockName(string $sku, int $stockId): string
    {
        // phpcs:ignore Magento2.Security.InsecureFunction
        return sprintf('inv_stk_%d_%s', $stockId, md5($sku));
    }

    /**
     * Get the (lazily resolved) database connection.
     *
     * @return AdapterInterface
     */
    private function getConnection(): AdapterInterface
    {
        if ($this->connection === null) {
            $this->connection = $this->resourceConnection->getConnection();
        }

        return $this->connection;
    }

    /**
     * Release locks if the request ends without an explicit release.
     */
    public function __destruct()
    {
        $this->releaseAll();
    }
}
