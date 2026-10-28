<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel\IsStockItemSalableCondition;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\InventoryApi\Api\Data\SourceItemInterface;

/**
 * Condition for min_qty configuration.
 */
class MinQtyStockCondition implements GetIsStockItemSalableConditionInterface
{
    /**
     * @var StockConfigurationInterface
     */
    private $configuration;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param StockConfigurationInterface $configuration
     * @param ResourceConnection $resourceConnection
     * @param GetNonBackorderableTypeCondition $getNonBackorderableTypeCondition
     */
    public function __construct(
        StockConfigurationInterface $configuration,
        ResourceConnection $resourceConnection,
        private readonly GetNonBackorderableTypeCondition $getNonBackorderableTypeCondition
    ) {
        $this->configuration = $configuration;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Select $select): string
    {
        $globalMinQty = (float)$this->configuration->getMinQty();

        $quantityExpression = (string)$this->resourceConnection->getConnection()->getCheckSql(
            'source_item.' . SourceItemInterface::STATUS . ' = ' . SourceItemInterface::STATUS_OUT_OF_STOCK,
            0,
            'source_item.' . SourceItemInterface::QUANTITY
        );
        $quantityExpression = 'SUM(' . $quantityExpression . ')';
        $globalThreshold = (string)$globalMinQty;
        $itemThreshold = 'stock_item_configuration.min_qty';
        $connection = $this->resourceConnection->getConnection();
        $nonBackorderableType = $this->getNonBackorderableTypeCondition->execute($connection);
        if ($nonBackorderableType !== null) {
            $globalThreshold = (string)$connection->getCheckSql(
                $nonBackorderableType,
                (string)max(0.0, $globalMinQty),
                $globalThreshold
            );
            $itemThreshold = (string)$connection->getCheckSql(
                $nonBackorderableType,
                "GREATEST($itemThreshold, 0)",
                $itemThreshold
            );
        }

        $condition =
            '(stock_item_configuration.use_config_min_qty = 1 AND ' . $quantityExpression
            . ' > ' . $globalThreshold . ')'
            . ' OR '
            . '(stock_item_configuration.use_config_min_qty = 0 AND ' . $quantityExpression
            . ' > ' . $itemThreshold . ')';

        return $condition;
    }
}
