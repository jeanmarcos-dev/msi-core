# Changelog

## How to read this file

This repository maintains four lines from one codebase, so most changes ship more than once: the
same fix is ported to every line it applies to and tagged separately on each. A file organised by
release would scatter one change across four places and leave you unable to answer the only
question that matters — *does my version have this?*

So it is organised **by change**, and each entry names the version that carries it on every line:

    Shipped: 2.4.9.20 · 2.4.8.20 · 2.4.7.16 · msi-core 1.0.0

A line missing from that list does not have the change. When that is deliberate, the entry says why.

Versions follow [`VERSIONING.md`](VERSIONING.md). Dates are the tag dates.

---

# msi-core 1.0.3 — 2026-10-07

### Source item writers that skipped the stock index and the salability processors

Some services write `inventory_source_item` without going through `SourceItemsSaveInterface` or
`SourceItemsDeleteInterface`, and skipped part of what those trigger.

- **Deleting or renaming a product left its SKU salable in the stock index.** The
  `inventory.source.items.cleanup` consumer deleted the source items but not their index rows, so
  `GetProductSalableQty` kept reporting the old quantity for a SKU with no stock. It now deletes
  through `SourceItemsDeleteInterface`.
- **Bulk transfer, partial transfer and bulk unassign left product and category pages cached as in
  stock.** They rebuilt whole stocks, a path that never runs the salability change processors (page
  cache, fulltext, catalog rule, stock visualizer). They now reindex only the affected SKUs through
  the same processor chain as a regular save.
- **Bulk assign passed source codes to an indexer that expects source item ids**, so newly assigned
  items got no index row. It uses the same SKU reindex.
- **Renaming a product through REST or SOAP left the new SKU unindexed.** The copied source items
  are now saved through `SourceItemsSaveInterface`.

**After upgrading:** rows left behind by deleted or renamed products are not removed retroactively.
Run `bin/magento indexer:reindex inventory` once.

---

# msi-core 1.0.2 — 2026-10-07

### Reservation cleanup no longer leaves phantom salable quantity

The `inventory_cleanup_reservations` cron deletes the reservations of an object once they add up to
zero. It had three defects:

- **Groups larger than `group_concat_max_len` were deleted partially.** The list of ids was cut at
  32768 characters, so only part of the group was deleted and the rest stayed forever as salable
  quantity that does not exist. An 8000-row zero-sum group left 2539 rows adding up to +13. The
  cleanup now checks the list against `COUNT(*)` and reads the whole group again when it was cut.
- **Grouping ignored `object_type` and `source_code`.** `Select::group()` only reads its first
  argument, so the cleanup grouped by `object_id` alone. A group could add up to zero across two
  sources while each source still held a balance, and the per-source quantity changed when it was
  deleted.
- **Reservations without `object_id` were cleaned as one group.** Every such row, of any SKU or
  stock, was summed together. They are now left alone.

**Behavior change:** reservations written without `object_id` or `object_increment_id` in their
metadata are no longer deleted by the cron. MSI always writes both.

---

# msi-core 1.0.1 — 2026-10-07

Upstream `magento/inventory` `develop` merged up to `dea64c0`. No schema, configuration or API
change.

### Fixes taken from upstream

- **ACP2E-5162** — a `catalogsearch_fulltext` reindex could halt part-way through the catalog
  (reported at 16,500 documents). `GetStockItemsData` now binds each SKU as its own placeholder
  instead of quoting the list into the SQL. Upstream fixed both of its query branches; this line
  has only the index-table one, so it takes the binding and drops the Default Stock branch and the
  `cataloginventory_stock_item` fallback again, as in 1.0.0.
- **AC-10942** — `IsSalableOptionPlugin` no longer keeps a per-instance salability cache across
  `getUsedProducts()` calls, which grew without bound and could answer for the wrong stock.
- **ACP2E-5231** — In-Store Pickup: a shipping method left over from a previous address is no
  longer re-applied to a quote already in the pickup-location state.

### Not taken

- Upstream's fix to `AdminBundleProductDisabledManageStockOnConfigurationPageCustomStockTest`: this
  line removed that test in 1.0.0 as a duplicate of the configuration page test, and keeps it
  removed.

---

# msi-core 1.0.0 — 2026-08-13

The first release in which MSI is the only inventory system. Exclusive to this line: it changes the
schema and the data contract, which is why it cannot ship on the `dist-2.4.x` lines at all.

**Read [`UPGRADE.md`](UPGRADE.md) before installing.** There is no downgrade path.

### The Default Stock stops being special

`inventory_stock_1` was a MySQL view over `cataloginventory_stock_status`; it is now a real index
table built by the MSI indexer. Stock 1 is indexed, read, edited and deleted like any stock you
create yourself.

- The MSI stock indexer no longer skips stock 1, and the flag that forced it through the exception
  path is gone.
- `InventoryConfigurableProductIndexer`, `InventoryBundleProductIndexer` and
  `InventoryGroupedProductIndexer` now cover stock 1, so composite salability comes from MSI
  instead of the legacy CatalogInventory indexer. The plugins that used to patch the gap by driving
  the legacy indexer are removed.
- The `if ($stockId === $defaultStockId)` branch is gone from the salability readers, the product
  collections and filters, the price index, search, catalog rules and visual merchandiser — around
  fifty branches across twenty modules.
- The read fallback to `cataloginventory_stock_item` is removed. It only existed because the view
  held no rows for disabled products; the real table does, because the index is built from
  `inventory_source_item` rather than from the enabled catalog.
- Stock 1 is now editable and deletable, and its sources can be assigned and unassigned. The admin
  website still resolves to stock 1, and now fails with a clear error rather than a raw
  `NoSuchEntityException` when someone deletes it.

### Stock item configuration moves into MSI

`manage_stock`, `backorders`, `min_qty`, `qty_increments` and the rest lived in
`cataloginventory_stock_item` for **every** stock, not just the default — so no stock was ever
independent of the legacy system.

- New table `inventory_stock_item_configuration`, keyed by sku, with a data patch that migrates the
  rows upstream actually wrote (stock 1, scope 0).
- The indexer joins the new table, which removes the hidden `stock_id = 1` condition its joins
  carried.
- Configuration of a deleted product is now dropped with it.

Per-source configuration overrides would be the natural next step and are deliberately out of
scope: they change the data model rather than its storage. A configuration read for stock 5 answers
the same as one for stock 1, exactly as before.

### The legacy tables are frozen, and writes to them are reported

MSI no longer writes `cataloginventory_stock_item` or `cataloginventory_stock_status`. The whole
synchronisation layer is deleted, and the legacy `cataloginventory_stock` indexer is inert.

The tables are **not** dropped — `Magento_CatalogInventory` is required by 42 core modules and
cannot be uninstalled, so declarative schema would recreate them anyway.

Because a write to a frozen table now succeeds and changes nothing, the upgrade installs four
triggers that count such writes into `inventory_legacy_stock_write`, surfaces the count as an admin
notification, and exposes it as:

    bin/magento inventory:legacy-stock:writes [--clear|--disable|--enable]

The legacy read contracts are unaffected. `StockRegistryInterface`, `StockStateInterface`,
`StockItemRepositoryInterface`, `StockStatusRepositoryInterface` and `StockManagementInterface` are
all served from MSI, so code going through them needs no change.

### Core readers redirected to MSI

Every reader of the legacy tables inside Magento now reads the MSI index, including those that
reach the table through `$stockItemResource->getMainTable()` and are invisible to a grep:

- the `qty` and stock status columns and filters of the admin product grid;
- the Quantity column of the widget grids (review, url rewrite, bundle option and downloadable
  product pickers);
- the bundle selection filter behind dynamic bundle prices;
- the stock columns of the product export;
- the bundle and configurable price indexers;
- the low stock report.

### Defects found and fixed along the way

- A configuration-only save of a legacy stock item did not rebuild the stock index, so a
  `manage_stock` change did not reach salability.
- A full stock reindex left catalog pages stale in the full page cache.
- The composite stock status settled before the source items were reindexed, so a parent could
  report the previous state.
- An import carrying no stock column rewrote the configuration; one carrying no quantity zeroed the
  default source item.
- A products filter arriving grouped had only its first id read.
- A sku the index does not carry is reported out of stock instead of raising.
- The stock registry snapshot was not dropped when source items were saved or deleted.
- A required bundle option with nothing to sell was ignored when pricing the bundle.
- A product page outside the stock broke instead of hiding the quantity message.

### Performance

- A list of stock items is answered from MSI without loading the legacy rows it replaces, and the
  product type lookup is skipped when every sku already carries a quantity.
- Saving the inventory configuration section no longer rewrites every
  `cataloginventory_stock_item` row: measured at 2464 discarded writes on a 2.5k product catalog,
  a full pass over the table for nothing.
- A legacy stock item save triggers a reindex only when it moved something the index reads.

---

# Changes shared across lines

## Salability update never enqueued with source-level reservations

**Shipped:** 2.4.9.20 · 2.4.8.20 · 2.4.7.16 · msi-core 1.0.0

With `cataloginventory/source_reservations/enabled = 1`, the plugin that publishes
`inventory.reservations.updateSalabilityStatus` was attached to the legacy
`PlaceReservationsForSalesEvent` — the exact class the router delegates *away from* when source
reservations are on. Selling therefore never refreshed the index, and the storefront kept offering
stock that was already committed. The plugin now sits on the router.

## Configurable salability resolved from the stock index

**Shipped:** 2.4.9.16 · 2.4.8.17 · 2.4.7.15

Listing a configurable product ran one `COUNT` per product to decide whether any child was salable.
The listing plugin resolves it from the stock index instead: 12 queries down to 0, and 17.5 ms down
to 250 µs on the measured page. Includes two fixes to the child status join of the parent stock
index, which the old path masked.

## Salable quantity broken down by source

**Shipped:** 2.4.9.17 · 2.4.8.18

The *Product Salable Quantity* section of the product form and the *Salable Quantity* column of the
product grid report, per source, the quantity on hand, that source's reservation balance and the
salable quantity they add up to. A salable quantity lower than the quantity on hand can now be
traced to the source holding the difference. Sources contributing nothing are still listed and
labelled, so a zero reads as a reason rather than a defect.

Not ported to 2.4.7.

## Storefront stock visualizer

**Shipped:** 2.4.9.13 · 2.4.8.14 · 2.4.7.13 (refined through 2.4.9.15 · 2.4.8.16 · 2.4.7.14)

An opt-in product-page *Availability* panel driven by MSI, shipped as the additive
`Magento_InventoryStockVisualizer` module — no core module is replaced. Renders a traffic-light
level with no quantity exposed, or the exact salable quantity over a cacheable AJAX fragment,
aggregate or per source. Composite products resolve availability by type. Out-of-stock products
render the status alone, skipping the client component entirely.

See [`InventoryStockVisualizer/README.md`](InventoryStockVisualizer/README.md).

## Supply-side oversell detection

**Shipped:** 2.4.9.11 · 2.4.8.13 · 2.4.7.12

Detection only — it never blocks a sale. Reports when the supply side of the ledger has drifted
into a state that would let a stock oversell, with a sweep that can run on cron:

    bin/magento inventory:reservation:detect-oversell

## Reservation invariant guards and reconciliation

**Shipped:** 2.4.9.10 · 2.4.8.12 · 2.4.7.11

Makes the demand side of the ledger self-defending: clamp and reject guards on the reservation
path, plus a reconciliation pass that repairs a ledger that already drifted.

    bin/magento inventory:reservation:reconcile

## Order submission lock covers the admin path

**Shipped:** 2.4.9.8 · 2.4.8.10 · 2.4.7.9

The concurrency lock was taken around place-order, which `submit()` bypasses — so admin-created
orders and anything else reaching the quote submission directly went unserialised. The lock moves
to order submission and covers both.

## Source-level place-order locks

**Shipped:** 2.4.9.7 · 2.4.8.9 · 2.4.7.8

The place-order lock was per stock, so two orders on different stocks sharing a source were not
serialised against each other and could oversell that source. The lock is taken per source.

Measured: disjoint orders show no contention and run fully in parallel; orders on a shared
sku and source serialise at roughly 687 ms of hold each.

On 2.4.9 this upgrades the lock introduced upstream by ACP2E-4509. On 2.4.8 and 2.4.7 that fix was
never backported by Adobe, so the lock is introduced whole.

## Source-level reservations

**Shipped:** 2.4.9.6 · 2.4.8.8 · 2.4.7.7

Opt-in, default off, at `cataloginventory/source_reservations/enabled` (Stores > Configuration >
Catalog > Inventory > Source-Level Reservations).

Splits each sales reservation into one row per source, allocated across the enabled sources of the
stock in priority order. Reservations then affect the salable quantity of **every** stock sharing
the source, which closes the cross-stock oversell gap, and compensations always land on the sources
the demand was allocated to regardless of which source ships.

Toggling the flag requires a full `bin/magento indexer:reindex inventory`. Orders placed while it
was off are compensated stock-scoped, exactly as before; mixed states are safe.

## Mage-OS 3 drop-in support

**Shipped:** 2.4.9.12

The package replaces the `mage-os/*` MSI packages as well as the `magento/*` ones, so it installs
on Mage-OS without further configuration. Verified version map: Mage-OS 1.1 – 2.3 tracks Magento
2.4.8, Mage-OS 3.x tracks 2.4.9.

Not ported to 2.4.8 on purpose: no installation is known on that range, and the dual replace is not
free to carry.

---

# Earlier releases

Releases before `2.4.7.7` / `2.4.8.8` / `2.4.9.6` predate this file. They are the upstream MSI
baseline for each Magento line plus cherry-picked community fixes; `git log` between two tags is
the record. Cherry-picked upstream commits keep their original subject and carry a
`[picked #NNNN]` marker.
