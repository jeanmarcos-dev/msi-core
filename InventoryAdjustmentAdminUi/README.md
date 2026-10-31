# InventoryAdjustmentAdminUi module

The `InventoryAdjustmentAdminUi` module extends Magento's Admin UI with the source item adjustment history.

This module is part of the community MSI distribution. The
[Inventory Management overview](https://developer.adobe.com/commerce/webapi/rest/inventory/index.html)
describes the MSI (Multi-Source Inventory) project in more detail.

## Installation details

This module is optional and depends on `InventoryAdjustment`. It may be disabled if the history is consumed
only through the REST API or the CLI.

## Extensibility

The `InventoryAdjustmentAdminUi` module contains several extension points.

### UI Components

The `view/adminhtml/ui_component` directory contains the extensible listings: the **Adjustment History** grid
under *Stores > Inventory*, and the history panel of the product form.

### Option sources

The `Model/OptionSource` directory provides the reason, actor type and source options used by the listing filters.
