<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Inventory\Model\SourceItem\Command\Handler;

use Exception;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Validation\ValidationResultFactory;
use Magento\Inventory\Model\IsProductAssignedToStock\CacheStorage;
use Magento\Inventory\Model\ResourceModel\GetCanonicalSourceCodes;
use Magento\Inventory\Model\ResourceModel\SourceItem\SaveMultiple;
use Magento\Inventory\Model\SourceItem\Validator\SourceItemsValidator;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Psr\Log\LoggerInterface;

/**
 * Save multiple source items service.
 */
class SourceItemsSaveHandler
{
    /**
     * @var GetCanonicalSourceCodes
     */
    private readonly GetCanonicalSourceCodes $getCanonicalSourceCodes;

    /**
     * @var ValidationResultFactory
     */
    private readonly ValidationResultFactory $validationResultFactory;

    /**
     * @param SourceItemsValidator $sourceItemsValidator
     * @param SaveMultiple $saveMultiple
     * @param LoggerInterface $logger
     * @param CacheStorage $isProductAssignedToStockCacheStorage
     * @param GetCanonicalSourceCodes|null $getCanonicalSourceCodes
     * @param ValidationResultFactory|null $validationResultFactory
     */
    public function __construct(
        private readonly SourceItemsValidator $sourceItemsValidator,
        private readonly SaveMultiple $saveMultiple,
        private readonly LoggerInterface $logger,
        private readonly CacheStorage $isProductAssignedToStockCacheStorage,
        ?GetCanonicalSourceCodes $getCanonicalSourceCodes = null,
        ?ValidationResultFactory $validationResultFactory = null
    ) {
        $this->getCanonicalSourceCodes = $getCanonicalSourceCodes
            ?? ObjectManager::getInstance()->get(GetCanonicalSourceCodes::class);
        $this->validationResultFactory = $validationResultFactory
            ?? ObjectManager::getInstance()->get(ValidationResultFactory::class);
    }

    /**
     * Save Multiple Source item data
     *
     * @param SourceItemInterface[] $sourceItems
     * @return void
     * @throws InputException
     * @throws ValidationException
     * @throws CouldNotSaveException
     */
    public function execute(array $sourceItems)
    {
        if (empty($sourceItems)) {
            throw new InputException(__('Input data is empty'));
        }

        $validationResult = $this->sourceItemsValidator->validate($sourceItems);
        if (!$validationResult->isValid()) {
            $error = current($validationResult->getErrors());
            throw new ValidationException(__('Validation Failed: ' . $error), null, 0, $validationResult);
        }

        $this->assertSourceCodesMatchStoredCase($sourceItems);

        try {
            $this->saveMultiple->execute($sourceItems);
            foreach ($sourceItems as $sourceItem) {
                $this->isProductAssignedToStockCacheStorage->delete((string) $sourceItem->getSku());
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new CouldNotSaveException(__('Could not save Source Item'), $e);
        }
    }

    /**
     * Reject source codes that differ from the stored source code only in case.
     *
     * @param SourceItemInterface[] $sourceItems
     * @return void
     * @throws ValidationException
     */
    private function assertSourceCodesMatchStoredCase(array $sourceItems): void
    {
        $sourceCodes = [];
        foreach ($sourceItems as $sourceItem) {
            $sourceCodes[] = (string) $sourceItem->getSourceCode();
        }
        $sourceCodes = array_unique($sourceCodes);

        $storedSourceCodes = $this->getCanonicalSourceCodes->execute($sourceCodes);
        $errors = [];
        foreach ($sourceCodes as $sourceCode) {
            $storedSourceCode = $storedSourceCodes[mb_strtolower($sourceCode)] ?? $sourceCode;
            if ($storedSourceCode !== $sourceCode) {
                $errors[] = __(
                    'Source code "%1" does not match the stored source code "%2".',
                    $sourceCode,
                    $storedSourceCode
                );
            }
        }

        if ($errors) {
            $validationResult = $this->validationResultFactory->create(['errors' => $errors]);
            throw new ValidationException(__('Validation Failed: ' . current($errors)), null, 0, $validationResult);
        }
    }
}
