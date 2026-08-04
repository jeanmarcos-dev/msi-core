<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Model;

use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryIndexer\Model\GetProductsIdsToProcess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GetProductsIdsToProcessTest extends TestCase
{
    /**
     * @var GetProductsIdsToProcess
     */
    private GetProductsIdsToProcess $model;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $getProductIdsBySkus = $this->createMock(GetProductIdsBySkusInterface::class);
        $getProductIdsBySkus
            ->expects($this->any())->method('execute')->willReturnCallback(function ($skus) {
                $result = [];

                foreach ($skus as $sku) {
                    $result[$sku] = 1;
                }

                return $result;
            });
        $this->model = new GetProductsIdsToProcess($getProductIdsBySkus);
    }

    /**
     * @param array $before
     * @param array $after
     * @param array $expectedResult
     * @return void
     */
    #[DataProvider('dataProvider')]
    public function testExecute(array $before, array $after, array $expectedResult): void
    {
        $actualResult = $this->model->execute($before, $after);

        $this->assertSame($expectedResult, $actualResult);
    }

    /**
     * Data provider for execute
     *
     * @return array[]
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public static function dataProvider(): array
    {
        return [
            'test with no difference' => [
                'before' => [
                    'sku1' => [
                        3 => true,
                    ],
                    'sku2' => [
                        5 => false
                    ]
                ],
                'after' => [
                    'sku1' => [
                        3 => true,
                    ],
                    'sku2' => [
                        5 => false
                    ]
                ],
                'expectedResult' => []
            ],

            'test adding to stock' => [
                'before' => [
                    'sku1' => [
                        3 => true,
                    ],
                ],
                'after' => [
                    'sku1' => [
                        3 => true,
                        5 => true
                    ],
                ],
                'expectedResult' => [
                    'sku1' => 1
                ]
            ],
            'test removing from stock' => [
                'before' => [
                    'sku1' => [
                        3 => true,
                        5 => true
                    ],
                ],
                'after' => [
                    'sku1' => [
                        3 => true,
                    ],
                ],
                'expectedResult' => [
                    'sku1' => 1
                ]
            ],
            'test turn out of stock' => [
                'before' => [
                    'sku1' => [
                        3 => true,
                    ],
                ],
                'after' => [
                    'sku1' => [
                        3 => false,
                    ],
                ],
                'expectedResult' => [
                    'sku1' => 1
                ]
            ],
            'test turn in stock' => [
                'before' => [
                    'sku1' => [
                        3 => false,
                    ],
                ],
                'after' => [
                    'sku1' => [
                        3 => true,
                    ],
                ],
                'expectedResult' => [
                    'sku1' => 1
                ]
            ],
            'test adding sku' => [
                'before' => [
                ],
                'after' => [
                    'sku1' => [
                        3 => true,
                    ],
                ],
                'expectedResult' => [
                    'sku1' => 1
                ]
            ],
            'test removing sku' => [
                'before' => [
                    'sku1' => [
                        3 => true,
                    ],
                ],
                'after' => [
                ],
                'expectedResult' => [
                    'sku1' => 1
                ]
            ],
            'test default stock is treated like any other stock' => [
                'before' => [
                    'sku1' => [
                        1 => true,
                    ],
                ],
                'after' => [
                    'sku1' => [
                        1 => true,
                    ],
                ],
                'expectedResult' => []
            ]
        ];
    }
}
