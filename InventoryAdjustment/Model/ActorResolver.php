<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\Integration;
use Magento\InventoryAdjustmentApi\Model\ActorType;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\UserFactory;

class ActorResolver
{
    /**
     * @param UserContextInterface $userContext
     * @param UserFactory $userFactory
     * @param UserResource $userResource
     * @param IntegrationServiceInterface $integrationService
     */
    public function __construct(
        private readonly UserContextInterface $userContext,
        private readonly UserFactory $userFactory,
        private readonly UserResource $userResource,
        private readonly IntegrationServiceInterface $integrationService
    ) {
    }

    /**
     * Who is changing the stock in this request
     *
     * @return Actor
     */
    public function resolve(): Actor
    {
        $userType = $this->userContext->getUserType();
        return $this->forUser(
            $userType === null ? null : (int)$userType,
            (int)$this->userContext->getUserId()
        );
    }

    /**
     * Actor for a given user
     *
     * @param int|null $userType
     * @param int $userId
     * @return Actor
     */
    public function forUser(?int $userType, int $userId): Actor
    {
        if ($userId === 0) {
            return new Actor(ActorType::System);
        }

        return match ($userType) {
            UserContextInterface::USER_TYPE_ADMIN => new Actor(
                ActorType::Admin,
                (string)$userId,
                $this->getAdminUsername($userId)
            ),
            UserContextInterface::USER_TYPE_INTEGRATION => new Actor(
                ActorType::Integration,
                (string)$userId,
                $this->getIntegrationName($userId)
            ),
            UserContextInterface::USER_TYPE_CUSTOMER => new Actor(ActorType::Customer, (string)$userId),
            default => new Actor(ActorType::System),
        };
    }

    /**
     * Username of an admin user
     *
     * @param int $userId
     * @return string|null
     */
    private function getAdminUsername(int $userId): ?string
    {
        $user = $this->userFactory->create();
        $this->userResource->load($user, $userId);

        return $user->getUserName() ?: null;
    }

    /**
     * Name of an integration
     *
     * @param int $integrationId
     * @return string|null
     */
    private function getIntegrationName(int $integrationId): ?string
    {
        try {
            return $this->integrationService->get($integrationId)->getData(Integration::NAME) ?: null;
        } catch (LocalizedException) {
            return null;
        }
    }
}
