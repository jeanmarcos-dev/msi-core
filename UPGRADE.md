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
back. It is nevertheless left as a deliberate decision rather than done for you, because a
store that still runs an extension reading them will fail differently — visibly on a
truncated table, silently on a frozen one.

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
     removed while the view still knows about them.
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

## Not a drop-in replacement

The `dist-2.4.x` branches of this fork replace `magento/inventory-*` transparently through
Composer's `replace` directive: same module names, same namespaces, same schema. This line
changes the schema and the data contract, so it cannot share their versioning or their
package identity. Its distribution channel and package name are not settled yet; until
they are, install it from the branch and pin the commit.
