# Versioning

This repository publishes two packages under two different schemes, because they promise
different things.

| Package | Scheme | Promise |
|---|---|---|
| `jeanmarcos/inventory` | `2.4.M.n` | Nothing ever breaks. It is the MSI of Magento 2.4.M with fixes. |
| `jeanmarcos/msi-core` | Semantic Versioning | A major version may break. Minor and patch may not. |

## `jeanmarcos/inventory` — the `dist-2.4.x` lines

Tagged `2.4.M.n`: the first three segments mirror the Magento line the build targets, and `n` is
this distribution's release counter for that line. `2.4.9.20` is the twentieth release for Magento
2.4.9.

The scheme is not semantic, and it does not need to be. These builds are drop-in replacements:
same module names, same namespaces, same schema, same behaviour plus fixes. There is no version
at which upgrading within a line can break you, so there is no major to bump. Every line has its
own branch, and a fix that applies to several is ported to each and tagged separately — which is
why [`CHANGELOG.md`](CHANGELOG.md) is organised by change rather than by release.

The fourth segment also avoids colliding with Adobe's `-p1` security-patch naming, and stays inside
Composer's four-component limit.

## `jeanmarcos/msi-core` — this line

Tagged with plain [Semantic Versioning](https://semver.org): `1.0.0`, `1.1.0`, `2.0.0`.

**The Magento version is not part of the version number.** It is enforced by the
`magento/framework` constraint in `composer.json`, so Composer refuses to install a build on a
Magento it was not made for. Encoding it in the version as well would produce numbers like
`2.4.9.1.0.0`, which Composer rejects outright — it parses at most four numeric components — and
would tie a breaking change of ours to a Magento release that has nothing to do with it.

`1.0.0` is the first release in which MSI is the only inventory system. The Default Stock work that
gets it there is a break relative to `jeanmarcos/inventory`, not relative to a previous `msi-core`;
there is no `0.x` to migrate from.

### What a version number covers

A **major** bump is required to change any of the following. A **minor** may add to them; a
**patch** may not alter them at all.

- **PHP classes and interfaces annotated `@api`.** 215 of them, 165 under `Inventory*Api`. This is
  Magento's own marker for supported extension surface, and this fork keeps it: signatures, return
  types and thrown exceptions of `@api` members are the contract.
- **Database schema** of the `inventory_*` tables — column names, types and meaning. Adding a
  nullable column is a minor; changing what an existing one holds is a major.
- **Store configuration paths** under `cataloginventory/source_reservations/*` and
  `cataloginventory/stock_visualizer/*`. Renaming a path, or changing what a value means, is a
  major; adding one is a minor. Default values may change in a minor when the previous default was
  a defect, and the changelog says so explicitly.
- **Message queue topics** and the shape of what travels on them, `inventory.*`. A consumer you
  wrote against a topic keeps working across minors.
- **CLI commands** and their options: `inventory:legacy-stock:writes`,
  `inventory:reservation:reconcile`, `inventory:reservation:detect-oversell`. Exit codes are part
  of the contract; scripts depend on them.
- **Indexer ids and index table structure**, including `inventory_stock_<id>`, which extensions
  legitimately read.

### What it does not cover

- Anything **not** annotated `@api`. A class under `Model/`, `Plugin/` or `ResourceModel/` without
  the annotation can be renamed, split or deleted in a minor. Magento's convention applies here as
  it does upstream: if you extend a non-`@api` class, you are pinned to a patch range and you know
  it.
- **Frozen legacy tables.** `cataloginventory_stock_item` and `cataloginventory_stock_status` are
  no longer written and are not a contract. See [`UPGRADE.md`](UPGRADE.md).
- **Templates, layout XML and frontend markup.** Themes override these; a minor may restructure
  them. The stock visualizer panel in particular is expected to evolve.
- **Tests, fixtures and MFTF artifacts.** Never part of the public surface, in any version.
- **Behaviour that was a defect.** Fixing a bug is a patch even when code depended on the bug.

### Deprecation

Anything covered above is deprecated for at least one minor version before a major removes it.
Deprecations carry `@deprecated` with the replacement named, are listed in the changelog under the
release that introduces them, and keep working until the major.

The one exception is a security fix that cannot be made compatibly. It ships in a patch, and the
changelog says why.

### Pinning

```jsonc
"jeanmarcos/msi-core": "^1.0"   // recommended: fixes and features, no breaks
"jeanmarcos/msi-core": "~1.2.0" // conservative: fixes only
```

Pinning an exact version is discouraged: it excludes security patches.

## Which Magento each build targets

| Package version | `magento/framework` | Magento | PHP |
|---|---|---|---|
| `jeanmarcos/inventory:2.4.7.*` | `>=103.0.7 <103.0.8` | 2.4.7 | 8.1 – 8.3 |
| `jeanmarcos/inventory:2.4.8.*` | `>=103.0.8 <103.0.9` | 2.4.8 | 8.2 – 8.4 |
| `jeanmarcos/inventory:2.4.9.*` | `>=103.0.9 <103.0.10` | 2.4.9 | 8.3 – 8.5 |
| `jeanmarcos/msi-core:^1.0` | `>=103.0.9 <103.0.10` | 2.4.9 | 8.3 – 8.5 |

When Magento 2.4.10 arrives, `msi-core` widens its constraint in a minor if nothing breaks, or in a
major if the new baseline forces a change. It does not fork a new branch per Magento line the way
`jeanmarcos/inventory` does — that scheme exists to serve versions Adobe still supports, and it is
the reason those lines are cherry-pick-only.
