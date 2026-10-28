<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryShippingAdminUi\Test\Unit\Controller\Adminhtml\SourceSelection;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObject;
use Magento\InventoryShippingAdminUi\Controller\Adminhtml\SourceSelection\Validate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidateTest extends TestCase
{
    /**
     * @param array $items
     * @param array $expected
     * @return void
     */
    #[DataProvider('shipments')]
    public function testValidatesEachItemAgainstTheQuantityItCanShip(array $items, array $expected): void
    {
        self::assertSame($expected, $this->validate($items));
    }

    /**
     * @return array[]
     */
    public static function shipments(): array
    {
        $shipped = ['error' => false];
        $managedShort = ['error' => true, 'messages' => ['Qty of SRI-SIMPLE should be less or equal to 0']];

        return [
            'a mixed shipment posted as strings' => [
                [self::item('SRI-SIMPLE', 'true', 10), self::item('SRI-UNMANAGED', 'false', 0)],
                $shipped,
            ],
            'a mixed shipment posted as booleans' => [
                [self::item('SRI-SIMPLE', true, 10), self::item('SRI-UNMANAGED', false, 0)],
                $shipped,
            ],
            'a managed item short at the source, posted as a string' => [
                [self::item('SRI-SIMPLE', 'true', 0)],
                $managedShort,
            ],
            'a managed item short at the source, posted as a boolean' => [
                [self::item('SRI-SIMPLE', true, 0)],
                $managedShort,
            ],
        ];
    }

    private static function item(string $sku, string|bool $isManageStock, int $qtyAvailable): array
    {
        return [
            'sku' => $sku,
            'qtyToShip' => '1',
            'isManageStock' => $isManageStock,
            'sources' => [
                ['sourceCode' => 'sri_a', 'qtyAvailable' => (string)$qtyAvailable, 'qtyToDeduct' => '1'],
            ],
        ];
    }

    private function validate(array $items): array
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnMap([
            ['sourceCode', null, 'sri_a'],
            ['items', null, $items],
        ]);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);

        $data = [];
        $json = $this->createStub(Json::class);
        $json->method('setData')->willReturnCallback(function (DataObject $response) use (&$data, $json) {
            $data = $response->toArray();

            return $json;
        });
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);

        (new Validate($context, $jsonFactory))->execute();

        return $data;
    }
}
