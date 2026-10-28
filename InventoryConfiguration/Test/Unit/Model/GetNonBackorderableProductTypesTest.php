<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Test\Unit\Model;

use Magento\InventoryConfiguration\Model\GetNonBackorderableProductTypes;
use PHPUnit\Framework\TestCase;

class GetNonBackorderableProductTypesTest extends TestCase
{
    public function testATypeSetToNullIsTakenOutOfTheList(): void
    {
        $types = new GetNonBackorderableProductTypes(['virtual' => 'virtual', 'downloadable' => null]);

        self::assertSame(['virtual'], $types->execute());
    }

    public function testTheModuleDeclaresVirtualAndDownloadable(): void
    {
        $diXml = simplexml_load_file(__DIR__ . '/../../../etc/di.xml');
        $items = $diXml->xpath(
            '//type[@name="Magento\InventoryConfiguration\Model\GetNonBackorderableProductTypes"]'
            . '/arguments/argument[@name="types"]/item'
        );

        self::assertSame(['virtual', 'downloadable'], array_map('strval', $items));
    }
}
