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

# msi-core 1.1.2 — 2026-10-17

### A placement that fails after reserving gives its stock back

When a placement wrote its reservation and then failed with a PHP `Error` (a `TypeError` from a
payment module, a method called on `null`) instead of an `Exception`, the compensation was never
written. The stock stayed reserved for an order that did not exist, and the salable quantity stayed
one unit short for good. Any `Throwable` now writes the compensation, and the error still reaches the
caller unchanged.

A placement whose process died after reserving (out of memory, killed by the FPM timeout) left the
same orphaned reservation, and neither `inventory:reservation:reconcile` nor upstream's
`list-inconsistencies` reported it. The reconciliation pass now repairs these abandoned placements:
increment ids that reserved stock at placement, still hold it and have no order. To leave running
placements alone it only releases reservations written before a mark at least 15 minutes old by the
database clock, so a retry of the same cart that is still running keeps its own reservation. With the
hourly sweep an orphan is repaired one to two hours after it was written. The first run after
upgrading records the mark and repairs nothing. The command reports abandoned placements apart from
terminal orders, and says how many are too recent to repair yet. An order deleted from `sales_order`
while its reservations were still open now counts as abandoned too, and its stock is released.

The repair runs with the sweep (`cataloginventory/source_reservations/reconcile_sweep_enabled`, off by
default) or on demand with the command.

---

# msi-core 1.1.1 — 2026-10-17

### The place-order lock covers the SKUs an order reserves, and only while it reserves them

The concurrency lock now covers the SKUs an order writes reservations for. Before, it was keyed on
the visible cart items, so a configurable or a bundle locked its own SKU while its child simple got
the reservation. A standalone order and a composite order of the same simple did not exclude each
other, and both could sell the last unit. With the window between the last salability check and the
reservation insert widened on purpose, simple + configurable, simple + bundle, configurable + bundle
and grouped + configurable sold one unit twice in 5 rounds out of 5. Grouped orders were already
safe, because their cart items are the simples themselves. Two variants of the same configurable no
longer wait for each other.

SKUs that never get a reservation no longer take the lock at all. That covers
`manage_stock = 0`, per item or globally, and `can_subtract = 0`. Ten concurrent checkouts of one
unmanaged SKU used to queue behind each other, and the last three failed after 30 s with
*Could not acquire inventory lock*.

The lock is now held while the reservation is written, not for the whole order submission. That is
the salability check plus the insert, 8 to 27 ms here, against 4 to 5 s for the submission with its
payment and email. Orders for the same SKU no longer wait for each other's payment. The lock is
released when the reservation is written even if the caller has a transaction open. A caller that
places orders inside its own transaction is not protected until it commits, which was already the
case. The initial placement of asynchronous orders and multishipping checkout, which never go
through the order submission and so had no lock at all, now take it too. A reservation that gives
stock back, such as the rollback of a rejected asynchronous order, takes no lock, so it can no
longer fail on contention and leave the stock reduced.

The `inventory_lock_during_place_order` plugin on `QuoteManagement::submit()` is removed. A
configuration that disables it by name no longer has any effect. Stores that disabled it to avoid
the wait on unmanaged stock no longer need to.

---

# msi-core 1.1.0 — 2026-10-13

### Every source item change now leaves a history row

Saving or deleting source items (admin product form, REST, imports), shipment and virtual invoice
deductions, and returns to stock from credit memos now write an append-only row to
`inventory_source_item_adjustment`, in the same transaction as the change. The row records the delta,
the resulting quantity, the status change if any, the reason, who made the change, and the shipment
or credit memo behind it (a deduction made when a credit memo is saved for items never shipped
names the order). `bin/magento inventory:adjustment:verify` lists source items whose quantity no
longer matches their last row and exits non-zero when there is any. History starts at the upgrade;
nothing is backfilled. Set `cataloginventory/source_item_adjustment/enabled` to `0` to stop
recording. When more items drift than `--limit` (1000 by default), `verify` says it is showing the
first ones and that there may be more, instead of presenting the limit as the total.

Bulk unassigns, full and partial transfers (one `transfer_out` and one `transfer_in` row), imports
(one import id per run; a replaced row records its net change), the legacy stock item bridges, mass
attribute updates, SKU renames through the API and the cleanup after a product is deleted now
record their own reason. Async bulk operations are attributed to the user that scheduled them and
carry the bulk uuid as `request_id`.

Recording a write looks its source items up per source with 200 SKUs per query, or per SKU with 200
sources per query when the write spans more sources than SKUs, so the cost grows with the number of
items written and not with the size of the catalogue. Saving one product stocked in 150 sources
reads its items twice, before and after the write, with 2 queries each instead of 151, and spends
about 3 ms on them instead of 32. Looking up 5000 items in a
600 000-row source item table takes about 70 ms; one condition per item, as before, took between 8 s
and almost three minutes there, and held the items it had locked while it ran.

The quantities a row records after a write are read with the same row locks as the ones before it,
so a transaction that had already read the source item table cannot record a stale quantity as a
change. The name of the admin user or integration behind a write is looked up once per request.

### Reading the adjustment history

The history can now be read without going to the database. `GET /V1/inventory/source-item-adjustments`
takes `searchCriteria` and returns rows from newest to oldest; it always pages, at most
`cataloginventory/source_item_adjustment/max_page_size` rows per page (500 by default), and rejects a
larger `pageSize` with a 400 that names the limit. In the admin, **Stores › Inventory › Adjustment
History** lists every row read-only, with filters by date, source, SKU, reason, actor type and
reference, and links to the order, shipment, invoice or credit memo behind a row. The product form
gains a collapsed **Stock History** section with the same list for that product, loaded only when it
is opened. All three require the new `Magento_InventoryAdjustment::view` permission. REST writes to
`POST /V1/inventory/source-items`, and its async variant, can say why the stock changed with the
`adjustment` extension attribute (`reason`, `note`, `reference_type`, `reference_id`); a reason
outside the list, a note or reference that is too long, a reference type of `order`, `shipment`,
`invoice` or `creditmemo` (Magento records those itself, by entity id), or items of one request
carrying different values are rejected with a 400 and nothing is written.

### Keeping the adjustment history bounded

The history no longer has to grow forever. `cataloginventory/source_item_adjustment/retention_days`
sets how many days a row is kept; the default, 0, keeps every row. When it is above 0, the
`inventory_adjustment_prune` cron job deletes older rows every night at 02:30, in batches of
`cataloginventory/source_item_adjustment/prune_batch_size` rows (5000 by default). Pruning never
deletes the latest row of each source, SKU and state, however old, so `inventory:adjustment:verify`
always has a row to compare an item's quantity against. It changes no quantity or status. Values
above 36 500 days or 100 000 rows per batch are treated as those limits, and the admin rejects them,
so an absurd retention no longer makes the nightly job fail. A new index on state, source, SKU and
row id serves both the prune and `verify`: on a 600 000-row history, `verify`'s scan went from about
520 ms to 130 ms and each prune batch from 45 ms to 14 ms.

The history starts at the upgrade, so an item that has not moved since has no row yet.
`bin/magento inventory:adjustment:opening-balance` gives every source item with a quantity other than
zero and no history one opening row: its delta and resulting quantity are the current quantity, the
reason is `other`, the note `opening_balance` and the actor `system`, and every row of one run shares a
`request_id`, which the command prints with the number of rows it wrote. Items without stock and items
that already have history are skipped, so running it again writes nothing. It locks the items it
writes, `--batch-size` at a time (1000 by default), and never runs on its own during `setup:upgrade`.

### Returns to stock restored nothing when the order was placed in the same process

A credit memo with **Return to Stock** added nothing back to the source when the order had been
placed, shipped and refunded by the same PHP process, such as a marketplace import, a long-running
consumer or an integration script. MSI looks each shipped unit up through the order that the order
repository cached; when that order is the one checkout has just built, its items are keyed by
position rather than by id, the lookup came back empty, and the unit was skipped without a warning.
MSI now finds the order item by id among the order's items when that lookup misses. Credit memos
from the admin or REST, which load the order afresh, were not affected.

---

# msi-core 1.0.8 — 2026-10-08

### Source selection page warns when shipping from a source the order did not reserve at

With source reservations enabled, the admin source selection page (**Ship** on a multi-source order)
now shows a warning under the **Source** selector when the selected source is not one the order
reserved an item at. The warning names the SKU and its reserved sources with quantities. Shipping
from that source still works: the warning explains that it takes the stock from the selected source
and releases the reservation at the other one. Nothing changes when source reservations are
disabled.

---

# msi-core 1.0.7 — 2026-10-08

### Full source transfer left open orders reserved at an empty source

With source reservations enabled, `POST /V1/inventory/bulk-product-source-transfer` moved all the
origin's stock to the destination but left the reservations of open orders at the origin. The origin
ended at zero with orders still allocated to it. The destination showed their units as free.

- **The transfer now moves those reservations with the stock.** For every open order, it releases
  the origin's pending balance and places the same quantity at the destination. Each order's ledger
  stays balanced, and its shipment then comes from the destination. The move runs under the same
  `(sku, source)` locks as checkout, in the same transaction as the stock.
- **The transfer is refused** when the destination is not assigned to the stock of an order that
  holds reservations at the origin. The error names the order.
- **The oversell guard on new reservations now checks a batch's net demand** per stock and SKU.
  It used to sum only the negative rows. A balanced move between sources asks the stock for nothing,
  and checkout batches are checked exactly as before.

---

# msi-core 1.0.6 — 2026-10-07

### Salable quantity table headers out of line with their values

In the per-source salable quantity table, the **On Hand**, **Reservations** and **Salable** headers
were left-aligned while their values were right-aligned. Each number looked as if it belonged to
the next column. The admin grid rule `.data-grid th { text-align: left }` outranked the table's own
alignment class on the headers. The alignment is now scoped to the table, so headers and values
share the right edge. This applies to both the product form and the product grid cell, with source
reservations on or off.

---

# msi-core 1.0.5 — 2026-10-07

### Shipments ignored the source the order reserved at

With source reservations enabled, an order reserves each item at specific sources when it is
placed. Shipping did not read that allocation.

- **The admin shipment form suggested sources by physical quantity only.** Once another open order
  had reserved a source, the form could suggest it anyway, and shipping from it left that source
  negative net of its reservations until the other order closed. The form now suggests the sources
  the order reserved at, with those quantities. The source selection only places the rest.
- **A shipment created without a source in a stock with several sources used the `default`
  source** (REST `ShipOrder` without `extension_attributes.source_code`, or programmatic
  shipments). It failed when `default` had no row for the SKU. When `default` did have one, the
  shipment drew from a source outside the order's stock. The source is now resolved in this order:
  1. the only source of the stock;
  2. the source the order reserved every item at;
  3. the default source selection, when it ships everything from one source.

  When none of these applies, the shipment is rejected with a validation message asking for a
  source. It no longer fails with `Source item not found`.

A source chosen explicitly, in the admin or through `extension_attributes.source_code`, is still
used as given.

---

# msi-core 1.0.4 — 2026-10-07

### Partial source transfer could leave negative quantities and move reserved stock

`POST /V1/inventory/bulk-partial-source-transfer` validated each item against the unlocked physical
quantity and then wrote absolute values computed in PHP, one transaction per item.

- **A SKU repeated in one request was checked item by item**, so 3 + 3 out of a source with 3 left
  it at −3. Repeated SKUs are now summed before validating.
- **A zero or negative quantity was accepted** and ran the transfer in reverse, which could take
  the destination negative. It is now rejected.
- **A destination removed between validation and write made the units disappear.** The rows are
  now read with `SELECT … FOR UPDATE` and the transfer aborts if either source item is missing.
- **Concurrent writes were lost.** The origin is decremented with a relative update that refuses to
  go below zero, and the whole request runs in one transaction: every item moves or none does.
- **With source reservations enabled, units reserved by open orders at the origin could be moved
  away**, leaving those orders without stock at their source. The transfer now holds the same
  `(sku, source)` locks as checkout and refuses to move more than the origin quantity net of its
  source reservations.

The full transfer (`bulk-product-source-transfer`) is unchanged.

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
