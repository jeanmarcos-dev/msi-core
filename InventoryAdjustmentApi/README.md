# InventoryAdjustmentApi module

The `InventoryAdjustmentApi` module provides service contracts for the source item adjustment history.

This module is part of the community MSI distribution. The
[Inventory Management overview](https://developer.adobe.com/commerce/webapi/rest/inventory/index.html)
describes the MSI (Multi-Source Inventory) project in more detail.

## Installation details

This module is installed together with `InventoryAdjustment`. Third-party modules that only need to read the
history or to describe the context of a change can depend on this module alone.

## Extensibility

The `InventoryAdjustmentApi` module contains extension points and APIs that 3rd-party developers
can use to record or read inventory adjustments.

### Public APIs

Public APIs are defined in the `Api` and `Api/Data` directories.

### Reasons and actor types

The `AdjustmentReason` and `ActorType` enums in the `Model` directory list the values an adjustment can carry.
