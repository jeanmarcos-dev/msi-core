# Magento Inventory Project (a.k.a MSI)

Welcome to the Magento Inventory community project!

## Overview

The Multi-Source Inventory (MSI) project is designed to enable stock management in multiple locations so that merchants can properly reflect their physical inventory in Adobe Commerce and Magento Open Source without having to use extensions or customization.

> **This is the `next-release` branch of the `jeanmarcos/inventory` fork.** It removes the
> special treatment of the Default Stock: stock id 1 behaves like any other stock, and the
> `cataloginventory_*` tables are no longer read or written by MSI. They are kept but frozen
> on the rows they held at the upgrade, so code reading them with raw SQL sees stale data and
> code writing them changes nothing — writes are counted and reported rather than applied.
> Read [`UPGRADE.md`](UPGRADE.md) before installing or upgrading, and
> [`DISTRIBUTION.md`](DISTRIBUTION.md) for what else this fork changes.

## Documentation

- Complete user documentation located on the project [Adobe Commerce DevDocs](https://developer.adobe.com/commerce/webapi/rest/inventory/) pages.
- Technical vision and designs described on the project [wiki](https://github.com/magento/inventory/wiki).
- Project [roadmap](https://github.com/magento/inventory/wiki/MSI-Roadmap) contains information about project phases and stories for each phase.
- How to start local development described in the [installation guide](https://github.com/magento/inventory/wiki/Metapackage-Installation-Guide).

## Community Engineering Slack

To connect with Magento Open Source team and the Community, join us on the [Magento Open Source Community Engineering Slack](https://magentocommeng.slack.com).
If you are interested in joining Slack, or a specific channel, use our [self signup](https://opensource.magento.com/slack) link.

MSI project slack channel: [#msi](https://magentocommeng.slack.com/archives/C5FU5E2HY)
