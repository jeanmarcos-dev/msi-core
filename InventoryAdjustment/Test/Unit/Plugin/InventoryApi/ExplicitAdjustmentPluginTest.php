<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Plugin\InventoryApi;

use Magento\Framework\Exception\InputException;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\InventoryAdjustment\Model\AdjustmentContext;
use Magento\InventoryAdjustment\Model\AdjustmentInput;
use Magento\InventoryAdjustment\Model\ExplicitAdjustmentResolver;
use Magento\InventoryAdjustment\Plugin\InventoryApi\ExplicitAdjustmentPlugin;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentMetadataInterface;
use Magento\InventoryApi\Api\Data\SourceItemExtensionInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use PHPUnit\Framework\TestCase;

class ExplicitAdjustmentPluginTest extends TestCase
{
    use MockCreationTrait;

    /**
     * @var AdjustmentContext
     */
    private AdjustmentContext $context;

    /**
     * @var ExplicitAdjustmentPlugin
     */
    private ExplicitAdjustmentPlugin $plugin;

    /**
     * @var AdjustmentMetadataInterface|null
     */
    private ?AdjustmentMetadataInterface $seen = null;

    /**
     * @var int
     */
    private int $calls = 0;

    protected function setUp(): void
    {
        $this->context = new AdjustmentContext();
        $this->plugin = new ExplicitAdjustmentPlugin($this->context, new ExplicitAdjustmentResolver());
    }

    public function testItemsWithoutAnAdjustmentAreSavedAsBefore(): void
    {
        $items = [$this->item(null), $this->item(null, false)];

        $this->plugin->aroundExecute($this->createMock(SourceItemsSaveInterface::class), $this->proceed(), $items);

        self::assertSame(1, $this->calls);
        self::assertNull($this->seen);
    }

    public function testTheSaveSeesTheRequestedReason(): void
    {
        $items = [$this->item($this->input('count')), $this->item($this->input('count'))];

        $this->plugin->aroundExecute($this->createMock(SourceItemsSaveInterface::class), $this->proceed(), $items);

        self::assertSame('count', $this->seen->getReason()->value);
        self::assertNull($this->context->getCurrent());
    }

    public function testAnInvalidAdjustmentSavesNothing(): void
    {
        try {
            $this->plugin->aroundExecute(
                $this->createMock(SourceItemsSaveInterface::class),
                $this->proceed(),
                [$this->item($this->input('lost_in_war'))]
            );
            self::fail('The invalid adjustment was accepted');
        } catch (InputException) {
            self::assertSame(0, $this->calls);
        }
    }

    private function proceed(): callable
    {
        return function (array $items): void {
            $this->calls++;
            $this->seen = $this->context->getCurrent();
        };
    }

    private function input(string $reason): AdjustmentInput
    {
        $input = new AdjustmentInput();
        $input->setReason($reason);
        return $input;
    }

    private function item(?AdjustmentInput $adjustment, bool $withExtension = true): SourceItemInterface
    {
        $item = $this->createMock(SourceItemInterface::class);
        if ($withExtension) {
            $extension = $this->createPartialMockWithReflection(
                SourceItemExtensionInterface::class,
                ['getAdjustment', 'setAdjustment']
            );
            $extension->method('getAdjustment')->willReturn($adjustment);
            $item->method('getExtensionAttributes')->willReturn($extension);
        }
        return $item;
    }
}
