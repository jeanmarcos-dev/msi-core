<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Plugin\ImportExport;

use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\ImportExport\Model\Import;
use Magento\InventoryAdjustment\Model\Actor;
use Magento\InventoryAdjustment\Model\ActorResolver;
use Magento\InventoryAdjustment\Model\AdjustmentMetadata;
use Magento\InventoryAdjustment\Model\AdjustmentOrigin;
use Magento\InventoryAdjustmentApi\Api\AdjustmentContextInterface;
use Magento\InventoryAdjustmentApi\Model\ActorType;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;

class ImportOriginPlugin
{
    /**
     * @param AdjustmentContextInterface $context
     * @param AdjustmentOrigin $origin
     * @param ActorResolver $actorResolver
     * @param IdentityGeneratorInterface $identityGenerator
     */
    public function __construct(
        private readonly AdjustmentContextInterface $context,
        private readonly AdjustmentOrigin $origin,
        private readonly ActorResolver $actorResolver,
        private readonly IdentityGeneratorInterface $identityGenerator
    ) {
    }

    /**
     * Record every stock change of an import run under one import id
     *
     * @param Import $subject
     * @param callable $proceed
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundImportSource(Import $subject, callable $proceed): bool
    {
        $user = $this->actorResolver->resolve();
        return $this->origin->run(
            new Actor(ActorType::Import, $user->id, $user->label),
            $this->identityGenerator->generateId(),
            fn () => $this->context->run(new AdjustmentMetadata(AdjustmentReason::Import), $proceed)
        );
    }
}
