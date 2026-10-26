<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\DeploymentConfig;
use Magento\InventorySales\Model\ResourceModel\InventoryLockName;
use PHPUnit\Framework\TestCase;

class InventoryLockNameTest extends TestCase
{
    private const MYSQL_LOCK_NAME_LIMIT = 64;

    public function testEveryFamilyFitsTheMysqlLimitWithTheLongestParts(): void
    {
        $builder = $this->builder(str_repeat('d', 64));
        $sku = str_repeat('s', 64);
        $names = [
            InventoryLockName::SOURCE => $builder->build(InventoryLockName::SOURCE, str_repeat('c', 255), $sku),
            InventoryLockName::STOCK => $builder->build(InventoryLockName::STOCK, (string)PHP_INT_MAX, $sku),
            InventoryLockName::CLAMP => $builder->build(InventoryLockName::CLAMP, (string)PHP_INT_MAX, $sku),
        ];

        foreach ($names as $family => $name) {
            self::assertStringStartsWith($family, $name);
            self::assertLessThanOrEqual(self::MYSQL_LOCK_NAME_LIMIT, strlen($name), $name);
        }
    }

    public function testPartsThatConcatenateAlikeGetDifferentNames(): void
    {
        $builder = $this->builder('shop');

        self::assertNotSame(
            $builder->build(InventoryLockName::SOURCE, 'ab', 'c'),
            $builder->build(InventoryLockName::SOURCE, 'a', 'bc')
        );
        self::assertNotSame(
            $builder->build(InventoryLockName::SOURCE, "a\0b", 'c'),
            $builder->build(InventoryLockName::SOURCE, 'a', "b\0c")
        );
    }

    public function testThePrefixCannotBeConfusedWithAPart(): void
    {
        self::assertNotSame(
            $this->builder('shop')->build(InventoryLockName::SOURCE, 'a', 'b'),
            $this->builder('shopa')->build(InventoryLockName::SOURCE, '', 'b')
        );
    }

    public function testDifferentInstallationsGetDifferentNames(): void
    {
        self::assertNotSame(
            $this->builder('shop_stg')->build(InventoryLockName::SOURCE, 'default', 'lk_s'),
            $this->builder('shop_prod')->build(InventoryLockName::SOURCE, 'default', 'lk_s')
        );
    }

    public function testTheSameInstallationAlwaysGetsTheSameName(): void
    {
        self::assertSame(
            $this->builder('shop')->build(InventoryLockName::SOURCE, 'default', 'lk_s'),
            $this->builder('shop')->build(InventoryLockName::SOURCE, 'default', 'lk_s')
        );
    }

    public function testFamiliesDoNotShareNames(): void
    {
        $builder = $this->builder('shop');

        self::assertNotSame(
            $builder->build(InventoryLockName::STOCK, '1', 'lk_s'),
            $builder->build(InventoryLockName::CLAMP, '1', 'lk_s')
        );
    }

    public function testThePrefixIsTheDatabaseNameReadOnce(): void
    {
        $deploymentConfig = $this->createMock(DeploymentConfig::class);
        $deploymentConfig->expects(self::once())
            ->method('get')
            ->with('db/connection/default/dbname', '')
            ->willReturn('shop');
        $builder = new InventoryLockName($deploymentConfig);

        $first = $builder->build(InventoryLockName::SOURCE, 'default', 'lk_s');
        $second = $builder->build(InventoryLockName::SOURCE, 'default', 'lk_s');

        self::assertSame($first, $second);
        self::assertSame($this->builder('shop')->build(InventoryLockName::SOURCE, 'default', 'lk_s'), $first);
    }

    public function testAnEmptyDatabaseNameStillGivesAValidName(): void
    {
        self::assertMatchesRegularExpression(
            '/^inv_src_[0-9a-f]{32}$/',
            $this->builder('')->build(InventoryLockName::SOURCE, 'default', 'lk_s')
        );
    }

    private function builder(string $dbname): InventoryLockName
    {
        $deploymentConfig = $this->createStub(DeploymentConfig::class);
        $deploymentConfig->method('get')->willReturn($dbname);

        return new InventoryLockName($deploymentConfig);
    }
}
