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

# msi-core — unreleased

### Magento's Performance Toolkit generates its fixtures on a store with msi-core

`bin/magento setup:performance:generate-fixtures` stopped at the first simple product with `The entity ID field for the
"inventory_stock_item_configuration" table wasn't found`. The generator copies the rows a template product writes by
entity id, and `InventorySetupFixtureGenerator` only mapped `inventory_source_item`. msi-core keys
`inventory_stock_item_configuration` and `inventory_source_item_adjustment` by SKU, and the Default Stock index
`inventory_stock_1` is a table instead of a view over `cataloginventory_stock_status`, so each of them has to be
rewritten to the SKU of the generated product, once per SKU. The synchronous stock reindex also refreshes
`catalog_product_index_website` while the template is saved, a table written once per website; a plugin on `SqlCollector`
now keeps it out of the template. The small profile and a three-source, two-stock profile complete, the generated stock
index rows are identical to the ones `indexer:reindex` computes, and the 15 template SKUs the generator leaves behind are
removed by the existing `inventory.source.items.cleanup` consumer. Tracked in [msi-core#100](https://github.com/jeanmarcos-dev/msi-core/issues/100).

### Re-enabling a child at website scope through the repository reindexes its configurable parents

`SaveInventoryDataObserver` decided that the status of a product had not changed by comparing it with the value the product
was loaded with. `ProductRepository::save` loads that product in the current store, so with the admin store active and the
status written at website scope the comparison ran against the default value: a child disabled at website scope and
re-enabled through the repository looked unchanged, nothing reindexed its parents, and a configurable stayed out of stock
until the next full reindex. A new observer on `catalog_product_save_before` now reads the status persisted for the scope the
product is written to, falling back to the default scope, and `SaveInventoryDataObserver` compares against that value. It
costs one indexed `SELECT` (0.18 ms) per save of an existing product. Reindexing on every save that carries a status was
measured and rejected: it adds about 25 to 30 ms and 61 statements to a save that takes about 105 ms and 193 statements,
for every save that changes nothing. Tracked in [msi-core#95](https://github.com/jeanmarcos-dev/msi-core/issues/95).

### A product import that changes a child's status reindexes the stock of its configurable parents

`catalog_product_import_bunch_save_after` had no observer in msi-core, and `InventoryImportExport` only hooked
`catalog_product_import_bunch_delete_after`. An import that enabled or disabled a child wrote the status attribute
without touching any source item, so the stock index row of the configurable parent kept the salability it had before
the import, in every stock that holds the child, until the next full reindex. `ReindexAfterStatusImportObserver` now
reindexes, once per bunch, the SKUs of the rows that carry a status value, through the same service the mass attribute
update uses, so the parents are recomputed in every stock. Rows without a `status` cell, or with it empty, are skipped.
Direct SQL writes remain out of scope, as for every MSI index. Tracked in
[msi-core#95](https://github.com/jeanmarcos-dev/msi-core/issues/95).

### A configurable product is salable only in the websites where one of its children is enabled, assigned and salable

The stock index counted a configurable's children by their status in the default scope, and the storefront trusted the
parent's row. `status` is a website attribute, so a child disabled in one website and enabled by default left the
configurable salable there, and a child enabled in a website and disabled by default hid it. The row of a configurable now
counts a child that is enabled in at least one website of the stock, by the website's value or else the default scope, and
a stock without websites keeps the default scope. The storefront, GraphQL `stock_status` and the search index then answer
for the current website: a configurable is salable only if one of its children is enabled in, assigned to and salable
in that website, resolved for a whole listing in one query instead of one per product, and a row of 0 needs no query.
Changing the websites of a stock reindexes the stocks involved, since the rows now depend on them. Reindex the
`inventory` indexer after updating. The stock APIs that take a stock id answer for the whole stock, and two store views of
one website with different `status` values, which only direct SQL can produce, are not supported. A configurable whose
children are all disabled is not salable and is hidden from listings, as it was. Tracked in
[msi-core#89](https://github.com/jeanmarcos-dev/msi-core/issues/89).

### A configurable product stops being salable in the same request once its children go out of stock

In the frontend, GraphQL, REST and SOAP areas, `GetStockItemDataInterface` and `GetStockItemsDataInterface` keep the stock
index rows they have read in memory for the rest of the request, and nothing dropped them when the index was rewritten.
Saving the first child of a configurable loads its parent to extend the cache identities, which cached the parent's row
before the reindex of the siblings changed it. Every read of the parent after that in the same request, `isSalable()`
among them since the listing resolves it from the index, kept answering `true` for a parent whose children were all out
of stock. `IndexHandler` now drops the cached rows of the SKUs it writes or deletes, for every stock, so a read after a
reindex sees the reindexed row. The cached rows of other SKUs are untouched, and areas that do not cache stock rows are
unaffected. A configurable whose children are disabled on a store view is a separate difference and is not changed.
Tracked in [msi-core#89](https://github.com/jeanmarcos-dev/msi-core/issues/89).

### Composer no longer installs the upstream requisition list module next to msi-core

`magento/module-inventory-requisition-list` and `mage-os/module-inventory-requisition-list` were missing from the
`replace` section of `composer.json`, although the inventory metapackage requires them. A Composer install therefore
brought the upstream module in next to msi-core, 1.2.7 on Magento 2.4.9 and 3.6.0 on Mage-OS 3.6, and left an
upstream MSI module running against msi-core's code. Both packages are now replaced, so Composer does not install
them. A store that relied on the upstream module's requisition list integration loses it. Tracked in
[msi-core#36](https://github.com/jeanmarcos-dev/msi-core/issues/36).

### `inventory-sales` no longer depends on `inventory-indexer`

`ReconcileReservationsSweep` in `InventorySales` called `Magento\InventoryIndexer\Indexer\Stock\StockIndexer` itself, so
`inventory-sales` and `inventory-indexer` required each other. The reindex that follows a reconcile now lives in an
`after` plugin on the sweep in `InventoryIndexer`, like the other indexer hooks, and `InventorySales/composer.json` no
longer requires `magento/module-inventory-indexer`. The sweep still returns the `stock_ids` it compensated, the plugin
reindexes them unless the run was a dry run, and the result of the sweep is unchanged.
Tracked in [msi-core#35](https://github.com/jeanmarcos-dev/msi-core/issues/35).

### Saving existing source items writes them in batches instead of one row at a time

`SaveMultiple::updateExistentItems` ran one `UPDATE inventory_source_item ... WHERE source_item_id = ?` per
existing source item, so saving many items that already exist made one round trip, and without a surrounding
transaction one commit, per item. Existing items are now written in batches of 1,000 with
`INSERT ... ON DUPLICATE KEY UPDATE` keyed on `source_item_id`, so every row keeps its id, the auto increment
does not move and the order of the writes is unchanged. Nothing listens to `inventory_source_item` writes at
database level, and the plugins around the save read the table before and after the write, so they are not
affected. Measured saving 10,000 existing source items in one `SourceItemsSaveInterface::execute` call on MySQL
8.4 (median of 5): the whole save went from 2.40 s to 1.47 s, `SaveMultiple` with its plugins from 1.62 s to
0.71 s, and the writes alone, inside the transaction the adjustment recorder opens, from 1.08 s to 0.24 s.
Tracked in [msi-core#41](https://github.com/jeanmarcos-dev/msi-core/issues/41).

### Five modules declare the modules their code uses

`InventoryAdjustment`, `InventoryBundleProductIndexer`, `InventoryImportExport`, `InventorySalesAdminUi` and
`InventoryStockVisualizer` used modules that their `composer.json` did not require, so a Composer install could
leave the code calling a class or script that was not there. `magento/framework-message-queue`,
`magento/module-catalog-import-export`, `magento/module-sales` and `magento/module-ui` are now in `require`, the
first and last for the first time and the other two moved from `suggest`. `magento/module-tax` is also in
`require` for the bundle indexer: its use is guarded by `isEnabled('Magento_Tax')`, but the Integrity
`DependencyTest` counts that reference as a hard dependency and the package already arrives through
`magento/module-bundle` and `magento/module-catalog`, so declaring it changes nothing at install time. The five
entries are removed from the static Integrity baseline. Tracked in
[msi-core#56](https://github.com/jeanmarcos-dev/msi-core/issues/56).

### The adjustment grids no longer remove the data sources of the other admin grids

Since 1.1.0, `InventoryAdjustmentAdminUi` registered its two grid data sources in `etc/adminhtml/di.xml`, in the
`collections` argument of `UiComponent\DataProvider\CollectionFactory`. Magento merges each argument of an area
configuration with `array_replace`, so in `adminhtml` that list replaced the global one: the order, invoice,
shipment and credit memo grids lost their data sources and failed with `Not registered handle
sales_order_grid_data_source`, as did any other listing that uses the factory. The two data sources and their
virtual type now live in `etc/di.xml`, where Magento's own modules register theirs, so they are added to the
global list instead of replacing it. Tracked in [msi-core#58](https://github.com/jeanmarcos-dev/msi-core/issues/58).

### A stock status set through the stock item API reaches the stock and price indexes of composite products

`StockRegistryInterface::updateStockItemBySku()`, which `PUT /V1/products/{sku}/stockItems/{itemId}` calls,
left a configurable, bundle or grouped product stale on a single-source installation. Marking the composite
product itself out of stock saved the flag in `inventory_stock_item_configuration` but never rebuilt its row in
`inventory_stock_1`, because the reindex went through the source items of the SKU and a composite product has
none. Returning a child to stock saved the parent back in stock through `ChangeParentStockStatus`, whose save
reported no change because `is_in_stock` was not among the fields the index reads, so the parent was never
reindexed: its price had already been reindexed while it was still unsalable, and it stayed out of
`catalog_product_index_price` and the category listings. A save that only moved the configuration of a composite
product now rebuilds its stock row through the composite indexer, which also queues its price, and `is_in_stock`
counts as a field the index reads. Tracked in [msi-core#51](https://github.com/jeanmarcos-dev/msi-core/issues/51).

### A partial reindex never leaves a SKU without a row in the stock index

A partial reindex replaced the rows of a SKU in `inventory_stock_<id>` with two statements that
committed on their own: a `DELETE` and then an `INSERT ... ON DUPLICATE KEY UPDATE`, once for the
simple products and again for each composite type. A reader that came in between found no row and
read the SKU as not salable, as the storefront or the salability consumer can. An insert that failed
after the delete, with a deadlock between two concurrent reindexes, left the SKU without a row until
the next reindex. Each delete and insert pair now runs in one transaction, so a reader sees the
previous row or the new one, and a failed insert restores the previous rows before the error
propagates. The stock 1 table is covered too. When the caller already has a transaction open, the
reindex joins it without opening another level, so an error it raises does not mark the caller's
transaction as rolled back; a caller that catches that error and commits still commits the SKU
without a row, as before. A full reindex is unchanged. Tracked in
[msi-core#29](https://github.com/jeanmarcos-dev/msi-core/issues/29), from
[magento/inventory#3462](https://github.com/magento/inventory/issues/3462) and
[magento/inventory#3443](https://github.com/magento/inventory/issues/3443).

### Two releases of one order can no longer release its stock twice

A cancel, credit memo, shipment or manual compensation is capped at what its order still reserves.
The cap read that balance without a lock, under an advisory lock that was released before the
transaction committed and that gave up after 10 seconds. Two releases of the same order that
overlapped could each read the balance before the other committed, and both write: a credit memo
that commits after the online refund, a shipment saved while the order is cancelled, or any release
that waited out the lock. The order then reserved less than nothing, and the extra unit stayed
salable for good: reconciliation only releases what an order still holds, and the cleanup only
deletes groups that add up to zero. Each order now keeps its balance per stock, SKU and source in
`inventory_reservation_order_balance`, written in the same transaction as its reservations, and a
release locks that row until it commits. Placements never wait for it. When the row stays locked
beyond `innodb_lock_wait_timeout`, that release is not written and an error is logged; the order
operation completes and reconciliation releases the balance once the order is final. A source
transfer that meets such a row fails whole instead, stock included, and can be run again.

`setup:upgrade` creates the table empty; orders placed before the upgrade are covered from their
first release on. Deploy in maintenance mode, and if you ever truncate `inventory_reservation`,
truncate the new table with it. See [Upgrade](https://jeanmarcos-dev.github.io/msi-core/start/upgrade/#upgrading-between-msi-core-versions)
and [Source reservations](https://jeanmarcos-dev.github.io/msi-core/features/source-reservations/#reconciliation).

### The shipping plugins move to the modules they plug into

Inventory Sales registered plugins on Inventory Shipping and Inventory Shipping Admin UI without
declaring either module. Their load order was undefined, and removing either module left a plugin on
a class that no longer existed. Each plugin now lives in the module it plugs into: the one that ships
from the reserved source moves to Inventory Shipping, and the one on the admin shipment form to
Inventory Shipping Admin UI. Both modules now declare Inventory Sales, and Inventory Sales no longer
refers to either. Each shipping module can still be disabled or removed on its own, and its plugin
goes with it. Declaring them from Inventory Sales would have meant that `Magento_InventoryShipping`
could only be disabled together with every module that depends on Inventory Sales. The plugins keep
their names; their classes are now
`Magento\InventoryShipping\Plugin\PreferAllocatedSourceOnResolveShipmentSourceCodePlugin` and
`Magento\InventoryShippingAdminUi\Plugin\PreferAllocatedSourcesOnGetSourcesByOrderIdSkuAndQtyPlugin`.
Raised by Ryan Hoerr in review of
[rhoerr/mage-os.inventory#1](https://github.com/rhoerr/mage-os.inventory/pull/1).

### Saving a shipment again keeps the source it shipped from

A shipment that was loaded without its `source_code` extension attribute, as a shipment collection
loads it, and then saved again through the shipment resource model had its stored source replaced
with `default`. The stock had already left the source it was shipped from, so returns to stock and
the admin's per-source views read a source that shipped nothing. Saving now writes the source only
when the shipment carries one, and otherwise keeps the stored one. A new shipment still always gets
a source: it is resolved before the save, or the save is rejected. A shipment with no stored source
no longer gets `default` when it is saved again. Tracked in
[msi-core#11](https://github.com/jeanmarcos-dev/msi-core/issues/11), from
[magento/inventory#3317](https://github.com/magento/inventory/issues/3317).

### A source that a shipment empties goes out of stock

**This changes behaviour.** Shipping, or invoicing virtual items, lowered a source item's quantity
but never wrote its status: the deduction computed one and dropped it, so a source shipped down to
its out-of-stock threshold stayed In Stock. The deduction now writes the status in the same update
as the quantity, and only when the source changes state:

- a deduction that leaves the quantity at or below the product's out-of-stock threshold, for a
  product without backorders, sets the source item Out of Stock;
- a return to stock that lifts an Out of Stock source item above the threshold sets it In Stock,
  only when *Set Items' Status to be In Stock When Order is Cancelled*
  (`cataloginventory/options/can_back_in_stock`, on by default) is on. This includes a source that
  was marked Out of Stock by hand;
- anything else leaves the status alone, so a source marked Out of Stock by hand stays that way
  through later shipments. Upstream's proposed fix writes the computed status on every deduction,
  which would undo it.

Whether a return brings a source back now depends on the source item's own status, no longer on the
product's stock status. The status change is recorded in the same adjustment history row as the
quantity. With a threshold above 0, a source that a deduction leaves Out of Stock keeps its
remaining units, and they no longer count towards the salable quantity, as when a merchant marks the
source Out of Stock by hand. [Differences from upstream][d12-upstream] compares it with upstream.
Tracked in [msi-core#12](https://github.com/jeanmarcos-dev/msi-core/issues/12), from
[magento/inventory#3478](https://github.com/magento/inventory/issues/3478).

[d12-upstream]: https://jeanmarcos-dev.github.io/msi-core/reference/upstream-differences/#a-source-that-a-shipment-empties-goes-out-of-stock

### The admin ships an unmanaged item together with a managed one

On the admin source selection page, **Proceed to Shipment** rejected an order that mixed a product
with managed stock and one without, when the source held none of the unmanaged one:
`Qty of <sku> should be less or equal to 0`. The form posts `isManageStock` as the string `"false"`,
which PHP reads as true, so the unmanaged item was checked against the source's quantity. The value
is now read as a boolean, and an unmanaged item is checked against its quantity to ship. Tracked in
[msi-core#15](https://github.com/jeanmarcos-dev/msi-core/issues/15), from
[magento/inventory#3408](https://github.com/magento/inventory/issues/3408).

### Virtual and downloadable products are sold only up to their stock

**This changes behaviour.** A virtual or downloadable product with managed stock and backorders on
was sold with its source at 0. Invoicing it had nothing to deduct, so the order completed with its
reservation still open, and that reservation lowered the product's salable quantity for good.
msi-core keeps physical stock at 0 or above, and a backorder waits for stock before it ships, but
these products have nothing to ship. With managed stock, they now count as having backorders *No*
and an out-of-stock threshold of 0 or above, whatever their own or the global configuration says.
This holds in the salable check, the salable quantity, the stock index, the cart and order
placement. Physical products keep their backorders. The types are a list in `di.xml`, and
[Differences from upstream][d4-upstream] explains how to take one out. The stored configuration is
not changed, so taking a type out restores its backorders as they were.

**Upgrade:** reindex `inventory` so the stock index stops listing these products as salable without
stock. Orders sold this way before the change keep their open reservation. Once they are complete or
closed, `bin/magento inventory:reservation:reconcile` writes the compensation that releases it. Run it
with `--dry-run` first to see the orders it would change. Tracked in
[msi-core#16](https://github.com/jeanmarcos-dev/msi-core/issues/16), from
[magento/inventory#3418](https://github.com/magento/inventory/issues/3418), which reports the same for
Adobe Commerce eGift cards.

[d4-upstream]: https://jeanmarcos-dev.github.io/msi-core/reference/upstream-differences/#virtual-and-downloadable-products-do-not-admit-backorders

### A source item whose source code differs from the stored one only in case is rejected

`POST /V1/inventory/source-items` accepted a `source_code` that differed from the stored one only in
case, `Warehouse_Bravo` for `warehouse_bravo`, because the foreign key compares without regard to
case, and saved the row exactly as it arrived. The product grid in the admin then failed with
"Undefined array key", since it looks the source up by exact code. **This changes behavior:** such a
save is now rejected with a validation error that names the stored code, `Source code
"Warehouse_Bravo" does not match the stored source code "warehouse_bravo".`, and nothing in the
batch is saved. A client that sends a code in another case has to send it as it is stored. A code
that matches no source still fails as before. The grid also looks the source up without regard to
case and skips a row it cannot resolve, so rows saved with another case before this change no longer
break it. Those rows are not rewritten. Costs one more query per save on `inventory_source`, for
the distinct codes of the batch. Tracked in
[msi-core#30](https://github.com/jeanmarcos-dev/msi-core/issues/30), adapted from
[magento/inventory#3471](https://github.com/magento/inventory/pull/3471) by
[lbajsarowicz](https://github.com/lbajsarowicz), which saves the stored code instead of rejecting
the save.

### Separating new from existing source items no longer compares every pair

Saving source items matched each item of the batch against each stored row, so the time grew with
the product of the two. Saving 10,000 items that already existed took a median of 12.5 seconds,
9.6 of them in the separation; with this change it takes 2.9 seconds, 0.1 of them in the
separation. It now indexes the batch by source and SKU and walks the stored rows once. The upstream
patch rebuilt the list of new items from arrays keyed by source and SKU: `array_merge` kept only one
item when the same non-numeric SKU was new in two sources of the same save, and the new rows were
inserted grouped by source instead of in the order they came in. The new items are now the ones
left in the batch, in their original order, and tests cover both cases. Tracked in
[msi-core#31](https://github.com/jeanmarcos-dev/msi-core/issues/31), from
[magento/inventory#3464](https://github.com/magento/inventory/pull/3464) by
[stollr](https://github.com/stollr).

### The web API honours the sort of a pickup location search

`GET` and `POST /V1/inventory/in-store-pickup/pickup-locations/` rejected any `searchRequest[sort]`
over REST and SOAP with `"Sort" is not supported`. Since magento/magento2@8075ae1942 the web API
passes a constructor argument to a data object only when its type is simple or lives in an
`Api\Data` namespace, so it fell back to a setter for `sort`, and `SearchRequestInterface` had
none. `SearchRequestInterface` and `SearchRequest` now declare `setSort()`, and the pickup
locations come back sorted by the given fields. GraphQL was not affected, because it builds the
request through `SearchRequestBuilder`. `setSort()` adds a method to an `@api` interface, which
[`VERSIONING.md`](VERSIONING.md) allows in a minor release. Tracked in
[msi-core#50](https://github.com/jeanmarcos-dev/msi-core/issues/50).

### Credit for the fixes taken from open upstream pull requests

Since 1.0.0, msi-core carries five pull requests that are still open in `magento/inventory`. They
were taken before this changelog started, and two of them were committed under the maintainer's
name, so the history did not credit their authors:

- [magento/inventory#3241](https://github.com/magento/inventory/pull/3241), by
  [nikita-shcherbatykh](https://github.com/nikita-shcherbatykh): disabled child products no longer
  keep a configurable in stock.
- [magento/inventory#3294](https://github.com/magento/inventory/pull/3294), by
  [vlmed](https://github.com/vlmed): the distance-based source selection admin uses
  `OptionSourceInterface` instead of the deprecated `ArrayInterface`.
- [magento/inventory#3368](https://github.com/magento/inventory/pull/3368), by
  [adamphh](https://github.com/adamphh): the stock sources import declares its valid columns, so
  its validation errors are clear.
- [magento/inventory#3425](https://github.com/magento/inventory/pull/3425), by
  [thomas-kl1](https://github.com/thomas-kl1): the "Only X left" message applies its threshold and
  display settings in the right order.
- [magento/inventory#3457](https://github.com/magento/inventory/pull/3457), by
  [igorwulff](https://github.com/igorwulff): `UpdateIsSalable` binds the SKU as a string, so the
  query uses the index instead of scanning the table.

From now on, a fix taken from an upstream pull request keeps its author in the commit.

---

# msi-core 1.1.3 — 2026-10-27

### Shipments without a source accept items with unmanaged stock again

Since 1.0.5, a shipment created without a source in a stock with several sources (REST `ShipOrder`
without `extension_attributes.source_code`, or a programmatic shipment) takes its source from the
default source selection. The selection only counts in-stock source items, so one item with
`manage_stock = 0` made the whole shipment unshippable and it was rejected, although the source
deduction skips such items. A shipment the selection split across sources was rejected too, even
when one source held all of it. Items with unmanaged stock no longer count when the source is
resolved, with source reservations on or off, and a split shipment now ships from the
highest-priority source that holds every item on its own. A shipment is still rejected when no
single source holds its items with managed stock, because the source deduction would fail on any
source. Raised by Ryan Hoerr in review of
[rhoerr/mage-os.inventory#1](https://github.com/rhoerr/mage-os.inventory/pull/1).

### The stock visualizer purges for every store view that shows it

The purge paths run in admin, cron and consumer context, where the store-scoped settings resolve to
the default scope. With the panel off by default and on for one store view, no purge ever ran, and
that store kept serving the availability it had cached. A store view showing the exact quantity
under a default in level mode was purged only when the default's level changed, so it showed stale
numbers. The purge now runs when any store view shows the panel, and a SKU is purged when any of
those store views would display it differently with its own display type and thresholds. The
module's `composer.json` also declares the Bundle, Configurable Product, Grouped Product and
Inventory Configuration API modules it uses, so `module:disable` no longer lets one of them go while
the panel needs it. Reported by Ryan Hoerr in
[msi-core#10](https://github.com/jeanmarcos-dev/msi-core/issues/10).

### Inventory locks work on MySQL

The per-source checkout lock was named `inv_src_` plus two md5 hashes, 73 characters. MySQL 5.7.5
and later reject `GET_LOCK` names longer than 64, so on MySQL every order with managed stock failed
with *Could not acquire inventory lock for the requested items*. MariaDB accepts long names, which
hid it. Every inventory lock (checkout, bulk source transfer and compensation) is now named after its
family plus one md5 of its parts, 40 to 46 characters. The hash includes the database name, so two
installations that share a database server, such as staging and production, no longer wait on each
other's locks. In application server mode, the state reset between requests now releases the locks
the request left taken instead of forgetting them on a connection that stays open.

While the old and the new code run side by side during a deploy, they name the same lock
differently and do not exclude each other. Deploy in maintenance mode, as Magento recommends, and
there is no such window. Reported by Ryan Hoerr in
[msi-core#10](https://github.com/jeanmarcos-dev/msi-core/issues/10).

### `create-compensations` can record an order that was already oversold

The oversell guard on the reservation append rejected every negative reservation for a SKU that was
no longer salable, including the ones `inventory:reservation:create-compensations` writes. When an
open order had lost its reservation and its last unit had since been sold to another order, the
compensation that records it failed with *Not enough salable quantity to reserve*. The salable
quantity stayed at 0 instead of -1, so the oversell stayed hidden from the merchant. The output also
printed "Following reservations were created:" above the error. Compensations written by the command
(`event_type` `manual_compensation`) now skip the guard, because they record a sale that already
happened. Orders placed through checkout, the API or the admin are still checked.

### The stock visualizer honours a per-product display type

The panel picked its component from the product's effective display type, which includes the
*Stock Visualizer* override. The AJAX fragments still shaped their payload from the store setting.
With the default `level` display and one product set to *Exact quantity*, the fragment sent a level
and the component read a missing quantity, so an in-stock product showed *Out of stock*. With the
opposite override on a configurable, bundle or grouped product, the panel showed *Out of stock* as
well, and the fragment carried the exact quantity the product was set to hide. Every fragment now
follows the effective display type of the product it describes. On a configurable in variant mode,
the selected variant shows a level when the configurable or the variant is set to level.

### Saving a product refreshes its stock visualizer fragments

The AJAX fragments carried only the stock visualizer tag, which only stock changes purge, and a
composite's fragment did not carry the composite's own id. After a product's *Stock Visualizer*
override changed, the full page cache kept serving the old fragment until it expired: a grouped or
bundle product switched to *Level* showed *Out of stock* and still sent the exact quantities, and a
variant switched to *Level* kept showing its number. Every fragment now also carries the `cat_p` tag
of each product it describes, the composite's included, so saving the product purges it. With the
full page cache disabled, the fragments were sent as publicly cacheable for a day and browsers kept
them through every stock change. They are now sent as non-cacheable, like the rest of the storefront.

### The unit suite runs clean on PHPUnit 12

Magento 2.4.9 and Mage-OS 3 install PHPUnit 12, where the unit suite reported 5 errors, 2 failures,
a warning and a deprecation. Four data providers declared in annotations, which PHPUnit 12 no longer
reads, move to the `#[DataProvider]` attribute; one test stops using `MockBuilder::addMethods()`; one
provider gets the arity of its test; and `isType('array')` becomes a callback. The suite stays green
on PHPUnit 10.5. Tests only: nothing that ships changes.

### Upgrade and behaviour notes

Four behaviours the documentation did not explain, raised by Ryan Hoerr in the Mage-OS port
([mage-os/mageos-inventory#15](https://github.com/mage-os/mageos-inventory/pull/15)):

- [Upgrade][notes-upgrade]: the new code cannot serve requests before `setup:upgrade` adds the
  reservation columns it writes, and that `ALTER` is slow on a large reservation table.
- [Differences from upstream][notes-upstream]: a configurable's children count by their status in
  the default scope.
- [Source reservations][notes-reservations]: a release is capped at the order's outstanding
  balance, so an order without a placement reservation gets none.
- [Stock visualizer][notes-visualizer]: its attribute group is added to every attribute set, even
  while the panel is disabled.

[notes-upgrade]: https://jeanmarcos-dev.github.io/msi-core/start/upgrade/
[notes-upstream]: https://jeanmarcos-dev.github.io/msi-core/reference/upstream-differences/
[notes-reservations]: https://jeanmarcos-dev.github.io/msi-core/features/source-reservations/#reconciliation
[notes-visualizer]: https://jeanmarcos-dev.github.io/msi-core/features/stock-visualizer/#operational-requirements

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
