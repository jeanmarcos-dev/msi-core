<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Plugin\AsynchronousOperations;

use Magento\AsynchronousOperations\Api\Data\OperationInterface;
use Magento\AsynchronousOperations\Model\ConfigInterface as AsyncConfig;
use Magento\AsynchronousOperations\Model\OperationProcessor;
use Magento\Framework\MessageQueue\MessageEncoder;
use Magento\InventoryAdjustment\Model\ActorResolver;
use Magento\InventoryAdjustment\Model\AdjustmentOrigin;
use Magento\InventoryAdjustment\Model\ResourceModel\GetBulkUser;
use Throwable;

class BulkOperationOriginPlugin
{
    /**
     * @param MessageEncoder $messageEncoder
     * @param GetBulkUser $getBulkUser
     * @param ActorResolver $actorResolver
     * @param AdjustmentOrigin $origin
     */
    public function __construct(
        private readonly MessageEncoder $messageEncoder,
        private readonly GetBulkUser $getBulkUser,
        private readonly ActorResolver $actorResolver,
        private readonly AdjustmentOrigin $origin
    ) {
    }

    /**
     * Run an async operation on behalf of the user that scheduled its bulk
     *
     * @param OperationProcessor $subject
     * @param callable $proceed
     * @param string $encodedMessage
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundProcess(OperationProcessor $subject, callable $proceed, string $encodedMessage): mixed
    {
        $bulkUuid = $this->getBulkUuid($encodedMessage);
        if ($bulkUuid === null) {
            return $proceed($encodedMessage);
        }
        $user = $this->getBulkUser->execute($bulkUuid);
        return $this->origin->run(
            $this->actorResolver->forUser($user['user_type'] ?? null, $user['user_id'] ?? 0),
            $bulkUuid,
            fn () => $proceed($encodedMessage)
        );
    }

    /**
     * Bulk uuid of an encoded operation, or null when the message is not one
     *
     * @param string $encodedMessage
     * @return string|null
     */
    private function getBulkUuid(string $encodedMessage): ?string
    {
        try {
            $operation = $this->messageEncoder->decode(AsyncConfig::SYSTEM_TOPIC_NAME, $encodedMessage);
        } catch (Throwable) {
            return null;
        }
        if (!$operation instanceof OperationInterface) {
            return null;
        }
        return (string)$operation->getBulkUuid() ?: null;
    }
}
