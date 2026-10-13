import { test } from 'node:test';
import assert from 'node:assert/strict';
import { toChangelogPage } from './changelog.mjs';

const body = (page) => page.slice(page.indexOf('---', 3) + 4);

test('drops the file title and demotes every heading one level', () => {
	const page = toChangelogPage('# Changelog\n\nIntro\n\n# msi-core 1.1.0 — 2026-10-13\n\n### Entry\n');

	assert.equal(body(page), '\nIntro\n\n## msi-core 1.1.0 — 2026-10-13\n\n#### Entry\n');
});

test('leaves headings inside fenced code untouched', () => {
	const page = toChangelogPage('# Changelog\n\n```bash\n# not a heading\n```\n');

	assert.match(body(page), /```bash\n# not a heading\n```/);
});

test('points links to documents that moved at their site pages', () => {
	const page = toChangelogPage(
		'# Changelog\n\n[a](VERSIONING.md) [b](UPGRADE.md) [c](DISTRIBUTION.md) [d](InventoryStockVisualizer/README.md) [e](CHANGELOG.md)\n'
	);

	assert.match(
		body(page),
		/\[a\]\(\/msi-core\/reference\/versioning\/\) \[b\]\(\/msi-core\/start\/upgrade\/\) \[c\]\(\/msi-core\/reference\/upstream-differences\/\) \[d\]\(\/msi-core\/features\/stock-visualizer\/\) \[e\]\(\/msi-core\/project\/changelog\/\)/
	);
});

test('points other relative links at the file on GitHub', () => {
	const page = toChangelogPage('# Changelog\n\n[n](NOTICE) [x](https://example.com/a.md)\n');

	assert.match(
		body(page),
		/\[n\]\(https:\/\/github\.com\/jeanmarcos-dev\/msi-core\/blob\/main\/NOTICE\) \[x\]\(https:\/\/example\.com\/a\.md\)/
	);
});

test('starts with frontmatter that edits the root changelog', () => {
	const page = toChangelogPage('# Changelog\n');

	assert.match(page, /^---\ntitle: Changelog\n/);
	assert.match(page, /\neditUrl: https:\/\/github\.com\/jeanmarcos-dev\/msi-core\/edit\/main\/CHANGELOG\.md\n/);
});

test('leaves headings and links inside tilde fences untouched', () => {
	const page = toChangelogPage('# Changelog\n\n~~~md\n# literal\n[x](UPGRADE.md)\n~~~\n\n# After\n');

	assert.match(body(page), /~~~md\n# literal\n\[x\]\(UPGRADE\.md\)\n~~~\n\n## After\n/);
});

test('a backtick fence is not closed by a tilde line', () => {
	const page = toChangelogPage('# Changelog\n\n```\n~~~\n# literal\n```\n# After\n');

	assert.match(body(page), /```\n~~~\n# literal\n```\n## After\n/);
});

test('keeps the anchor of a link to a document that moved', () => {
	const page = toChangelogPage('# Changelog\n\n[x](UPGRADE.md#downgrading)\n');

	assert.match(body(page), /\[x\]\(\/msi-core\/start\/upgrade\/#downgrading\)/);
});
