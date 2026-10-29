# MSI Core

**Multi-Source Inventory as the only inventory system in Magento Open Source.**

**Documentation: [jeanmarcos-dev.github.io/msi-core](https://jeanmarcos-dev.github.io/msi-core/)**

Upstream MSI ships with a second inventory system underneath it. The Default Stock is not a
stock: `inventory_stock_1` is a MySQL view over `cataloginventory_stock_status`, the MSI indexer
skips stock 1 on purpose, and composite salability for that stock is computed by the legacy
CatalogInventory indexer. Around fifty `if ($stockId === $defaultStockId)` branches across twenty
modules keep the two worlds apart, each with its own data path.

`msi-core` removes that split. Stock 1 is indexed, configured, edited and deleted like a stock you
create yourself, the composite indexers cover it, and the `cataloginventory_*` tables are neither
read nor written by MSI. On top of that single code path it adds source-level reservations, salable
quantity broken down by source, an adjustment history of every stock change and a storefront stock
visualizer.

## Which package do I install?

| Package | Repository | Magento | PHP | Drop-in | Maintained |
|---|---|---|---|---|---|
| `jeanmarcos/inventory:2.4.7.*` | `jeanmarcos-dev/inventory` | 2.4.7 | 8.1 – 8.3 | yes | no |
| `jeanmarcos/inventory:2.4.8.*` | `jeanmarcos-dev/inventory` | 2.4.8 | 8.2 – 8.4 | yes | no |
| `jeanmarcos/inventory:2.4.9.*` | `jeanmarcos-dev/inventory` | 2.4.9 | 8.3 – 8.5 | yes | no |
| **`jeanmarcos/msi-core:^1.0`** | **`jeanmarcos-dev/msi-core`** | **2.4.9** | **8.3 – 8.5** | **no** | **yes** |

Mage-OS 3.x, which tracks Magento 2.4.9, is supported. `jeanmarcos/inventory` is no longer
maintained, and its later releases fail every order with managed stock on MySQL
([notice](https://github.com/jeanmarcos-dev/inventory#readme)). `msi-core` gives you one inventory
system instead of two, at the cost of a schema migration.
[Why msi-core](https://jeanmarcos-dev.github.io/msi-core/start/why/) compares them.

## Install

```bash
composer config repositories.msi-core vcs https://github.com/jeanmarcos-dev/msi-core
composer require "jeanmarcos/msi-core:^1.0"
```

**On a store with data this is a schema migration with no downgrade path.** Read
[Upgrade from Magento MSI](https://jeanmarcos-dev.github.io/msi-core/start/upgrade/) before running
`setup:upgrade`, and [Install](https://jeanmarcos-dev.github.io/msi-core/start/install/) for the
full steps.

## Documentation

- [Install](https://jeanmarcos-dev.github.io/msi-core/start/install/) and
  [Upgrade from Magento MSI](https://jeanmarcos-dev.github.io/msi-core/start/upgrade/)
- [Run in production](https://jeanmarcos-dev.github.io/msi-core/start/production/): the salability
  consumer is a requirement
- [Features](https://jeanmarcos-dev.github.io/msi-core/features/default-stock/),
  [configuration](https://jeanmarcos-dev.github.io/msi-core/reference/configuration/),
  [CLI](https://jeanmarcos-dev.github.io/msi-core/reference/cli/) and
  [REST](https://jeanmarcos-dev.github.io/msi-core/reference/rest-api/) reference
- [Versioning](https://jeanmarcos-dev.github.io/msi-core/reference/versioning/) and
  [`CHANGELOG.md`](CHANGELOG.md)

The site is built from [`docs/`](docs/). For MSI itself (concepts, REST API, admin walkthroughs)
Adobe's documentation still applies, since the module names, namespaces and service contracts are
unchanged.

## Contributing

Questions go to [Discussions](https://github.com/jeanmarcos-dev/msi-core/discussions). Issues and
pull requests are welcome at [jeanmarcos-dev/msi-core](https://github.com/jeanmarcos-dev/msi-core).
Report a vulnerability privately, as [`SECURITY.md`](SECURITY.md) explains, never in a public issue.
See [Contributing](https://jeanmarcos-dev.github.io/msi-core/project/contributing/) and
[`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md).

## License and attribution

This is a modified derivative of [`magento/inventory`](https://github.com/magento/inventory)
(Copyright Adobe), redistributed under **AFL-3.0**. Files derived from upstream keep Adobe's
original copyright and license notices; files original to this fork carry their own copyright under
OSL-3.0 / AFL-3.0. See [`LICENSE_AFL.txt`](LICENSE_AFL.txt) and [`NOTICE`](NOTICE).

**Not affiliated with, endorsed by, or supported by Adobe.** Magento and Adobe Commerce are
trademarks of Adobe Inc.
