# InventoryConfiguration module

The `InventoryConfiguration` module implements logic for inventory management configuration.

This module is part of the new inventory infrastructure. The
[Inventory Management overview](https://developer.adobe.com/commerce/webapi/rest/inventory/index.html)
describes the MSI (Multi-Source Inventory) project in more detail.

## Installation details

This module is installed as part of Magento Open Source. Unless a custom implementation for
`InventoryConfigurationApi` is provided by a 3rd-party module, the module cannot be deleted or disabled.

## Extension points and service contracts

All public interfaces related to this module are located in the `InventoryConfigurationApi` module.
Use the interfaces defined in `InventoryConfigurationApi` to extend this module.

## Stock item configuration storage

Since the Default Stock stopped being special, the per-product stock item configuration
(`manage_stock`, `backorders`, `min_qty`, `min_sale_qty`, `max_sale_qty`, `qty_increments`,
`is_in_stock` for composite parents, and the matching `use_config_*` flags) lives in
`inventory_stock_item_configuration`, keyed by **sku**. It is no longer read from, nor
written to, `cataloginventory_stock_item`.

`notify_stock_qty` is duplicated here on purpose: `InventoryLowQuantityNotification`, which
owns the per-source notification thresholds, is optional, so this module cannot depend on it
to hydrate `StockItemConfigurationInterface::getNotifyStockQty()`.

`MigrateLegacyStockItemConfiguration` copies the existing rows over on `setup:upgrade`. See
[`UPGRADE.md`](../UPGRADE.md).
