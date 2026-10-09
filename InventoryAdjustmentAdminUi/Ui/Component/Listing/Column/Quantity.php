<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentAdminUi\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class Quantity extends Column
{
    /**
     * Show quantities without trailing zeros, and with their sign when the column is signed
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        $name = $this->getData('name');
        $signed = (bool)($this->getData('config')['signed'] ?? false);
        foreach ($dataSource['data']['items'] ?? [] as $key => $item) {
            if (!isset($item[$name])) {
                continue;
            }
            $value = (float)$item[$name];
            $text = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
            $dataSource['data']['items'][$key][$name] = $signed && $value > 0 ? '+' . $text : $text;
        }

        return $dataSource;
    }
}
