<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySalesAdminUi\Ui\Component\Control\Stock;

use Magento\Backend\Ui\Component\Control\DeleteButton as StockDeleteButton;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\Framework\App\RequestInterface;
use Magento\InventorySalesApi\Model\GetAssignedSalesChannelsForStockInterface;

/**
 * Represents delete button with pre-configured options
 * Provide an ability to show delete button only when the stock has no assigned sales channels
 */
class DeleteButton implements ButtonProviderInterface
{
    /**
     * @var StockDeleteButton
     */
    private $deleteButton;

    /**
     * @var GetAssignedSalesChannelsForStockInterface
     */
    private $assignedSalesChannelsForStock;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @param StockDeleteButton $deleteButton
     * @param GetAssignedSalesChannelsForStockInterface $assignedSalesChannelsForStock
     * @param RequestInterface $request
     */
    public function __construct(
        StockDeleteButton $deleteButton,
        GetAssignedSalesChannelsForStockInterface $assignedSalesChannelsForStock,
        RequestInterface $request
    ) {
        $this->deleteButton = $deleteButton;
        $this->assignedSalesChannelsForStock = $assignedSalesChannelsForStock;
        $this->request = $request;
    }

    /**
     * @inheritdoc
     */
    public function getButtonData()
    {
        $stockId = (int)$this->request->getParam(StockInterface::STOCK_ID);
        $assignSalesChannels = $this->assignedSalesChannelsForStock->execute($stockId);
        if (count($assignSalesChannels)) {
            return [];
        }

        return $this->deleteButton->getButtonData();
    }
}
