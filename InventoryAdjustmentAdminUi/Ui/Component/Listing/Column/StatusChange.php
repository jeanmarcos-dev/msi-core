<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentAdminUi\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class StatusChange extends Column
{
    /**
     * Show a changed stock status as its value before and after
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] ?? [] as $key => $item) {
            $before = $item['status_before'] ?? null;
            $after = $item['status_after'] ?? null;
            $dataSource['data']['items'][$key][$name] = $before === null && $after === null
                ? ''
                : (string)__('%1 → %2', $this->getLabel($before), $this->getLabel($after));
        }

        return $dataSource;
    }

    /**
     * Label of a stored stock status
     *
     * @param string|int|null $status
     * @return string
     */
    private function getLabel(string|int|null $status): string
    {
        if ($status === null) {
            return (string)__('Not Assigned');
        }

        return (int)$status === 1 ? (string)__('In Stock') : (string)__('Out of Stock');
    }
}
