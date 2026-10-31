<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySetupFixtureGenerator\Plugin\Setup\Model\FixtureGenerator\SqlCollector;

use Magento\Setup\Model\FixtureGenerator\SqlCollector;

class ExcludeWebsiteTablesPlugin
{
    private const WEBSITE_TABLES = ['catalog_product_index_website'];

    /**
     * Drop the tables written once per website.
     *
     * @param SqlCollector $subject
     * @param array $sql
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetSql(SqlCollector $subject, array $sql): array
    {
        return array_values(
            array_filter(
                $sql,
                static function (array $pattern) {
                    return !in_array($pattern[1], self::WEBSITE_TABLES, true);
                }
            )
        );
    }
}
