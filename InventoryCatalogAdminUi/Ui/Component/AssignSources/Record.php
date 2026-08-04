<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalogAdminUi\Ui\Component\AssignSources;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentInterface;
use Magento\Ui\Component\Container;

/**
 * Assigned sources record.
 */
class Record extends Container
{
    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @param ContextInterface $context
     * @param UiComponentInterface[] $components
     * @param array $data
     * @param AuthorizationInterface|null $authorization
     */
    public function __construct(
        ContextInterface $context,
        array $components = [],
        array $data = [],
        ?AuthorizationInterface $authorization = null
    ) {
        parent::__construct($context, $components, $data);
        $this->authorization = $authorization ?? ObjectManager::getInstance()->get(AuthorizationInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function prepare()
    {
        parent::prepare();

        $deleteConfig = $this->components['actionDelete']->getData('config');
        // Hide delete assigned sources button according to ACL resource.
        $deleteConfig['visible'] = $this->authorization->isAllowed('Magento_InventoryApi::stock_source_link');

        $this->components['actionDelete']->setData('config', $deleteConfig);
    }
}
