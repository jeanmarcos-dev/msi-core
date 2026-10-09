<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Plugin\ImportExport;

use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\ImportExport\Model\Import;
use Magento\InventoryAdjustment\Model\Actor;
use Magento\InventoryAdjustment\Model\ActorResolver;
use Magento\InventoryAdjustment\Model\AdjustmentContext;
use Magento\InventoryAdjustment\Model\AdjustmentOrigin;
use Magento\InventoryAdjustment\Plugin\ImportExport\ImportOriginPlugin;
use Magento\InventoryAdjustmentApi\Model\ActorType;
use PHPUnit\Framework\TestCase;

class ImportOriginPluginTest extends TestCase
{
    public function testAnImportRunsAsAnImportOfTheCurrentUserWithItsOwnId(): void
    {
        $this->assertImportRun(new Actor(ActorType::Admin, '3', 'jane'), '3', 'jane');
    }

    public function testAnImportFromTheCommandLineHasNoUser(): void
    {
        $this->assertImportRun(new Actor(ActorType::System), null, null);
    }

    public function testWhatTheImportReturnsIsPassedOnUntouched(): void
    {
        $actorResolver = $this->createMock(ActorResolver::class);
        $actorResolver->method('resolve')->willReturn(new Actor(ActorType::System));

        $result = (new ImportOriginPlugin(
            new AdjustmentContext(),
            new AdjustmentOrigin(),
            $actorResolver,
            $this->createMock(IdentityGeneratorInterface::class)
        ))->aroundImportSource($this->createMock(Import::class), fn () => null);

        self::assertNull($result);
    }

    private function assertImportRun(Actor $current, ?string $id, ?string $label): void
    {
        $context = new AdjustmentContext();
        $origin = new AdjustmentOrigin();
        $actorResolver = $this->createMock(ActorResolver::class);
        $actorResolver->method('resolve')->willReturn($current);
        $identityGenerator = $this->createMock(IdentityGeneratorInterface::class);
        $identityGenerator->method('generateId')->willReturn('run-1');

        $seen = (new ImportOriginPlugin($context, $origin, $actorResolver, $identityGenerator))->aroundImportSource(
            $this->createMock(Import::class),
            function () use ($context, $origin, &$state): bool {
                $state = [$context->getCurrent()->getReason()->value, $origin->getActor(), $origin->getRequestId()];
                return true;
            }
        );

        self::assertTrue($seen);
        [$reason, $actor, $requestId] = $state;
        self::assertSame('import', $reason);
        self::assertSame(ActorType::Import, $actor->type);
        self::assertSame($id, $actor->id);
        self::assertSame($label, $actor->label);
        self::assertSame('run-1', $requestId);
    }
}
