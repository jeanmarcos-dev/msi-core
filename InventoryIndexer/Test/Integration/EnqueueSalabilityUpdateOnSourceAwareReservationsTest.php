<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Integration;

use Magento\Framework\Interception\PluginListInterface;
use Magento\InventorySales\Model\PlaceReservationsForSalesEvent;
use Magento\InventorySales\Model\SourceReservation\PlaceReservationsForSalesEventRouter;
use Magento\InventorySales\Model\SourceReservation\PlaceSourceAwareReservationsForSalesEvent;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Source level reservations route a reservation away from the class that used to place every one of
 * them, so the plugin that enqueues the salability recalculation has to sit on the router. Declared
 * on the routed-away class instead, enabling the feature silently stops the stock index from ever
 * learning that an order consumed the stock, and the storefront keeps offering a sold out product.
 */
class EnqueueSalabilityUpdateOnSourceAwareReservationsTest extends TestCase
{
    private const PLUGIN_CODE = 'schedule_reservation_place';

    /**
     * @var PluginListInterface
     */
    private $pluginList;

    protected function setUp(): void
    {
        $this->pluginList = Bootstrap::getObjectManager()->create(PluginListInterface::class);
    }

    public function testTheRouterEnqueuesTheSalabilityUpdate(): void
    {
        self::assertContains(
            self::PLUGIN_CODE,
            $this->getAfterPlugins(PlaceReservationsForSalesEventRouter::class),
            'Reservations placed through the router must enqueue the salability recalculation.'
        );
    }

    /**
     * The router delegates to one implementation or the other, so a plugin left on either of them
     * would fire for only half of the installations.
     *
     * @return void
     */
    public function testNeitherImplementationCarriesThePluginOnItsOwn(): void
    {
        self::assertNotContains(self::PLUGIN_CODE, $this->getAfterPlugins(PlaceReservationsForSalesEvent::class));
        self::assertNotContains(
            self::PLUGIN_CODE,
            $this->getAfterPlugins(PlaceSourceAwareReservationsForSalesEvent::class)
        );
    }

    /**
     * After plugin codes registered for the execute method of a type.
     *
     * @param string $type
     * @return array
     */
    private function getAfterPlugins(string $type): array
    {
        $plugins = $this->pluginList->getNext($type, 'execute');

        return $plugins[\Magento\Framework\Interception\DefinitionInterface::LISTENER_AFTER] ?? [];
    }
}
