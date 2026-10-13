---
title: Run in production
description: The consumers, cron jobs, privileges and permissions msi-core needs on a live store.
---

## The salability consumer must run

```bash
bin/magento queue:consumers:start inventory.reservations.updateSalabilityStatus
```

This is a requirement, not a tuning recommendation. Placing a reservation publishes a message that
refreshes the stock index; nothing else does. With the consumer down, two things happen at once:

- the index goes stale, so the storefront keeps selling what is already committed;
- `queue_message` grows without bound, so every subsequent reservation pays to write into a larger
  table.

Measured on the reservation path, 8 workers placing 25 reservations each:

| | `place_ms` |
|---|---|
| publisher disabled | 8.5 |
| publisher on, queue drained | 14.2 |
| publisher on, 2568 pending rows | 16.5 |

The 2.3 ms between the last two rows is not a fixed cost: it scales with the table. On a ~620 ms
checkout the whole publish is about 1%, but it does not stay there if nothing consumes.

The standard `consumers_runner` cron covers this. Verify it after the upgrade rather than assuming
it, and alert on the depth of that queue.

## Other consumers

If you enable the [storefront stock visualizer](/msi-core/features/stock-visualizer/) and its purge
strategy resolves to the queue, also run `inventory.stockvisualizer.purge`. The existing MSI consumers
(`inventory.indexer.*`, `inventory.reservations.*`) are unchanged.

## Cron jobs

All in the `default` group.

| Job | Schedule | Runs |
|---|---|---|
| `inventory_adjustment_prune` | `30 2 * * *` | always; deletes nothing while [retention](/msi-core/features/adjustment-history/#retention) is 0 |
| `inventory_reconcile_reservations` | `cataloginventory/source_reservations/reconcile_sweep_cron` | only when the [reconciliation sweep](/msi-core/features/source-reservations/#reconciliation) is enabled |
| `inventory_detect_oversell` | `cataloginventory/source_reservations/oversell_sweep_cron` | only when the [oversell sweep](/msi-core/features/source-reservations/#oversell-detection) is enabled |

## Database privileges

[Legacy write detection](/msi-core/features/legacy-write-detection/) installs triggers and needs the
`TRIGGER` privilege. Without it, `setup:upgrade` logs a warning and continues, and detection stays off
until the privilege is granted and `bin/magento inventory:legacy-stock:writes --enable` is run.

## Admin permissions

Reading the [adjustment history](/msi-core/features/adjustment-history/), over REST, in the admin grid
or in the product form, requires the `Magento_InventoryAdjustment::view` permission. Custom admin roles
and integrations do not get it on upgrade; grant it to the ones that should see the history.

## Disk

The adjustment history adds one row per changed source item. Plan for about 350 bytes per row; [the
sizing guide](/msi-core/features/adjustment-history/#volume) has the formula and how to bound it.
