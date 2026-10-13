---
title: Contributing
description: Where to report issues and send pull requests, and what a change needs to be accepted.
---

Issues and pull requests are welcome at
[jeanmarcos-dev/msi-core](https://github.com/jeanmarcos-dev/msi-core), for the drop-in
`jeanmarcos/inventory` lines too: that repository is a fork and has its issue tracker disabled. Say
which package and which Magento version you are on.

## What a change needs

- **Commits** follow [Conventional Commits](https://www.conventionalcommits.org/) with a single-line
  subject; a git hook enforces it.
- **Changes to reservations, salability or the indexers** come with the stress suite result.
- **A change users can notice** gets an entry in `CHANGELOG.md` and, when it adds or changes behaviour,
  an update to the page of this site that documents it. The site lives in `docs/` of the same
  repository.

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
