---
title: Why msi-core
description: The two inventory systems inside upstream MSI, what msi-core does about them, and when to choose it over the drop-in package.
---

## Two inventory systems in one

Upstream Multi-Source Inventory ships with a second inventory system underneath it. The Default
Stock is not a stock at all:

- `inventory_stock_1` is a MySQL view over `cataloginventory_stock_status`;
- the MSI indexer skips stock 1 on purpose;
- the salability of composite products for that stock is computed by the legacy CatalogInventory
  indexer instead of the MSI ones.

Around fifty `if ($stockId === $defaultStockId)` branches across twenty modules keep the two worlds
apart, each with its own data path. That split is the root of a whole class of defects: a bug fixed
on one side of the `if` stays open on the other. It also forces every new feature to be written and
tested twice.

## What msi-core does about it

msi-core removes the split. Stock 1 is indexed, configured, edited and deleted like a stock you
create yourself, the composite indexers cover it, and the `cataloginventory_*` tables are neither
read nor written by MSI. One code path instead of two means a defect can no longer be fixed on one
side and stay open on the other, and a new feature is written and tested once.

On top of that single code path, msi-core adds features upstream does not have:

- [source-level reservations](/msi-core/features/source-reservations/), which close the
  cross-stock oversell gap;
- [salable quantity broken down by source](/msi-core/features/salable-by-source/) in the product
  form and grid;
- an [adjustment history](/msi-core/features/adjustment-history/) that records every change to a
  source item;
- a [storefront stock visualizer](/msi-core/features/stock-visualizer/);
- [detection of writes](/msi-core/features/legacy-write-detection/) that still target the frozen
  legacy tables.

## What it costs

msi-core is a schema upgrade, not a package swap. It adds a table, migrates data into it and stops
writing two core tables. The upgrade needs a maintenance window, has no downgrade path, and breaks
code that reads `cataloginventory_stock_item` or `cataloginventory_stock_status` with raw SQL.
[Upgrade from Magento MSI](/msi-core/start/upgrade/) describes the break and the migration in full.

## msi-core or the drop-in package

The same fork is distributed as two packages from two repositories.

| Package | Repository | Magento | PHP | Drop-in | Maintained |
|---|---|---|---|---|---|
| `jeanmarcos/inventory:2.4.7.*` | `jeanmarcos-dev/inventory` | 2.4.7 | 8.1 – 8.3 | yes | no |
| `jeanmarcos/inventory:2.4.8.*` | `jeanmarcos-dev/inventory` | 2.4.8 | 8.2 – 8.4 | yes | no |
| `jeanmarcos/inventory:2.4.9.*` | `jeanmarcos-dev/inventory` | 2.4.9 | 8.3 – 8.5 | yes | no |
| **`jeanmarcos/msi-core:^1.0`** | **`jeanmarcos-dev/msi-core`** | **2.4.9** | **8.3 – 8.5** | **no** | **yes** |

**[`jeanmarcos/inventory`](https://github.com/jeanmarcos-dev/inventory) is no longer maintained.**
It was the drop-in package: Composer swapped the package, the module names and namespaces stayed
identical, and no migration ran. Its releases from 2.4.7.8, 2.4.8.9 and 2.4.9.7 on fail every order
with managed stock on MySQL, because the per-source lock name is longer than MySQL accepts
([msi-core#10](https://github.com/jeanmarcos-dev/msi-core/issues/10)). On 2.4.7 and 2.4.8, Magento's
own MSI is the alternative.

**msi-core** gives you one inventory system instead of two, at the cost of a schema migration with
downtime and a documented break for code that reads `cataloginventory_*` by raw SQL.

They are two repositories rather than two branches of one because Packagist serves every branch of a
repository under a single package name: a second package identity needs a second repository. The
codebase is shared.

## How the package works

msi-core keeps the original `Magento_Inventory*` module names and `Magento\Inventory*` namespaces.
It uses Composer's `replace` directive to provide the 73 open-source MSI modules that ship with
Magento, plus its own modules. The `replace` covers the `mage-os/*` packages as well as the
`magento/*` ones, which is what makes it install on Mage-OS without further configuration. Mage-OS
3.x tracks Magento 2.4.9.

The build pins `magento/framework` to its target line, so Composer only installs it on a matching
Magento version.

The two packages replace the same modules and therefore cannot coexist. msi-core declares an
explicit `conflict` on `jeanmarcos/inventory`, so Composer refuses a tree holding both rather than
producing a broken one.
