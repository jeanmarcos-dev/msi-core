const REPOSITORY = 'https://github.com/jeanmarcos-dev/msi-core';

const MOVED_DOCUMENTS = {
	'CHANGELOG.md': '/msi-core/project/changelog/',
	'DISTRIBUTION.md': '/msi-core/reference/upstream-differences/',
	'InventoryStockVisualizer/README.md': '/msi-core/features/stock-visualizer/',
	'UPGRADE.md': '/msi-core/start/upgrade/',
	'VERSIONING.md': '/msi-core/reference/versioning/',
};

const FRONTMATTER = [
	'---',
	'title: Changelog',
	'description: Every change to msi-core, grouped by release, with the version that carries it.',
	`editUrl: ${REPOSITORY}/edit/main/CHANGELOG.md`,
	'---',
	'',
].join('\n');

function rewriteLinks(line) {
	return line.replace(/\]\(([^)\s]+)\)/g, (match, target) => {
		if (/^[a-z]+:|^#|^\//i.test(target)) {
			return match;
		}
		return `](${MOVED_DOCUMENTS[target] ?? `${REPOSITORY}/blob/main/${target}`})`;
	});
}

export function toChangelogPage(markdown) {
	const lines = markdown.split('\n');
	const firstHeading = lines.findIndex((line) => line.startsWith('# '));
	let inFence = false;

	const body = lines
		.filter((_, index) => index !== firstHeading)
		.map((line) => {
			if (line.startsWith('```')) {
				inFence = !inFence;
				return line;
			}
			if (inFence) {
				return line;
			}
			return rewriteLinks(/^#{1,5} /.test(line) ? `#${line}` : line);
		});

	return FRONTMATTER + body.join('\n');
}
