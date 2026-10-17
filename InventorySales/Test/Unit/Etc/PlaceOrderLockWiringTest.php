<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Etc;

use Magento\InventorySales\Model\AppendReservations;
use Magento\InventorySales\Plugin\InventorySales\LockReservedSkusPlugin;
use PHPUnit\Framework\TestCase;

class PlaceOrderLockWiringTest extends TestCase
{
    /**
     * @var \DOMXPath
     */
    private $xpath;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $document = new \DOMDocument();
        $document->load(dirname(__DIR__, 3) . '/etc/di.xml');
        $this->xpath = new \DOMXPath($document);
    }

    public function testTheReservationIsWrappedByTheLockPlugin(): void
    {
        $this->assertPluginEnabled(AppendReservations::class, 'lock_reserved_skus', LockReservedSkusPlugin::class);
    }

    public function testTheSubmissionNoLongerTakesALock(): void
    {
        $this->assertSame(
            0,
            $this->xpath->query('//plugin[@name="inventory_lock_during_place_order"]')->length
        );
    }

    /**
     * @param string $type
     * @param string $name
     * @param string $pluginClass
     * @return void
     */
    private function assertPluginEnabled(string $type, string $name, string $pluginClass): void
    {
        $plugins = $this->xpath->query(sprintf('//type[@name="%s"]/plugin[@name="%s"]', $type, $name));

        $this->assertSame(1, $plugins->length, "$name must be declared once on $type");
        $plugin = $plugins->item(0);
        $this->assertSame($pluginClass, $plugin->getAttribute('type'));
        $this->assertNotSame('true', $plugin->getAttribute('disabled'));
    }
}
