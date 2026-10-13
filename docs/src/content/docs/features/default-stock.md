---
title: Default Stock as a regular stock
description: How msi-core turns stock 1 into an ordinary MSI stock and moves stock item configuration into MSI.
---

In upstream MSI, `inventory_stock_1` is a MySQL view over `cataloginventory_stock_status`. In msi-core
it is a real index table built by the MSI indexer, and stock 1 is indexed, read, edited and deleted
like any stock you create yourself.

:::caution
This is the breaking change of msi-core. [Upgrade from Magento MSI](/msi-core/start/upgrade/)
describes what it breaks and how to migrate.
:::

## One code path for every stock

- The MSI stock indexer no longer skips stock 1, and the flag that forced it through the exception
  path is gone.
- `InventoryConfigurableProductIndexer`, `InventoryBundleProductIndexer` and
  `InventoryGroupedProductIndexer` cover stock 1, so composite salability comes from MSI instead of
  the legacy CatalogInventory indexer. The plugins that used to patch the gap by driving the legacy
  indexer are removed.
- The `if ($stockId === $defaultStockId)` branch is gone from the salability readers, the product
  collections and filters, the price index, search, catalog rules and visual merchandiser: around
  fifty branches across twenty modules.
- The read fallback to `cataloginventory_stock_item` is removed. It only existed because the view held
  no rows for disabled products; the real table does, because the index is built from
  `inventory_source_item` rather than from the enabled catalog.
- Stock 1 is editable and deletable, and its sources can be assigned and unassigned. The admin website
  still resolves to stock 1, and fails with a clear error rather than a raw `NoSuchEntityException`
  when someone deletes it.

## Stock item configuration lives in MSI

`manage_stock`, `backorders`, `min_qty`, `qty_increments` and the rest lived in
`cataloginventory_stock_item` for **every** stock, not just the default, so no stock was ever
independent of the legacy system.

- The configuration lives in `inventory_stock_item_configuration`, keyed by sku. A data patch migrates
  the rows upstream actually wrote (stock 1, scope 0).
- The indexer joins the new table, which removes the hidden `stock_id = 1` condition its joins carried.
- The configuration of a deleted product is dropped with it.

Per-stock or per-source configuration overrides would be the natural next step and are deliberately
out of scope: they change the data model rather than its storage. A configuration read for stock 5
answers the same as one for stock 1, exactly as upstream.

## The legacy tables are frozen

MSI no longer writes `cataloginventory_stock_item` or `cataloginventory_stock_status`. The whole
synchronisation layer is deleted, and the legacy `cataloginventory_stock` indexer is inert. The tables
are kept, because `Magento_CatalogInventory` is required by 42 core modules and cannot be uninstalled.
Writes that still reach them are counted by [legacy write
detection](/msi-core/features/legacy-write-detection/).

The legacy read contracts are unaffected. `StockRegistryInterface`, `StockStateInterface`,
`StockItemRepositoryInterface`, `StockStatusRepositoryInterface` and `StockManagementInterface` are
served from MSI, so code going through them needs no change.

## Core readers redirected to MSI

Every reader of the legacy tables inside Magento reads the MSI index, including those that reach the
table through `$stockItemResource->getMainTable()` and are invisible to a grep:

- the `qty` and stock status columns and filters of the admin product grid;
- the Quantity column of the widget grids (review, url rewrite, bundle option and downloadable product
  pickers);
- the bundle selection filter behind dynamic bundle prices;
- the stock columns of the product export;
- the bundle and configurable price indexers;
- the low stock report.

## Defects fixed along the way

- A configuration-only save of a legacy stock item did not rebuild the stock index, so a
  `manage_stock` change did not reach salability.
- A full stock reindex left catalog pages stale in the full page cache.
- The composite stock status settled before the source items were reindexed, so a parent could report
  the previous state.
- An import carrying no stock column rewrote the configuration; one carrying no quantity zeroed the
  default source item.
- A products filter arriving grouped had only its first id read.
- A sku the index does not carry is reported out of stock instead of raising.
- The stock registry snapshot was not dropped when source items were saved or deleted.
- A required bundle option with nothing to sell was ignored when pricing the bundle.
- A product page outside the stock broke instead of hiding the quantity message.

## Performance

- A list of stock items is answered from MSI without loading the legacy rows it replaces, and the
  product type lookup is skipped when every sku already carries a quantity.
- Saving the inventory configuration section no longer rewrites every `cataloginventory_stock_item`
  row: measured at 2464 discarded writes on a 2.5k product catalog, a full pass over the table for
  nothing.
- A legacy stock item save triggers a reindex only when it moved something the index reads.
