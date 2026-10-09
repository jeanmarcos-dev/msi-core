<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

class AdjustmentOrigin implements ResetAfterRequestInterface
{
    /**
     * @var array
     */
    private array $stack = [];

    /**
     * Run an operation on behalf of an actor and a correlation id
     *
     * @param Actor $actor
     * @param string|null $requestId
     * @param callable $operation
     * @return mixed
     */
    public function run(Actor $actor, ?string $requestId, callable $operation): mixed
    {
        $this->stack[] = ['actor' => $actor, 'request_id' => $requestId];
        try {
            return $operation();
        } finally {
            array_pop($this->stack);
        }
    }

    /**
     * Actor of the innermost running operation
     *
     * @return Actor|null
     */
    public function getActor(): ?Actor
    {
        return $this->getCurrent()['actor'] ?? null;
    }

    /**
     * Correlation id of the innermost running operation
     *
     * @return string|null
     */
    public function getRequestId(): ?string
    {
        return $this->getCurrent()['request_id'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function _resetState(): void
    {
        $this->stack = [];
    }

    /**
     * Innermost running origin
     *
     * @return array|null
     */
    private function getCurrent(): ?array
    {
        return $this->stack === [] ? null : $this->stack[array_key_last($this->stack)];
    }
}
