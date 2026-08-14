# MSI Core

**Multi-Source Inventory as the only inventory system in Magento Open Source.**

Upstream MSI ships with a second inventory system underneath it. The Default Stock is not a
stock: `inventory_stock_1` is a MySQL view over `cataloginventory_stock_status`, the MSI indexer
skips stock 1 on purpose, and composite salability for that stock is computed by the legacy
CatalogInventory indexer. Around fifty `if ($stockId === $defaultStockId)` branches across twenty
modules keep the two worlds apart, each with its own data path.

`msi-core` removes that split. Stock 1 is indexed, configured, edited and deleted like a stock you
create yourself, the composite indexers cover it, and the `cataloginventory_*` tables are neither
read nor written by MSI.

One code path instead of two means a defect can no longer be fixed on one side of the `if` and
stay open on the other, and a new feature is written and tested once.

---

## Which line do I install?

The same fork is distributed as two packages. This repository holds `msi-core`, which is a schema
upgrade; its sibling [`jeanmarcos-dev/inventory`](https://github.com/jeanmarcos-dev/inventory) holds
three drop-in lines that replace the MSI shipped with your Magento version and change nothing else.

| Package | Repository | Magento | PHP | Drop-in |
|---|---|---|---|---|
| `jeanmarcos/inventory:2.4.7.*` | `jeanmarcos-dev/inventory` | 2.4.7 | 8.1 – 8.3 | yes |
| `jeanmarcos/inventory:2.4.8.*` | `jeanmarcos-dev/inventory` | 2.4.8 | 8.2 – 8.4 | yes |
| `jeanmarcos/inventory:2.4.9.*` | `jeanmarcos-dev/inventory` | 2.4.9 | 8.3 – 8.5 | yes |
| **`jeanmarcos/msi-core:^1.0`** | **`jeanmarcos-dev/msi-core`** | **2.4.9** | **8.3 – 8.5** | **no** |

Mage-OS is supported on the 2.4.9 line and therefore on `msi-core`: the package replaces both the
`magento/*` and the `mage-os/*` MSI packages. Mage-OS 3.x tracks Magento 2.4.9.

**Pick `jeanmarcos/inventory`** if you want the curated fixes and nothing else to change. Composer
swaps the package, the module names and namespaces stay identical, and no migration runs.

**Pick `msi-core`** if you want one inventory system instead of two, and can accept a schema
migration with downtime and a documented break for code that reads `cataloginventory_*` by raw SQL.

> The two packages provide the same modules, so a store runs one or the other, never both.
> `msi-core` declares a `conflict` on `jeanmarcos/inventory`, so Composer refuses a tree holding
> both rather than producing a broken one. Migrating is
> `composer remove jeanmarcos/inventory && composer require jeanmarcos/msi-core:^1.0`.

## Install

```jsonc
// composer.json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/jeanmarcos-dev/msi-core" }
    ]
}
```

```bash
composer require "jeanmarcos/msi-core:^1.0"
bin/magento maintenance:enable
bin/magento setup:upgrade
bin/magento indexer:reindex inventory catalog_product_price catalogsearch_fulltext
bin/magento cache:flush
bin/magento maintenance:disable
```

**Read [`UPGRADE.md`](UPGRADE.md) before running this on a store with data.** The upgrade is a
maintenance operation with no downgrade path, and it is where the breaking changes are described.

The `magento/framework` constraint gates the install: Composer will not place `msi-core` on a
Magento version it was not built for.

## After installing: run the salability consumer

```bash
bin/magento queue:consumers:start inventory.reservations.updateSalabilityStatus
```

This is a **requirement, not a tuning recommendation.** Placing a reservation publishes a message
that refreshes the index; if nothing consumes the queue, the table grows without bound and every
subsequent reservation pays for it. Measured on the reservation path: 14.2 ms with the queue
drained, 16.5 ms at 2568 pending rows, and the cost keeps climbing from there. The standard
`consumers_runner` cron covers this if you have it enabled.

If you also enable the storefront stock visualizer with the queue purge strategy, run
`inventory.stockvisualizer.purge` as well.

## What else this fork changes

Beyond the Default Stock work, every line carries curated fixes, and the 2.4.9 line adds opt-in
features: source-level reservations, salable quantity broken down by source, and a storefront stock
visualizer. [`DISTRIBUTION.md`](DISTRIBUTION.md) describes each one, with its configuration path and
its caveats.

## Documentation

| | |
|---|---|
| [`UPGRADE.md`](UPGRADE.md) | Breaking changes, migration steps, what happens to the legacy tables, how to detect third-party code still writing them |
| [`DISTRIBUTION.md`](DISTRIBUTION.md) | What differs from upstream, feature by feature |
| [`VERSIONING.md`](VERSIONING.md) | What a version number promises, and what counts as public API |
| [`CHANGELOG.md`](CHANGELOG.md) | Every change, with the version that carries it on each line |
| [`InventoryStockVisualizer/README.md`](InventoryStockVisualizer/README.md) | Configuration and cache invalidation of the availability panel |

For MSI itself — concepts, REST API, admin walkthroughs — Adobe's documentation still applies,
since the module names, namespaces and service contracts are unchanged:

- [Adobe Commerce inventory REST reference](https://developer.adobe.com/commerce/webapi/rest/inventory/)
- [Upstream MSI wiki](https://github.com/magento/inventory/wiki)

## Contributing

Issues and pull requests are welcome at
[jeanmarcos-dev/msi-core](https://github.com/jeanmarcos-dev/msi-core). Report anything specific to
the drop-in lines at [jeanmarcos-dev/inventory](https://github.com/jeanmarcos-dev/inventory)
instead; the two repositories share this codebase, and a fix that applies to both is ported.

Commits follow [Conventional Commits](https://www.conventionalcommits.org/) with a single-line
subject; a git hook enforces it. Changes to reservations, salability or the indexers must come with
the stress suite result. See [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md).

## License and attribution

This is a modified derivative of [`magento/inventory`](https://github.com/magento/inventory)
(Copyright Adobe), redistributed under **AFL-3.0**. Files derived from upstream keep Adobe's
original copyright and license notices; files original to this fork carry their own copyright under
OSL-3.0 / AFL-3.0. See [`LICENSE_AFL.txt`](LICENSE_AFL.txt) and [`NOTICE`](NOTICE).

**Not affiliated with, endorsed by, or supported by Adobe.** Magento and Adobe Commerce are
trademarks of Adobe Inc.
