# Security policy

## Supported versions

Security fixes ship for the latest minor version of `jeanmarcos/msi-core` only, as a patch release.

| Version | Supported |
|---|---|
| 1.1.x | yes |
| < 1.1 | no |

[`jeanmarcos/inventory`](https://github.com/jeanmarcos-dev/inventory) is no longer maintained and
gets no security fixes. [Upgrading](https://jeanmarcos-dev.github.io/msi-core/start/upgrade/)
explains how to move to msi-core.

## Reporting a vulnerability

Do not report a vulnerability in a public issue, discussion or pull request. Report it privately
through [GitHub's private vulnerability reporting](https://github.com/jeanmarcos-dev/msi-core/security/advisories/new),
and include:

- the msi-core version, the Magento Open Source or Mage-OS version, and the PHP version;
- the affected module, endpoint, admin page or CLI command;
- the steps to reproduce it, or a proof of concept, and the impact you expect.

## What happens next

msi-core has a single maintainer, so reports are handled on a best-effort basis, without a fixed
response time. A confirmed vulnerability is fixed in a patch release of the supported version and
disclosed in a [GitHub Security Advisory](https://github.com/jeanmarcos-dev/msi-core/security/advisories)
once the release is out. The reporter is credited unless they ask not to be.

## Code inherited from Magento

msi-core is derived from [`magento/inventory`](https://github.com/magento/inventory). If the
vulnerability is also present in Adobe's package, report it to Adobe too, through
[its HackerOne program](https://hackerone.com/adobe): a fix in msi-core does not reach stores that
run Adobe's package.
