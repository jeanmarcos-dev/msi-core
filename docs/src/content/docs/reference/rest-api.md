---
title: REST API
description: The endpoint and the extension attribute msi-core adds to the MSI REST API.
---

The MSI REST API is otherwise unchanged: module names, namespaces and service contracts are the same as
upstream, so the [Adobe Commerce inventory REST reference](https://developer.adobe.com/commerce/webapi/rest/inventory/)
applies.

## `GET /V1/inventory/source-item-adjustments`

Reads the [adjustment history](/msi-core/features/adjustment-history/#reading-the-history).

- **ACL:** `Magento_InventoryAdjustment::view`.
- **Input:** `searchCriteria`, with filters on any [history field](/msi-core/features/adjustment-history/).
- **Order:** newest first.
- **Paging:** always paged. `pageSize` above `cataloginventory/source_item_adjustment/max_page_size`
  (500 by default) is rejected with a 400 that names the limit.

## `adjustment` on source items

`POST /V1/inventory/source-items`, and its async variant, accept an `adjustment` extension attribute
on each source item, with `reason`, `note`, `reference_type` and `reference_id`. It sets why the
history records the change. [Saying why over REST](/msi-core/features/adjustment-history/#saying-why-over-rest)
lists the accepted values and the requests that are rejected with a 400.

## Behaviour changes on existing endpoints

- `POST /V1/inventory/bulk-product-source-transfer` moves the reservations of open orders with the
  stock when [source-level reservations](/msi-core/features/source-reservations/#transfers-respect-reservations)
  are enabled, and is refused when the destination is not assigned to the stock of such an order.
- `POST /V1/inventory/bulk-partial-source-transfer` sums a repeated SKU before validating, rejects zero
  or negative quantities, and moves every item or none.
- `ShipOrder` without `extension_attributes.source_code` on a stock with several sources [resolves the
  source](/msi-core/features/source-reservations/#shipping-from-the-reserved-sources) or rejects the
  shipment with a validation message.
