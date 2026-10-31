---
title: Contributing
description: Where to ask questions, report issues and vulnerabilities, and send pull requests, and what a change needs to be accepted.
---

Questions about installing, configuring or using msi-core go to
[Discussions](https://github.com/jeanmarcos-dev/msi-core/discussions/categories/q-a).

Issues and pull requests are welcome at
[jeanmarcos-dev/msi-core](https://github.com/jeanmarcos-dev/msi-core). Say which Magento or Mage-OS
version you are on. The drop-in `jeanmarcos/inventory` package is no longer maintained, so a problem
reported against it is fixed in msi-core only.

## Reporting a vulnerability

Report it privately, never in a public issue or discussion. The
[security policy](https://github.com/jeanmarcos-dev/msi-core/security/policy) says how, and which
versions receive security fixes.

## What a change needs

- **Commits** follow [Conventional Commits](https://www.conventionalcommits.org/) with a single-line
  subject; a git hook enforces it.
- **Changes to reservations, salability or the indexers** come with the stress suite result.
- **A change users can notice** gets an entry in `CHANGELOG.md` and, when it adds or changes behaviour,
  an update to the page of this site that documents it. The site lives in `docs/` of the same
  repository.

## License of your contribution

msi-core has no contributor license agreement. Under
[section D.6 of GitHub's terms of service](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service#6-contributions-under-repository-license),
what you contribute is licensed under the license of the files it changes, OSL-3.0 and AFL-3.0
([License and attribution](/msi-core/project/license/)). You keep the copyright of your work.

## Building this site

```bash
cd docs
npm ci
npm run dev       # live preview on http://localhost:4321/msi-core/
npm run build     # fails on a broken internal link
npm test          # the changelog page generator
```

The changelog page is generated from `CHANGELOG.md` on every build; edit the root file, not the page.

## Code of conduct

Participation is governed by the
[code of conduct](https://github.com/jeanmarcos-dev/msi-core/blob/main/CODE_OF_CONDUCT.md).
