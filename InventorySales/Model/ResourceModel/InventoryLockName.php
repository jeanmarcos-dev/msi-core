<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Config\ConfigOptionsListConstants;

/**
 * Inventory advisory lock names: within MySQL's 64-character limit and unique per installation.
 */
class InventoryLockName
{
    public const SOURCE = 'inv_src_';
    public const STOCK = 'inv_stk_';

    /**
     * @var string|null
     */
    private ?string $prefix = null;

    /**
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(
        private readonly DeploymentConfig $deploymentConfig
    ) {
    }

    /**
     * Build the lock name of a family from the installation prefix and the given parts.
     *
     * @param string $family
     * @param string[] $parts
     * @return string
     */
    public function build(string $family, string ...$parts): string
    {
        $key = '';
        foreach ([$this->prefix(), ...$parts] as $value) {
            $key .= strlen($value) . ':' . $value;
        }

        // phpcs:ignore Magento2.Security.InsecureFunction
        return $family . md5($key);
    }

    /**
     * The database name of the default connection, which tells two installations on one server apart.
     *
     * @return string
     */
    private function prefix(): string
    {
        if ($this->prefix === null) {
            $this->prefix = (string)$this->deploymentConfig->get(
                ConfigOptionsListConstants::CONFIG_PATH_DB_CONNECTION_DEFAULT
                . '/' . ConfigOptionsListConstants::KEY_NAME,
                ''
            );
        }

        return $this->prefix;
    }
}
