<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Plugin\AsynchronousOperations;

use InvalidArgumentException;
use Magento\AsynchronousOperations\Api\Data\OperationInterface;
use Magento\AsynchronousOperations\Model\ConfigInterface as AsyncConfig;
use Magento\AsynchronousOperations\Model\OperationProcessor;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\MessageQueue\MessageEncoder;
use Magento\InventoryAdjustment\Model\Actor;
use Magento\InventoryAdjustment\Model\ActorResolver;
use Magento\InventoryAdjustment\Model\AdjustmentOrigin;
use Magento\InventoryAdjustment\Model\ResourceModel\GetBulkUser;
use Magento\InventoryAdjustment\Plugin\AsynchronousOperations\BulkOperationOriginPlugin;
use Magento\InventoryAdjustmentApi\Model\ActorType;
use PHPUnit\Framework\TestCase;

class BulkOperationOriginPluginTest extends TestCase
{
    /**
     * @var AdjustmentOrigin
     */
    private AdjustmentOrigin $origin;

    protected function setUp(): void
    {
        $this->origin = new AdjustmentOrigin();
    }

    public function testTheBulkUserAndUuidAreTheOrigin(): void
    {
        $admin = new Actor(ActorType::Admin, '5', 'jane');

        $seen = $this->process(
            'bulk-1',
            ['user_type' => UserContextInterface::USER_TYPE_ADMIN, 'user_id' => 5],
            [[UserContextInterface::USER_TYPE_ADMIN, 5, $admin]]
        );

        self::assertSame([$admin, 'bulk-1'], $seen);
        self::assertNull($this->origin->getActor());
    }

    public function testABulkWithoutUserKeepsItsUuid(): void
    {
        $system = new Actor(ActorType::System);

        $seen = $this->process('bulk-2', null, [[null, 0, $system]]);

        self::assertSame([$system, 'bulk-2'], $seen);
    }

    public function testAMessageThatIsNotAnOperationRunsUntouched(): void
    {
        $encoder = $this->createMock(MessageEncoder::class);
        $encoder->method('decode')->willThrowException(new InvalidArgumentException('bad'));
        $getBulkUser = $this->createMock(GetBulkUser::class);
        $getBulkUser->expects(self::never())->method('execute');

        $seen = (new BulkOperationOriginPlugin(
            $encoder,
            $getBulkUser,
            $this->createMock(ActorResolver::class),
            $this->origin
        ))->aroundProcess(
            $this->createMock(OperationProcessor::class),
            fn (string $message) => [$message, $this->origin->getActor()],
            'garbage'
        );

        self::assertSame(['garbage', null], $seen);
    }

    private function process(string $bulkUuid, ?array $user, array $forUserMap): array
    {
        $operation = $this->createMock(OperationInterface::class);
        $operation->method('getBulkUuid')->willReturn($bulkUuid);
        $encoder = $this->createMock(MessageEncoder::class);
        $encoder->method('decode')->with(AsyncConfig::SYSTEM_TOPIC_NAME, 'message')->willReturn($operation);
        $getBulkUser = $this->createMock(GetBulkUser::class);
        $getBulkUser->method('execute')->with($bulkUuid)->willReturn($user);
        $actorResolver = $this->createMock(ActorResolver::class);
        $actorResolver->method('forUser')->willReturnMap($forUserMap);

        return (new BulkOperationOriginPlugin($encoder, $getBulkUser, $actorResolver, $this->origin))
            ->aroundProcess(
                $this->createMock(OperationProcessor::class),
                fn () => [$this->origin->getActor(), $this->origin->getRequestId()],
                'message'
            );
    }
}
