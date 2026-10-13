---
title: CLI commands
description: The bin/magento commands msi-core adds, their options and exit codes.
---

Exit codes are part of the [versioning contract](/msi-core/reference/versioning/): scripts can rely on
them.

## `inventory:adjustment:verify`

Reports source items whose quantity no longer matches their [adjustment
history](/msi-core/features/adjustment-history/#checking-for-drift).

| Option | Default | |
|---|---|---|
| `--limit` | `1000` | Maximum number of source items to report. |

Exits `0` when nothing drifts and `1` when any item does. At the limit, it says it is showing the first
items and that there may be more.

## `inventory:adjustment:opening-balance`

Writes an opening history row for every source item with stock and no history. See [Opening
balance](/msi-core/features/adjustment-history/#opening-balance).

| Option | Default | |
|---|---|---|
| `--batch-size` | `1000` | Number of source items locked and written per transaction. |

Prints the number of rows written and the `request_id` they share. Exits `2` when `--batch-size` is not
a whole number of at least 1.

## `inventory:legacy-stock:writes`

Reports writes made against the frozen `cataloginventory_*` tables. See [Legacy write
detection](/msi-core/features/legacy-write-detection/).

| Option | |
|---|---|
| `--clear` | Forget every recorded write. |
| `--enable` | Install the detection triggers. |
| `--disable` | Drop the detection triggers. |

Without options it prints the report and exits `1` while any write is on record. `--enable` and
`--disable` together exit `1`.

## `inventory:reservation:reconcile`

Reconciles terminal orders whose reservations were never released. See
[Reconciliation](/msi-core/features/source-reservations/#reconciliation).

| Option | Default | |
|---|---|---|
| `--dry-run` | | Report the residues without writing any compensation. |
| `--limit` | `500` | Maximum number of orders to process. |

Exits `0`. When the limit is reached it says so; run it again for the rest.

## `inventory:reservation:detect-oversell`

Reports source stock positions that are below their committed reservations. See [Oversell
detection](/msi-core/features/source-reservations/#oversell-detection).

| Option | Default | |
|---|---|---|
| `--source` | | Filter by source code. |
| `--sku` | | Filter by SKU. |
| `--limit` | `1000` | Maximum number of oversold positions to scan. |

Exits `0` when nothing is oversold and `1` when any position is.
