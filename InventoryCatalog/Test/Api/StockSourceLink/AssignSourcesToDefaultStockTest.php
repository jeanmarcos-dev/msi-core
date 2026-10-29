<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Api\StockSourceLink;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Webapi\Rest\Request;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\WebapiAbstract;
use PHPUnit\Framework\Attributes\DataProvider;

class AssignSourcesToDefaultStockTest extends WebapiAbstract
{
    private const RESOURCE_PATH = '/V1/inventory/stock-source-links';
    private const SERVICE_NAME_SAVE = 'inventoryApiStockSourceLinksSaveV1';
    private const SERVICE_NAME_DELETE = 'inventoryApiStockSourceLinksDeleteV1';
    private const SERVICE_NAME_GET_LIST = 'inventoryApiGetStockSourceLinksV1';

    /**
     * @var array
     */
    private array $assignedLinks = [];

    protected function tearDown(): void
    {
        if ($this->assignedLinks) {
            $this->callLinksService(self::RESOURCE_PATH . '-delete', self::SERVICE_NAME_DELETE, $this->assignedLinks);
        }
        parent::tearDown();
    }

    /**
     * @magentoApiDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/sources.php
     * @param array $sourceCodes
     * @throws \Exception
     */
    #[DataProvider('sourceCodesProvider')]
    public function testAssignSourcesToDefaultStock(array $sourceCodes): void
    {
        $defaultSourceCode = Bootstrap::getObjectManager()->get(DefaultSourceProviderInterface::class)->getCode();
        $stockId = Bootstrap::getObjectManager()->get(DefaultStockProviderInterface::class)->getId();
        $links = [];
        foreach ($sourceCodes as $priority => $sourceCode) {
            $links[] = [
                StockSourceLinkInterface::STOCK_ID => $stockId,
                StockSourceLinkInterface::SOURCE_CODE => $sourceCode,
                StockSourceLinkInterface::PRIORITY => $priority + 1,
            ];
        }
        $this->assignedLinks = array_values(array_filter(
            $links,
            static fn (array $link): bool => $link[StockSourceLinkInterface::SOURCE_CODE] !== $defaultSourceCode
        ));

        $this->callLinksService(self::RESOURCE_PATH, self::SERVICE_NAME_SAVE, $links);

        $priorities = array_column(
            $this->getStockSourceLinks($stockId)['items'],
            StockSourceLinkInterface::PRIORITY,
            StockSourceLinkInterface::SOURCE_CODE
        );
        foreach ($links as $link) {
            self::assertArrayHasKey($link[StockSourceLinkInterface::SOURCE_CODE], $priorities);
            self::assertEquals(
                $link[StockSourceLinkInterface::PRIORITY],
                $priorities[$link[StockSourceLinkInterface::SOURCE_CODE]]
            );
        }
    }

    public static function sourceCodesProvider(): array
    {
        $defaultSourceCode = Bootstrap::getObjectManager()->get(DefaultSourceProviderInterface::class)->getCode();
        return [
            'default_and_additional_source' => [[$defaultSourceCode, 'eu-2']],
            'additional_source_only' => [['eu-1']],
            'several_additional_sources' => [['eu-1', 'eu-2', 'eu-3']],
        ];
    }

    private function callLinksService(string $resourcePath, string $serviceName, array $links): void
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => $resourcePath,
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => $serviceName,
                'operation' => $serviceName . 'Execute',
            ],
        ];
        $this->_webApiCall($serviceInfo, ['links' => $links]);
    }

    private function getStockSourceLinks(int $stockId): array
    {
        $requestData = [
            'searchCriteria' => [
                SearchCriteria::FILTER_GROUPS => [
                    [
                        'filters' => [
                            [
                                'field' => StockSourceLinkInterface::STOCK_ID,
                                'value' => $stockId,
                                'condition_type' => 'eq',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH . '?' . http_build_query($requestData),
                'httpMethod' => Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_NAME_GET_LIST,
                'operation' => self::SERVICE_NAME_GET_LIST . 'Execute',
            ],
        ];

        return (TESTS_WEB_API_ADAPTER === self::ADAPTER_REST)
            ? $this->_webApiCall($serviceInfo)
            : $this->_webApiCall($serviceInfo, $requestData);
    }
}
