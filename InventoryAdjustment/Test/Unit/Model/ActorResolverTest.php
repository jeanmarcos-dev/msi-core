<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\IntegrationException;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\Integration;
use Magento\InventoryAdjustment\Model\ActorResolver;
use Magento\InventoryAdjustmentApi\Model\ActorType;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;
use PHPUnit\Framework\TestCase;

class ActorResolverTest extends TestCase
{
    public function testAdminCarriesItsUsername(): void
    {
        $actor = $this->resolver(UserContextInterface::USER_TYPE_ADMIN, 3)->resolve();

        self::assertSame(ActorType::Admin, $actor->type);
        self::assertSame('3', $actor->id);
        self::assertSame('jane', $actor->label);
    }

    public function testIntegrationCarriesItsName(): void
    {
        $actor = $this->resolver(UserContextInterface::USER_TYPE_INTEGRATION, 8)->resolve();

        self::assertSame(ActorType::Integration, $actor->type);
        self::assertSame('8', $actor->id);
        self::assertSame('erp', $actor->label);
    }

    public function testMissingIntegrationKeepsTheIdWithoutALabel(): void
    {
        $actor = $this->resolver(UserContextInterface::USER_TYPE_INTEGRATION, 99)->resolve();

        self::assertSame('99', $actor->id);
        self::assertNull($actor->label);
    }

    public function testCustomerCarriesOnlyItsId(): void
    {
        $actor = $this->resolver(UserContextInterface::USER_TYPE_CUSTOMER, 5)->resolve();

        self::assertSame(ActorType::Customer, $actor->type);
        self::assertSame('5', $actor->id);
        self::assertNull($actor->label);
    }

    public function testNoUserIsTheSystem(): void
    {
        $cases = [[null, null], [UserContextInterface::USER_TYPE_GUEST, null], [UserContextInterface::USER_TYPE_ADMIN, null]];
        foreach ($cases as [$type, $id]) {
            $actor = $this->resolver($type, $id)->resolve();
            self::assertSame(ActorType::System, $actor->type);
            self::assertNull($actor->id);
        }
    }

    private function resolver(?int $userType, ?int $userId): ActorResolver
    {
        $userContext = $this->createMock(UserContextInterface::class);
        $userContext->method('getUserType')->willReturn($userType);
        $userContext->method('getUserId')->willReturn($userId);

        $user = $this->createMock(User::class);
        $user->method('getUserName')->willReturn('jane');
        $userFactory = $this->createMock(UserFactory::class);
        $userFactory->method('create')->willReturn($user);

        $integration = $this->createMock(Integration::class);
        $integration->method('getData')->with(Integration::NAME)->willReturn('erp');
        $integrationService = $this->createMock(IntegrationServiceInterface::class);
        $integrationService->method('get')->willReturnCallback(
            fn ($id) => $id === 8 ? $integration : throw new IntegrationException(__('missing'))
        );

        return new ActorResolver(
            $userContext,
            $userFactory,
            $this->createMock(UserResource::class),
            $integrationService
        );
    }
}
