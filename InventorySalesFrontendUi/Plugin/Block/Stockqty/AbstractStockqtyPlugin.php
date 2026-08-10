<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySalesFrontendUi\Plugin\Block\Stockqty;

use Magento\CatalogInventory\Block\Stockqty\AbstractStockqty;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryCatalogFrontendUi\Model\IsSalableQtyThresholdReached;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Exception\SkuIsNotAssignedToStockException;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\InventoryCatalogFrontendUi\Model\IsSalableQtyAvailableForDisplaying;

/**
 * Plugin for adapting stock qty for block.
 */
class AbstractStockqtyPlugin
{
    /**
     * @var GetStockItemConfigurationInterface
     */
    private GetStockItemConfigurationInterface $getStockItemConfiguration;

    /**
     * @var StockByWebsiteIdResolverInterface
     */
    private StockByWebsiteIdResolverInterface $stockByWebsiteId;

    /**
     * @var GetProductSalableQtyInterface
     */
    private GetProductSalableQtyInterface $getProductSalableQty;

    /**
     * @var IsSourceItemManagementAllowedForProductTypeInterface
     */
    private IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType;

    /**
     * @var IsSalableQtyThresholdReached
     */
    private IsSalableQtyThresholdReached $qtyLeftChecker;

    /**
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteId
     * @param GetStockItemConfigurationInterface $getStockItemConfig
     * @param GetProductSalableQtyInterface $getProductSalableQty
     * @param IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType
     * @param IsSalableQtyAvailableForDisplaying $qtyLeftChecker
     * @param IsSalableQtyThresholdReached|null $isSalableQtyThresholdReached
     */
    public function __construct(
        StockByWebsiteIdResolverInterface $stockByWebsiteId,
        GetStockItemConfigurationInterface $getStockItemConfig,
        GetProductSalableQtyInterface $getProductSalableQty,
        IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType,
        IsSalableQtyAvailableForDisplaying $qtyLeftChecker,
        ?IsSalableQtyThresholdReached $isSalableQtyThresholdReached = null
    ) {
        $this->getStockItemConfiguration = $getStockItemConfig;
        $this->stockByWebsiteId = $stockByWebsiteId;
        $this->getProductSalableQty = $getProductSalableQty;
        $this->isSourceItemManagementAllowedForProductType = $isSourceItemManagementAllowedForProductType;
        $this->qtyLeftChecker = $isSalableQtyThresholdReached
            ?? ObjectManager::getInstance()->get(IsSalableQtyThresholdReached::class);
    }

    /**
     * Is message visible.
     *
     * @param AbstractStockqty $subject
     * @param callable $proceed
     * @return bool
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundIsMsgVisible(AbstractStockqty $subject, callable $proceed): bool
    {
        $product = $subject->getProduct();
        if ($this->isSourceItemManagementAllowedForProductType->execute($product->getTypeId())) {
            $sku = $product->getSku();
            $stockId = (int)$this->stockByWebsiteId->execute(
                (int)$subject->getProduct()->getStore()->getWebsiteId()
            )->getStockId();
            try {
                $stockItemConfig = $this->getStockItemConfiguration->execute($sku, $stockId);
                $salableQty = $this->getProductSalableQty->execute($sku, $stockId);
            } catch (SkuIsNotAssignedToStockException $e) {
                return false;
            }

            return $stockItemConfig->isManageStock()
                && $this->qtyLeftChecker->execute($salableQty, $stockItemConfig);
        }

        return false;
    }

    /**
     * Get stock qty left.
     *
     * @param AbstractStockqty $subject
     * @param callable $proceed
     * @return float
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundGetStockQtyLeft(AbstractStockqty $subject, callable $proceed): float
    {
        $product = $subject->getProduct();

        return $this->getProductSalableQty->execute(
            $product->getSku(),
            (int)$this->stockByWebsiteId->execute((int)$product->getStore()->getWebsiteId())->getStockId()
        );
    }
}
