# InventoryCatalog

The `InventoryCatalog` module integrates inventory management business logic into Magento's catalog logic.

This module is part of the new inventory infrastructure. The
[Inventory Management overview](https://developer.adobe.com/commerce/webapi/rest/inventory/index.html)
describes the MSI (Multi-Source Inventory) project in more detail.

## Installation details

This module is installed as part of Magento Open Source. Unless a custom implementation for `InventoryCatalogApi`
is provided by a 3rd-party module, the module cannot be deleted or disabled.

## Extension points and service contracts

All public interfaces related to this module are located in the `InventoryCatalogApi` module.
Use the interfaces defined in `InventoryCatalogApi` to extend this module.

## The Default Stock is an ordinary stock

Stock id 1 is still created at installation, but it carries no special behaviour: the MSI
indexers build its index table, the composite indexers cover it, and it can be edited,
assigned sources and deleted like any other stock. `inventory_stock_1` is a real index
table, not a view over `cataloginventory_stock_status`.

The legacy read contracts — `StockRegistryInterface`, `StockStateInterface`,
`StockItemRepositoryInterface`, `StockStatusRepositoryInterface` and
`StockManagementInterface` — are served from MSI by this module, so core and third-party
code that goes through them keeps working. The `cataloginventory_*` tables themselves are
kept for schema compatibility but are neither read nor written; code querying them with raw
SQL sees frozen data. See [`UPGRADE.md`](../UPGRADE.md).

The one place where stock 1 is still named explicitly is
`AdaptStockResolverToAdminWebsitePlugin`, which resolves the admin website to it. Deleting
stock 1 without reassigning the admin website therefore raises an explicit error.
