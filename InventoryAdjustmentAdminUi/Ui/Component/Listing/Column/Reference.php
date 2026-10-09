<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentAdminUi\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Reference extends Column
{
    private const ROUTES = [
        'order' => ['sales/order/view', 'order_id'],
        'shipment' => ['adminhtml/order_shipment/view', 'shipment_id'],
        'invoice' => ['sales/invoice/view', 'invoice_id'],
        'creditmemo' => ['sales/creditmemo/view', 'creditmemo_id'],
    ];

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param Escaper $escaper
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Show the document behind each row, linked when it is a sales document
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] ?? [] as $key => $item) {
            $dataSource['data']['items'][$key][$name] = $this->renderReference(
                (string)($item['reference_type'] ?? ''),
                (string)($item['reference_id'] ?? '')
            );
        }

        return $dataSource;
    }

    /**
     * Link or text for one reference
     *
     * @param string $type
     * @param string $id
     * @return string
     */
    private function renderReference(string $type, string $id): string
    {
        if ($id === '') {
            return '';
        }
        if (!isset(self::ROUTES[$type]) || !ctype_digit($id)) {
            return $this->escaper->escapeHtml($id);
        }
        [$route, $param] = self::ROUTES[$type];

        return sprintf(
            '<a href="%s">#%s</a>',
            $this->escaper->escapeUrl($this->urlBuilder->getUrl($route, [$param => $id])),
            $id
        );
    }
}
