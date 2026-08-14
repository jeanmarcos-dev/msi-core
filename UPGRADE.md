# Upgrading to the MSI-only inventory line

This branch removes the special treatment of the **Default Stock** and stops MSI from
writing the `cataloginventory_*` tables. Stock id 1 is still created at installation, but
it is an ordinary stock: it is indexed, edited, and read exactly like any stock you create
yourself.

**This is a breaking change.** Read this page before upgrading a live store.

## Why

In upstream MSI the Default Stock is not a stock at all. `inventory_stock_1` is a MySQL
view over `cataloginventory_stock_status`, the MSI indexer skips stock 1 on purpose, and
the salability of composite products for that stock is computed by the legacy
CatalogInventory indexer instead of the MSI ones. Around fifty `if ($stockId ===
$defaultStockId)` branches across twenty modules keep the two worlds apart, each with its
own data path.

That split is the root of a whole class of defects — a bug fixed on one side of the `if`
stays open on the other — and it forces every new feature to be written and tested twice.
Collapsing it removes the divergence at the source.

## What changes

| | Before | After |
|---|---|---|
| `inventory_stock_1` | MySQL view over `cataloginventory_stock_status` | real index table, built by the MSI indexer |
| Composite salability in stock 1 | legacy CatalogInventory indexer | `Inventory*ProductIndexer` |
| Stock item configuration | `cataloginventory_stock_item` | `inventory_stock_item_configuration` (keyed by sku) |
| `cataloginventory_stock_item` / `_stock_status` | written on every stock change | never written again |
| `cataloginventory_stock` indexer | rebuilds the legacy index | registered but inert |
| Stock id 1 | undeletable, sources not assignable | ordinary stock |

The new configuration table is keyed by **sku alone**, exactly like the legacy one it replaces.
Giving each stock its own `manage_stock`, `backorders` or `min_qty` would be the natural next step
in MSI, but it changes the data model rather than the storage, so it is deliberately left out: a
configuration read for stock 5 answers the same as one read for stock 1.

The legacy read contracts are unchanged and keep working: `StockRegistryInterface`,
`StockStateInterface`, `StockItemRepositoryInterface`, `StockStatusRepositoryInterface`
and `StockManagementInterface` are all served from MSI. Code that goes through them needs
no change.

## The `cataloginventory_*` tables are kept, empty and frozen

They are **not** dropped. Their `db_schema.xml` belongs to `Magento_CatalogInventory`,
which 42 core modules of Magento Open Source 2.4.9 require through Composer and which
therefore cannot be uninstalled; declarative schema would recreate the tables on the next
`setup:upgrade` anyway.

So after the upgrade the tables still exist and still hold whatever rows they had at the
moment of the cut. **Those rows are stale from that moment on** — nothing updates them,
and products created afterwards get no row at all:

```
a product created after the upgrade
  cataloginventory_stock_item    0 rows
  cataloginventory_stock_status  0 rows
  inventory_source_item          1 row
  inventory_stock_item_configuration  1 row
  inventory_stock_1              quantity 33, is_salable 1
```

Truncating them is safe: with both tables empty, the conformance suite, the functional end
to end suite and a concurrent checkout run all pass unchanged, and nothing writes a row
back. It is nevertheless left as a deliberate decision rather than done for you, and there
is a reason to lean against it. A store that still runs an extension *reading* them fails
differently — visibly on a truncated table, silently on a frozen one — but keeping the rows
is what lets the write detector below see an extension *writing* them. Reads argue for
truncating, writes argue for keeping; neither is free.

## What breaks

Anything that reads `cataloginventory_stock_item` or `cataloginventory_stock_status` with
**raw SQL**, instead of going through the CatalogInventory service contracts. This is an
accepted, documented break — the whole point of the change is that those tables stop being
a source of truth.

Every such reader inside Magento itself has been redirected to MSI, including the ones a
literal grep does not find because they reach the table through
`$stockItemResource->getMainTable()`:

- the `qty` and stock status columns and filters of the admin product grid;
- the Quantity column of the widget grids (product pickers of reviews, url rewrites,
  bundle options and downloadable);
- the bundle selection filter that builds dynamic bundle prices;
- the stock columns of the product export;
- the bundle and configurable price indexers;
- the low stock report, already replaced by MSI's own source-based grid.

If you maintain an extension that queries those tables, port it to
`StockRegistryInterface` (legacy-shaped reads), `GetStockItemDataInterface` (index reads,
per stock) or `GetStockItemConfigurationInterface` (configuration). If you cannot, the
data it reads will be stale.

### Third-party code that writes the legacy tables

Writes are not blocked, they are simply ignored: nothing reads those tables back. An
extension that sets a quantity by writing `cataloginventory_stock_item` directly will
appear to succeed and change nothing. Point it at `SourceItemsSaveInterface`, or at
`StockRegistryInterface::updateStockItemBySku()`, which MSI projects onto the default
source item.

Because that failure is silent, the upgrade installs four database triggers that count
those writes into `inventory_legacy_stock_write`. An admin notification then names the
table and the number of ignored writes, and the same record is available from the console:

```
bin/magento inventory:legacy-stock:writes            # report; exits non-zero while any write is on record
bin/magento inventory:legacy-stock:writes --clear    # acknowledge and forget them
bin/magento inventory:legacy-stock:writes --disable  # drop the triggers
```

The report is a starting point, not an audit. A trigger fires per row, so it catches every
`INSERT` and every `UPDATE` that matches at least one row — but an `UPDATE` matching nothing
is invisible to the database, and no trigger can see it. **That is a reason not to truncate
the tables**: while they still hold their pre-upgrade rows, an extension updating a product
by id still hits a row and gets counted; on an empty table the same statement passes
unnoticed. The triggers need the `TRIGGER` privilege; without it `setup:upgrade` logs a
warning and continues, and detection stays off until the privilege is granted and
`--enable` is run.

CatalogInventory itself used to be the loudest writer here: saving the inventory
configuration section made it rewrite every `cataloginventory_stock_item` row through
`UpdateItemsStockUponConfigChangeObserver` — 2464 discarded writes on a 2.5k product
catalog, and a full pass over the table for nothing. That observer is disabled, so what the
detector reports is third-party code and not Magento talking to itself.

## Upgrading

The upgrade is a maintenance operation. Expect downtime proportional to your catalog size.

1. **Back up the database.** There is no automatic downgrade, see below.
2. Put the store in maintenance mode.
3. `bin/magento setup:upgrade` — this applies:
   - `ReplaceLegacyStockStatusViewWithIndexTable`: drops the `inventory_stock_1` view and
     lets the indexer create the real table;
   - `MigrateLegacyStockItemConfiguration`: copies the per-product configuration from
     `cataloginventory_stock_item` into `inventory_stock_item_configuration`, for the rows
     of stock 1 in scope 0 — the only ones upstream ever wrote. On a large catalog this is
     the slow step;
   - `DisableLegacyStockIndexer`: switches `cataloginventory_stock` back to realtime mode,
     which is the only way to drop its mview triggers — the subscriptions have to be
     removed while the view still knows about them;
   - `DetectLegacyStockWrites`: installs the triggers that count writes made against the
     frozen tables. It needs the `TRIGGER` privilege and only warns when it is missing.
4. `bin/magento indexer:reindex inventory` — required: stock 1 has no index rows yet. The
   schema patch already invalidates the indexer, so a cron-driven reindex would eventually
   pick it up too; do not rely on that during an upgrade window.
5. `bin/magento indexer:reindex catalog_product_price catalogsearch_fulltext` — both read
   salability and were fed by the legacy index before.
6. `bin/magento cache:flush`, leave maintenance mode.

`cataloginventory_stock` stays in the indexer list on purpose: `Magento_Elasticsearch`
declares `catalogsearch_fulltext` as depending on it, and the framework throws when an
indexer id cannot be resolved. Reindexing it is a no-op that completes instantly.

## Downgrading

There is no downgrade path. Going back to the upstream line needs the legacy tables
repopulated from MSI — quantities from `inventory_source_item` for the default source,
configuration from `inventory_stock_item_configuration` — and the `inventory_stock_1`
view recreated. Restore the backup instead.

## Deployment requirements

### The salability consumer must run

```bash
bin/magento queue:consumers:start inventory.reservations.updateSalabilityStatus
```

This is a requirement, not a tuning recommendation. Placing a reservation publishes a
message that refreshes the stock index; nothing else does. With the consumer down, two
things happen at once: the index goes stale, so the storefront keeps selling what is
already committed, and `queue_message` grows without bound, so every subsequent
reservation pays to write into a larger table.

Measured on the reservation path, 8 workers placing 25 reservations each:

| | `place_ms` |
|---|---|
| publisher disabled | 8.5 |
| publisher on, queue drained | 14.2 |
| publisher on, 2568 pending rows | 16.5 |

The 2.3 ms between the last two rows is not a fixed cost — it scales with the table. On a
~620 ms checkout the whole publish is about 1%, but it does not stay there if nothing
consumes.

The standard `consumers_runner` cron covers this. Verify it after the upgrade rather than
assuming it, and alert on the depth of that queue.

### Other consumers

If you enable the storefront stock visualizer with the queue purge strategy, also run
`inventory.stockvisualizer.purge`. The existing MSI consumers (`inventory.indexer.*`,
`inventory.reservations.*`) are unchanged by this upgrade.

## Not a drop-in replacement

The `dist-2.4.x` branches of this fork replace `magento/inventory-*` transparently through
Composer's `replace` directive: same module names, same namespaces, same schema. This line
changes the schema and the data contract, so it cannot share their versioning or their
package identity.

It ships as `jeanmarcos/msi-core` under plain Semantic Versioning:

```bash
composer remove jeanmarcos/inventory
composer require "jeanmarcos/msi-core:^1.0"
```

The two packages provide the same modules and cannot coexist. `msi-core` declares an
explicit `conflict` on `jeanmarcos/inventory`, so Composer refuses a tree holding both
rather than producing a broken one — remove the old package first.

See [`VERSIONING.md`](VERSIONING.md) for what a version number promises here, and which
parts of the codebase that promise covers.
