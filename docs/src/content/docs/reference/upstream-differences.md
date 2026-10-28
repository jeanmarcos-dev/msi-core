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
| Disabled children of a configurable | still count toward the parent's salability | excluded, by their status in the default scope ([`SelectBuilder`](https://github.com/jeanmarcos-dev/msi-core/blob/main/InventoryConfigurableProductIndexer/Indexer/SelectBuilder.php)). The index is per stock, not per store, so a child disabled globally but enabled on one store view counts as disabled everywhere, and the other way round |
| Backorders on virtual and downloadable products | follow the product's setting; an order sold on backorder is invoiced without a deduction and its reservation stays open | [not admitted](#virtual-and-downloadable-products-do-not-admit-backorders) when the product manages stock |

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
