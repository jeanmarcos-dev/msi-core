---
title: Configuration
description: Every store configuration path msi-core adds, with its default and the page that explains it.
---

All paths are global (default scope) unless noted, under **Stores › Configuration › Catalog ›
Inventory**.

## Source-Level Reservations

| Path | Default | |
|---|---|---|
| `cataloginventory/source_reservations/enabled` | `0` | [Source-level reservations](/msi-core/features/source-reservations/). Changing it needs a full `inventory` reindex. |
| `cataloginventory/source_reservations/reconcile_cancel_refund` | `0` | [Reconciliation](/msi-core/features/source-reservations/#reconciliation) when an order reaches a final state. |
| `cataloginventory/source_reservations/reconcile_sweep_enabled` | `0` | Periodic [reconciliation sweep](/msi-core/features/source-reservations/#reconciliation). |
| `cataloginventory/source_reservations/reconcile_sweep_cron` | `0 * * * *` | Schedule of the reconciliation sweep. |
| `cataloginventory/source_reservations/oversell_detection_enabled` | `0` | [Oversell detection](/msi-core/features/source-reservations/#oversell-detection) on stock writes. |
| `cataloginventory/source_reservations/oversell_sweep_enabled` | `0` | Periodic oversell sweep. |
| `cataloginventory/source_reservations/oversell_sweep_cron` | `0 * * * *` | Schedule of the oversell sweep. |

## Source Item Adjustment History

| Path | Default | |
|---|---|---|
| `cataloginventory/source_item_adjustment/enabled` | `1` | Record the [adjustment history](/msi-core/features/adjustment-history/). |
| `cataloginventory/source_item_adjustment/max_page_size` | `500` | Largest page the [REST endpoint](/msi-core/reference/rest-api/) returns. |
| `cataloginventory/source_item_adjustment/retention_days` | `0` | Days a row is kept; `0` keeps every row. At most 36 500. See [Retention](/msi-core/features/adjustment-history/#retention). |
| `cataloginventory/source_item_adjustment/prune_batch_size` | `5000` | Rows deleted per prune batch. At most 100 000. |

## Storefront Stock Visualizer

| Path | Default | |
|---|---|---|
| `cataloginventory/stock_visualizer/enabled` | `0` | Show the [Availability panel](/msi-core/features/stock-visualizer/). |
| `cataloginventory/stock_visualizer/display_type` | `level` | `level` or `quantity`. |
| `cataloginventory/stock_visualizer/mode` | `on_demand` | `on_demand` or `instant`. |
| `cataloginventory/stock_visualizer/ttl` | `0` | Public-cache lifetime of the AJAX fragments, in seconds. |
| `cataloginventory/stock_visualizer/level_basis` | `quantity` | Absolute thresholds or a per-product full quantity. |
| `cataloginventory/stock_visualizer/level_high` | `10` | High level threshold. |
| `cataloginventory/stock_visualizer/level_low` | `3` | Low level threshold. |
| `cataloginventory/stock_visualizer/scope` | `aggregate` | `aggregate` or `per_source`. |
| `cataloginventory/stock_visualizer/show_source_labels` | `1` | Source name on per-source rows. |
| `cataloginventory/stock_visualizer/hide_empty_sources` | `1` | Omit out-of-stock sources. |
| `cataloginventory/stock_visualizer/composite_configurable_mode` | `variant` | `variant`, `children` or `status`. |
| `cataloginventory/stock_visualizer/composite_bundle_mode` | `max` | `max`, `children` or `status`. |
| `cataloginventory/stock_visualizer/composite_grouped_mode` | `children` | `children` or `status`. |
| `cataloginventory/stock_visualizer/composite_grouped_sets_calculator` | `0` | Complete-sets calculator for grouped products. |

The visualizer settings can be set per store; scope, delivery mode and the composite modes are
store-scoped only, and the display type and thresholds can also be overridden per product.
