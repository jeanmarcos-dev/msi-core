<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\Layout;
use Magento\Framework\View\Layout\ProcessorInterface;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Observer\AddPanelLayoutHandle;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see AddPanelLayoutHandle
 */
class AddPanelLayoutHandleTest extends TestCase
{
    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var ProcessorInterface|MockObject
     */
    private $update;

    /**
     * @var Layout|MockObject
     */
    private $layout;

    /**
     * @var AddPanelLayoutHandle
     */
    private $observer;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->config = $this->createMock(Config::class);
        $this->update = $this->createMock(ProcessorInterface::class);
        $this->layout = $this->createMock(Layout::class);
        $this->layout->method('getUpdate')->willReturn($this->update);
        $this->observer = new AddPanelLayoutHandle($this->config);
    }

    /**
     * The handle is applied on the product page when the visualizer is enabled.
     *
     * @return void
     */
    public function testAddsHandleOnEnabledProductPage(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->update->expects($this->once())->method('addHandle')->with('inventory_stockviz_panel');

        $this->observer->execute($this->createObserver('catalog_product_view', $this->layout));
    }

    /**
     * A disabled visualizer leaves the layout alone, so the core availability badge stays.
     *
     * @return void
     */
    public function testDoesNotAddHandleWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->update->expects($this->never())->method('addHandle');

        $this->observer->execute($this->createObserver('catalog_product_view', $this->layout));
    }

    /**
     * Every other page is left alone, enabled or not.
     *
     * @return void
     */
    public function testDoesNotAddHandleOutsideTheProductPage(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->update->expects($this->never())->method('addHandle');

        $this->observer->execute($this->createObserver('cms_index_index', $this->layout));
    }

    /**
     * A payload without a layout is ignored instead of fataling.
     *
     * @return void
     */
    public function testIgnoresMissingLayout(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->update->expects($this->never())->method('addHandle');

        $this->observer->execute($this->createObserver('catalog_product_view', null));
    }

    /**
     * @param string $fullActionName
     * @param Layout|MockObject|null $layout
     * @return Observer
     */
    private function createObserver(string $fullActionName, $layout): Observer
    {
        $event = new Event(['full_action_name' => $fullActionName, 'layout' => $layout]);

        return new Observer(['event' => $event]);
    }
}
