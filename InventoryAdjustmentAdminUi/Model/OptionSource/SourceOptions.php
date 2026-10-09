<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentAdminUi\Model\OptionSource;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;

class SourceOptions implements OptionSourceInterface
{
    /**
     * @param SourceRepositoryInterface $sourceRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * Every source, enabled or not, since history outlives a disabled source
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->sourceRepository->getList($this->searchCriteriaBuilder->create())->getItems() as $source) {
            $options[] = [
                'value' => $source->getSourceCode(),
                'label' => sprintf('%s (%s)', $source->getName(), $source->getSourceCode()),
            ];
        }

        return $options;
    }
}
