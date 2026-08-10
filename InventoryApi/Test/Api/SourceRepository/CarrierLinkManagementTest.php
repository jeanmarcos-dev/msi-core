<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryApi\Test\Api\SourceRepository;

use Magento\Framework\Webapi\Rest\Request;
use Magento\InventoryApi\Api\Data\SourceCarrierLinkInterface;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\TestFramework\TestCase\WebapiAbstract;

class CarrierLinkManagementTest extends WebapiAbstract
{
    /**#@+
     * Service constants
     */
    private const RESOURCE_PATH = '/V1/inventory/sources';
    private const SERVICE_NAME = 'inventoryApiSourceRepositoryV1';
    /**#@-*/

    /**
     * @param array $carrierData
     * @param array $expectedErrorData
     */
    public function testCarrierLinksValidationUseGlobalConfiguration()
    {
        $carrierData = [
            SourceInterface::SOURCE_CODE => 'source-code-1',
            SourceInterface::NAME => 'source-name-1',
            SourceInterface::POSTCODE => 'source-postcode',
            SourceInterface::COUNTRY_ID => 'US',
            SourceInterface::USE_DEFAULT_CARRIER_CONFIG => 1,
            SourceInterface::CARRIER_LINKS => [
                [
                    SourceCarrierLinkInterface::CARRIER_CODE => 'ups',
                    SourceCarrierLinkInterface::POSITION => 100,
                ],
                [
                    SourceCarrierLinkInterface::CARRIER_CODE => 'usps',
                    SourceCarrierLinkInterface::POSITION => 200,
                ],
            ],
        ];
        $expectedErrorData = [
            'message' => 'Validation Failed',
            'errors' => [
                [
                    'message' =>
                        'You can\'t configure "%field" because you have chosen Global Shipping configuration.',
                    'parameters' => [
                        'field' => SourceInterface::CARRIER_LINKS,
                    ],
                ],
            ],
        ];

        $this->validate($carrierData, $expectedErrorData);
    }

    /**
     * @param array $carrierData
     * @param array $expectedErrorData
     * @return void
     */
    private function validate(array $carrierData, array $expectedErrorData): void
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH,
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_NAME,
                'operation' => self::SERVICE_NAME . 'Save',
            ],
        ];

        try {
            $this->_webApiCall($serviceInfo, ['source' => $carrierData]);
            $this->fail('Expected throwing exception');
        } catch (\Exception $e) {
            if (TESTS_WEB_API_ADAPTER === self::ADAPTER_REST) {
                self::assertEquals($expectedErrorData, $this->processRestExceptionResult($e));
                self::assertEquals(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST, $e->getCode());
            } elseif (TESTS_WEB_API_ADAPTER === self::ADAPTER_SOAP) {
                $this->assertInstanceOf('SoapFault', $e);
                $expectedWrappedErrors = [];
                foreach ($expectedErrorData['errors'] as $error) {
                    // @see \Magento\TestFramework\TestCase\WebapiAbstract::getActualWrappedErrors()
                    $expectedWrappedErrors[] = [
                        'message' => $error['message'],
                        'params' => $error['parameters'],
                    ];
                }
                $this->checkSoapFault(
                    $e,
                    $expectedErrorData['message'],
                    'env:Sender',
                    [],
                    $expectedWrappedErrors
                );
            } else {
                throw $e;
            }
        }
    }
}
