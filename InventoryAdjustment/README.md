# InventoryAdjustment module

The `InventoryAdjustment` module keeps an append-only history of every source item quantity and status change
in Multi-Source Inventory: who made it, from where, and why.

This module is part of the community MSI distribution. The
[Inventory Management overview](https://developer.adobe.com/commerce/webapi/rest/inventory/index.html)
describes the MSI (Multi-Source Inventory) project in more detail.

## Installation details

This module is optional. Run `bin/magento setup:upgrade` to create the history table.
Recording is on by default and can be switched off under *Stores > Configuration > Catalog > Inventory >
Source Item Adjustment History*, where the retention period and the prune batch size are also set.

## Extensibility

The contracts live in the `InventoryAdjustmentApi` module. Use the interfaces defined there to read the history
or to describe the context of a change.

### CLI commands

- `inventory:adjustment:opening-balance` writes an opening history row for every source item that has stock and
  no history.
- `inventory:adjustment:verify` reports the source items whose quantity no longer matches their history.

### REST endpoints

The `etc/webapi.xml` file defines a `GET /V1/inventory/source-item-adjustments` endpoint, protected by the
`Magento_InventoryAdjustment::view` resource.

### Cron

The `inventory_adjustment_prune` job deletes the rows older than the retention period every day at 02:30.
A retention of `0` days keeps the history forever.
