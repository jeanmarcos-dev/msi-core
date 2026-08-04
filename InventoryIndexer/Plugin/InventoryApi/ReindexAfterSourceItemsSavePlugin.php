<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Plugin\InventoryApi;

use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryIndexer\Indexer\SourceItem\GetSourceItemIds;
use Magento\InventoryIndexer\Indexer\SourceItem\SourceItemIndexer;

class ReindexAfterSourceItemsSavePlugin
{
    /**
     * @var GetSourceItemIds
     */
    private $getSourceItemIds;

    /**
     * @var SourceItemIndexer
     */
    private $sourceItemIndexer;

    /**
     * @param GetSourceItemIds $getSourceItemIds
     * @param SourceItemIndexer $sourceItemIndexer
     */
    public function __construct(
        GetSourceItemIds $getSourceItemIds,
        SourceItemIndexer $sourceItemIndexer
    ) {
        $this->getSourceItemIds = $getSourceItemIds;
        $this->sourceItemIndexer = $sourceItemIndexer;
    }

    /**
     * Run reindex process for saved source items
     *
     * @param SourceItemsSaveInterface $subject
     * @param void $result
     * @param SourceItemInterface[] $sourceItems
     * @return void
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        SourceItemsSaveInterface $subject,
        $result,
        array $sourceItems
    ) {
        $sourceItemIds = $this->getSourceItemIds->execute($sourceItems);
        if (count($sourceItemIds)) {
            $this->sourceItemIndexer->executeList($sourceItemIds);
        }
    }
}
