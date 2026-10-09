<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentAdminUi\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Actor extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param Escaper $escaper
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Show who made each change by name, or by kind and id when the name is unknown
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] ?? [] as $key => $item) {
            $type = (string)($item['actor_type'] ?? '');
            $id = (string)($item['actor_id'] ?? '');
            $label = (string)($item['actor_label'] ?? '');
            $text = match (true) {
                $label !== '' => sprintf('%s (%s)', $label, $type),
                $id !== '' => sprintf('%s #%s', $type, $id),
                default => $type,
            };
            $dataSource['data']['items'][$key][$name] = $this->escaper->escapeHtml($text);
        }

        return $dataSource;
    }
}
