# jeanmarcos/inventory — Community MSI distribution (Magento 2.4.9)

A redistributable fork of Magento **Multi-Source Inventory (MSI)** that ships
curated community fixes ahead of the upstream release cadence.

> **This branch (`next-release`) is not a drop-in replacement.** It removes the
> special Default Stock and stops MSI from writing the `cataloginventory_*`
> tables, which changes the schema and the data contract. Read
> [`UPGRADE.md`](UPGRADE.md) before installing it anywhere. Everything below
> describes the shared distribution mechanics; the `Installation` and
> `Versioning` sections apply to the `dist-2.4.x` branches, not to this one.

> This is a modified derivative of [`magento/inventory`](https://github.com/magento/inventory)
> (Copyright Adobe), redistributed under **AFL-3.0**. Not affiliated with or
> endorsed by Adobe. See [`NOTICE`](NOTICE) for full attribution.

## What this is

The `dist-2.4.9` branch targets **Magento Open Source 2.4.9** (PHP 8.3 - 8.5).
The PHP code keeps its original `Magento_Inventory*` module names and
`Magento\Inventory*` namespaces, so it is a **drop-in replacement**. Only the
Composer package identity changes.

`next-release` is based on `dist-2.4.9` and keeps the module names and
namespaces, but adds a table, migrates data into it and stops writing two core
tables, so it is a schema upgrade rather than a swap. Its package name and
distribution channel are still undecided; until they are, install it from the
branch and pin the commit.

## How it works

The single package `jeanmarcos/inventory` uses Composer's `replace` directive
to provide the 73 open-source MSI modules that ship with Magento 2.4.9.
Its `require` pins `magento/framework` to the 2.4.9 line, so Composer will
only install this build on a matching Magento version and auto-selects the right
build across versions.

## Installation

```jsonc
// composer.json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/jeanmarcos-dev/inventory" }
    ]
}
```

```bash
# explicit for this line:
composer require "jeanmarcos/inventory:2.4.9.*"
# or let the framework gate auto-select the right build:
composer require "jeanmarcos/inventory:*"
bin/magento setup:upgrade
```

## Versioning

Releases are tagged **`2.4.9.<n>`** (e.g. `2.4.9.0`, `2.4.9.1`) — the
4th segment is this distribution's release counter for the Magento 2.4.9 line.
This mirrors the target Magento version and does not collide with Adobe's own
`-pN` security-patch naming. Each Magento line has its own `dist-<version>` branch.

## Differences from upstream

- Curated fixes applied on top of the Magento 2.4.9 MSI baseline (cherry-picked
  commits are tagged `[picked #NNNN]`).
- **No special Default Stock** (`next-release` only): stock id 1 is indexed,
  configured, edited and deleted like any stock you create yourself, the
  composite indexers cover it, and the `cataloginventory_*` tables are neither
  read nor written by MSI. This removes the ~50 `if (default stock)` branches
  that gave the same catalog two behaviours, and with them a class of defects
  that could only be fixed twice. Breaking change — see [`UPGRADE.md`](UPGRADE.md)
  for what it costs, what it breaks and how to migrate. Because a write to those
  frozen tables now changes nothing and raises nothing, the upgrade installs
  triggers that count such writes and reports them in the admin and through
  `bin/magento inventory:legacy-stock:writes`.
- **Source-level reservations** (opt-in, default off): the global config
  `cataloginventory/source_reservations/enabled` (Stores > Configuration >
  Catalog > Inventory > Source-Level Reservations) splits each sales
  reservation into one row per source, allocated across the enabled sources of
  the stock in priority order. Reservations then affect the salable quantity of
  **every stock sharing the source**, closing the cross-stock oversell gap, and
  compensations (shipment, cancellation, credit memo) always land on the sources
  the demand was originally allocated to, regardless of which source ships.
  Notes:
  - Toggling the flag requires a full `bin/magento indexer:reindex inventory`.
  - Orders placed while the flag was off are compensated stock-scoped, exactly
    as before; mixed states are safe.
  - `inventory:reservation:create-compensations` still creates stock-scoped
    compensations; per-source residues of such orders are kept (not deleted by
    the cleanup cron) and remain visible until compensated per source.
  - Concurrent orders on different stocks sharing a source are not serialized
    against each other (the place-order lock is per stock); totals per stock
    are always preserved.
- **Salable quantity broken down by source**: the *Product Salable Quantity*
  section of the product form and the *Salable Quantity* column of the product
  grid report, for every source of a stock, the quantity on hand, that source's
  reservation balance and the salable quantity they add up to. The aggregate
  Magento already showed becomes the total of that breakdown, so a salable
  quantity lower than the quantity on hand can be traced to the source holding
  the difference. Sources that contribute nothing are still listed and labelled
  — a disabled source, or a source item set out of stock — so a zero reads as a
  reason rather than a defect. Notes:
  - Single-source mode is untouched: with one source the breakdown would only
    repeat the aggregate, so the section renders exactly as before.
  - Without source-level reservations the breakdown degrades to the quantity on
    hand, since reservations are then held per stock and cannot be attributed
    to a source.
  - The grid resolves a whole page in one pair of queries and keeps the
    breakdown collapsed until it is expanded, which costs no further request.
- **Storefront stock visualizer** (opt-in, default off): a product-page
  *Availability* panel driven by MSI, shipped as the additive
  `Magento_InventoryStockVisualizer` module (no core module is replaced). The
  global config `cataloginventory/stock_visualizer/enabled` (Stores >
  Configuration > Catalog > Inventory > Storefront Stock Visualizer) renders a
  traffic-light level (server-side, no quantity exposed) or the exact salable
  quantity over a cacheable AJAX fragment, aggregate or broken down per source
  (source-reservation aware). Composite products resolve their availability by
  type — the selected configurable variant, the sellable bundle count, a
  per-component breakdown, or an aggregate in-stock status — each selectable in
  the admin. Out-of-stock products render the status alone: the panel skips the
  client component, so no call to action is offered and no fragment is requested
  for an availability the server already resolved to zero. A dedicated cache tag
  keeps the panel fresh on both demand (reservation) and supply (source-item)
  changes; the purge runs synchronously or over a database-backed queue. Notes:
  - Run `bin/magento setup:upgrade` (registers the per-product attributes and the
    message-queue topology) and `setup:di:compile` for production.
  - When the purge strategy resolves to the queue, run the consumer
    `bin/magento queue:consumers:start inventory.stockvisualizer.purge` (or the
    standard consumer cron).
  - See [`InventoryStockVisualizer/README.md`](InventoryStockVisualizer/README.md)
    for the full configuration and cache-invalidation architecture.
- The proprietary `InventoryRequisitionList` module is **removed** (not covered by
  the OSL-3.0 / AFL-3.0 open source license).
- The `InventoryLogging` module is **removed** — it targets the Adobe Commerce
  `Magento_Logging` module and is excluded from the upstream Open Source
  metapackage; shipping it would break `setup:di:compile` on Magento Open Source.

## License

Redistributed under **AFL-3.0**. Files derived from upstream Magento retain
Adobe's original copyright and license notices; files original to this fork carry
their own copyright under **OSL-3.0 / AFL-3.0**. See [`LICENSE_AFL.txt`](LICENSE_AFL.txt)
and [`NOTICE`](NOTICE).
