---
title: Differences from upstream
description: Everything msi-core changes relative to the MSI that ships with Magento 2.4.9.
---

msi-core is based on the MSI of Magento 2.4.9, synced with `magento/inventory` `develop`, and keeps
the original `Magento_Inventory*` module names and `Magento\Inventory*` namespaces.

## Features

| | Upstream MSI | msi-core |
|---|---|---|
| Default Stock | a MySQL view over `cataloginventory_stock_status`, special-cased in about fifty places | [an ordinary stock](/msi-core/features/default-stock/), indexed by MSI |
| Stock item configuration | `cataloginventory_stock_item` | `inventory_stock_item_configuration` |
| Legacy `cataloginventory_*` tables | written on every stock change | frozen; [writes are reported](/msi-core/features/legacy-write-detection/) |
| Reservations | per stock | [per source](/msi-core/features/source-reservations/), opt-in |
| Place-order lock | per stock, around place-order | per source, around the reservation write, only for SKUs that get a reservation |
| Ledger repair | `inventory:reservation:create-compensations` | plus [reconciliation and oversell detection](/msi-core/features/source-reservations/#reconciliation) |
| Salable quantity in the admin | one figure per stock | [broken down by source](/msi-core/features/salable-by-source/) |
| Stock change history | none | [every source item change recorded](/msi-core/features/adjustment-history/) |
| Storefront availability | in stock or out of stock | an [Availability panel](/msi-core/features/stock-visualizer/), opt-in |
| Disabled children of a configurable | the stock index counts them toward the parent's salability, and the storefront then counts the enabled, salable children of the store one product at a time | excluded by their status in the websites of the stock ([`SelectBuilder`](https://github.com/jeanmarcos-dev/msi-core/blob/main/InventoryConfigurableProductIndexer/Indexer/SelectBuilder.php)). `status` is a website attribute, so the index row counts a child enabled in at least one website of the stock, by the website's value or else the default scope. The storefront, GraphQL `stock_status` and the search index narrow that to the current website, in one query for a whole listing: a configurable is salable there only if one of its children is enabled in, assigned to and salable in that website. A configurable with no enabled child is not salable and is hidden from listings. The stock APIs that take a stock id answer for the whole stock. Two store views of one website with different `status` values, which only direct SQL can produce, are not supported: the website takes the value of its default store view |
| Backorders on virtual and downloadable products | follow the product's setting; an order sold on backorder is invoiced without a deduction and its reservation stays open | [not admitted](#virtual-and-downloadable-products-do-not-admit-backorders) when the product manages stock |
| Status of a source that a shipment empties | stays In Stock at its out-of-stock threshold; a return to stock follows the product's stock status | [goes Out of Stock](#a-source-that-a-shipment-empties-goes-out-of-stock); a return brings it back by its own status |
| Source item save with a source code that differs from the stored one only in case | saved as sent; the admin product grid then fails on that row | rejected with a validation error that names the stored code |

### Virtual and downloadable products do not admit backorders

In msi-core, physical stock never goes below 0, and a backorder waits for stock to arrive before it
ships. A virtual or downloadable product has nothing to ship, and invoicing it deducts its stock at
once, so it cannot wait. With managed stock, such a product is sold only up to its stock: its
backorders setting counts as *No* and its out-of-stock threshold as 0 or above, whatever its own or
the global configuration says. The rule applies to the salable check, the salable quantity, the
stock index, the cart and order placement. Physical products keep their backorders, and a bundle
follows its children.

The types are a list on `Magento\InventoryConfiguration\Model\GetNonBackorderableProductTypes`. To
let a type admit backorders again, set its item to `null` in your module's `di.xml`:

```xml
<type name="Magento\InventoryConfiguration\Model\GetNonBackorderableProductTypes">
    <arguments>
        <argument name="types" xsi:type="array">
            <item name="virtual" xsi:type="null"/>
        </argument>
    </arguments>
</type>
```

A product type of your own with nothing to ship can be added to the list the same way.

### A source that a shipment empties goes out of stock

Upstream lowers a source item's quantity when an order ships, or when virtual items are invoiced,
but never writes its status, so a source shipped down to its out-of-stock threshold stays In Stock
([magento/inventory#3478](https://github.com/magento/inventory/issues/3478)). In msi-core the
deduction writes the status in the same update as the quantity, and only when the source changes
state:

- a deduction that leaves the quantity at or below the product's out-of-stock threshold, for a
  product without backorders, sets the source item Out of Stock;
- a return to stock that lifts an Out of Stock source item above the threshold sets it In Stock,
  only when *Set Items' Status to be In Stock When Order is Cancelled*
  (`cataloginventory/options/can_back_in_stock`) is on. This includes a source that was marked Out
  of Stock by hand;
- anything else leaves the status alone, so a source marked Out of Stock by hand stays that way
  through later shipments.

Two results differ from what an upstream store shows. A return brings a source back by the source
item's own status, no longer by the product's stock status. And with an out-of-stock threshold above
0, a source that a deduction leaves Out of Stock keeps its remaining units, which no longer count
towards the salable quantity, as when a merchant marks the source Out of Stock by hand.

Writing the computed status on every deduction would also fix the upstream bug, but it would undo a
status that a merchant set by hand. msi-core writes it only when the source crosses the threshold.

## Fixes

Curated fixes are applied on top of the baseline. Cherry-picked upstream commits keep their original
subject and carry a `[picked #NNNN]` marker. The [changelog](/msi-core/project/changelog/) lists every
fix with the release that carries it.

## Removed modules

- **`InventoryRequisitionList`** is removed: it is proprietary and not covered by the OSL-3.0 / AFL-3.0
  open source license.
- **`InventoryLogging`** is removed: it targets the Adobe Commerce `Magento_Logging` module and is
  excluded from the upstream Open Source metapackage, so shipping it would break `setup:di:compile` on
  Magento Open Source.

## Packaging

msi-core uses Composer's `replace` directive to provide the 73 open-source MSI modules that ship with
Magento, plus its own modules, for both the `magento/*` and the `mage-os/*` package names. It pins
`magento/framework` to its target line and declares a `conflict` on `jeanmarcos/inventory`.
