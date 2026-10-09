<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentAdminUi\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponent\Processor;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\InventoryAdjustmentAdminUi\Ui\Component\Listing\Column\Actor;
use Magento\InventoryAdjustmentAdminUi\Ui\Component\Listing\Column\Quantity;
use Magento\InventoryAdjustmentAdminUi\Ui\Component\Listing\Column\Reference;
use Magento\InventoryAdjustmentAdminUi\Ui\Component\Listing\Column\StatusChange;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ColumnsTest extends TestCase
{
    /**
     * @var ContextInterface|MockObject
     */
    private $context;

    /**
     * @var Escaper|MockObject
     */
    private $escaper;

    protected function setUp(): void
    {
        $this->context = $this->createMock(ContextInterface::class);
        $this->context->method('getProcessor')->willReturn($this->createMock(Processor::class));
        $this->escaper = $this->createMock(Escaper::class);
        $this->escaper->method('escapeHtml')->willReturnCallback(fn ($value) => htmlspecialchars((string)$value));
        $this->escaper->method('escapeUrl')->willReturnCallback(fn ($value) => htmlspecialchars((string)$value));
    }

    public function testSalesDocumentsLinkToTheirAdminPage(): void
    {
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(
            fn (string $route, array $params) => 'https://admin/' . $route . '/' . key($params) . '/' . current($params)
        );
        $column = new Reference(
            $this->context,
            $this->createMock(UiComponentFactory::class),
            $urlBuilder,
            $this->escaper,
            [],
            ['name' => 'reference_id']
        );

        $rendered = array_column($this->render($column, [
            ['reference_type' => 'order', 'reference_id' => '42'],
            ['reference_type' => 'shipment', 'reference_id' => '17'],
            ['reference_type' => 'invoice', 'reference_id' => '5'],
            ['reference_type' => 'creditmemo', 'reference_id' => '3'],
            ['reference_type' => 'transfer', 'reference_id' => 'bulk-1'],
            ['reference_type' => 'stocktake', 'reference_id' => '<b>ST</b>'],
            ['reference_type' => null, 'reference_id' => null],
        ]), 'reference_id');

        self::assertSame([
            '<a href="https://admin/sales/order/view/order_id/42">#42</a>',
            '<a href="https://admin/adminhtml/order_shipment/view/shipment_id/17">#17</a>',
            '<a href="https://admin/sales/invoice/view/invoice_id/5">#5</a>',
            '<a href="https://admin/sales/creditmemo/view/creditmemo_id/3">#3</a>',
            'bulk-1',
            '&lt;b&gt;ST&lt;/b&gt;',
            '',
        ], $rendered);
    }

    public function testAStatusChangeReadsAsBeforeAndAfter(): void
    {
        $column = new StatusChange(
            $this->context,
            $this->createMock(UiComponentFactory::class),
            [],
            ['name' => 'status_change']
        );

        $rendered = array_column($this->render($column, [
            ['status_before' => '1', 'status_after' => '0'],
            ['status_before' => null, 'status_after' => '1'],
            ['status_before' => null, 'status_after' => null],
        ]), 'status_change');

        self::assertSame(['In Stock → Out of Stock', 'Not Assigned → In Stock', ''], $rendered);
    }

    public function testAnActorReadsAsItsNameOrItsKind(): void
    {
        $column = new Actor(
            $this->context,
            $this->createMock(UiComponentFactory::class),
            $this->escaper,
            [],
            ['name' => 'actor_type']
        );

        $rendered = array_column($this->render($column, [
            ['actor_type' => 'admin', 'actor_id' => '3', 'actor_label' => 'jane'],
            ['actor_type' => 'integration', 'actor_id' => '8', 'actor_label' => null],
            ['actor_type' => 'system', 'actor_id' => null, 'actor_label' => null],
            ['actor_type' => 'admin', 'actor_id' => '4', 'actor_label' => '<i>x</i>'],
        ]), 'actor_type');

        self::assertSame(['jane (admin)', 'integration #8', 'system', '&lt;i&gt;x&lt;/i&gt; (admin)'], $rendered);
    }

    public function testQuantitiesDropTrailingZerosAndDeltasKeepTheirSign(): void
    {
        $delta = new Quantity(
            $this->context,
            $this->createMock(UiComponentFactory::class),
            [],
            ['name' => 'delta', 'config' => ['signed' => true]]
        );
        $after = new Quantity(
            $this->context,
            $this->createMock(UiComponentFactory::class),
            [],
            ['name' => 'quantity_after']
        );

        self::assertSame(
            ['+2', '-3', '+0.5', '0'],
            array_column($this->render($delta, [
                ['delta' => '2.0000'],
                ['delta' => '-3.0000'],
                ['delta' => '0.5000'],
                ['delta' => '0.0000'],
            ]), 'delta')
        );
        self::assertSame(
            ['5', '1.25'],
            array_column(
                $this->render($after, [['quantity_after' => '5.0000'], ['quantity_after' => '1.2500']]),
                'quantity_after'
            )
        );
    }

    private function render(object $column, array $items): array
    {
        return $column->prepareDataSource(['data' => ['items' => $items]])['data']['items'];
    }
}
