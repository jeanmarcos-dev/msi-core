---
title: Versioning
description: What an msi-core version number promises, which parts of the codebase the promise covers, and how to pin it.
---

msi-core is tagged with plain [Semantic Versioning](https://semver.org): `1.0.0`, `1.1.0`, `2.0.0`. A
major version may break; a minor or a patch may not.

**The Magento version is not part of the version number.** It is enforced by the `magento/framework`
constraint in `composer.json`, so Composer refuses to install a build on a Magento it was not made
for. Encoding it in the version as well would produce numbers like `2.4.9.1.0.0`, which Composer
rejects outright, since it parses at most four numeric components, and would tie a breaking change of
ours to a Magento release that has nothing to do with it.

`1.0.0` is the first release in which MSI is the only inventory system. The Default Stock work that
gets it there is a break relative to `jeanmarcos/inventory`, not relative to a previous msi-core;
there is no `0.x` to migrate from.

## What a version number covers

A **major** bump is required to change any of the following. A **minor** may add to them; a **patch**
may not alter them at all.

- **PHP classes and interfaces annotated `@api`.** 221 of them as of 1.1.0, 175 under `Inventory*Api`.
  This is Magento's own marker for supported extension surface, and msi-core keeps it: signatures, return types and thrown exceptions of `@api` members are
  the contract.
- **Database schema** of the `inventory_*` tables: column names, types and meaning. Adding a nullable
  column is a minor; changing what an existing one holds is a major.
- **Store configuration paths** under `cataloginventory/source_reservations/*`,
  `cataloginventory/source_item_adjustment/*` and `cataloginventory/stock_visualizer/*`. Renaming a
  path, or changing what a value means, is a major; adding one is a minor. Default values may change in
  a minor when the previous default was a defect, and the changelog says so explicitly.
- **Message queue topics** and the shape of what travels on them, `inventory.*`. A consumer you wrote
  against a topic keeps working across minors.
- **CLI commands** and their options: `inventory:legacy-stock:writes`,
  `inventory:reservation:reconcile`, `inventory:reservation:detect-oversell`,
  `inventory:adjustment:verify` and `inventory:adjustment:opening-balance`. Exit codes are part of the
  contract; scripts depend on them.
- **REST endpoints** msi-core adds, such as `GET /V1/inventory/source-item-adjustments`, and the
  `adjustment` extension attribute.
- **Indexer ids and index table structure**, including `inventory_stock_<id>`, which extensions
  legitimately read.

## What it does not cover

- Anything **not** annotated `@api`. A class under `Model/`, `Plugin/` or `ResourceModel/` without the
  annotation can be renamed, split or deleted in a minor. Magento's convention applies here as it does
  upstream: if you extend a non-`@api` class, you are pinned to a patch range and you know it.
- **Frozen legacy tables.** `cataloginventory_stock_item` and `cataloginventory_stock_status` are no
  longer written and are not a contract. See [Upgrade from Magento MSI](/msi-core/start/upgrade/).
- **Templates, layout XML and frontend markup.** Themes override these; a minor may restructure them.
  The stock visualizer panel in particular is expected to evolve.
- **Tests, fixtures and MFTF artifacts.** Never part of the public surface, in any version.
- **Behaviour that was a defect.** Fixing a bug is a patch even when code depended on the bug.

## Deprecation

Anything covered above is deprecated for at least one minor version before a major removes it.
Deprecations carry `@deprecated` with the replacement named, are listed in the changelog under the
release that introduces them, and keep working until the major.

The one exception is a security fix that cannot be made compatibly. It ships in a patch, and the
changelog says why.

## Pinning

```jsonc
"jeanmarcos/msi-core": "^1.0"   // recommended: fixes and features, no breaks
"jeanmarcos/msi-core": "~1.2.0" // conservative: fixes only
```

Pinning an exact version is discouraged: it excludes security patches.

## Which Magento each version targets

| Package version | `magento/framework` | Magento | PHP |
|---|---|---|---|
| `jeanmarcos/msi-core:^1.0` | `>=103.0.9 <103.0.10` | 2.4.9 | 8.3 – 8.5 |

When Magento 2.4.10 arrives, msi-core widens its constraint in a minor if nothing breaks, or in a major
if the new baseline forces a change. It does not fork a new branch per Magento line.

## The drop-in package

[`jeanmarcos/inventory`](https://github.com/jeanmarcos-dev/inventory), which is no longer
maintained, is versioned differently, as
`2.4.M.n`: the first three segments mirror the Magento line the build targets, and `n` is the
release counter for that line. Those builds are drop-in replacements, with the same module names,
namespaces, schema and behaviour plus fixes, so there is no version at which upgrading within a line
can break, and no major to bump. The fourth segment also avoids colliding with Adobe's `-p1`
security-patch naming and stays inside Composer's four-component limit.
