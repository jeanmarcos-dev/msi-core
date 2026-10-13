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
		const [path, anchor] = target.split('#');
		const fragment = anchor === undefined ? '' : `#${anchor}`;
		return MOVED_DOCUMENTS[path]
			? `](${MOVED_DOCUMENTS[path]}${fragment})`
			: `](${REPOSITORY}/blob/main/${target})`;
	});
}

export function toChangelogPage(markdown) {
	const lines = markdown.split('\n');
	const firstHeading = lines.findIndex((line) => line.startsWith('# '));
	let fence = null;

	const body = lines
		.filter((_, index) => index !== firstHeading)
		.map((line) => {
			const marker = line.match(/^(`{3,}|~{3,})/)?.[1];
			if (fence === null && marker) {
				fence = marker;
				return line;
			}
			if (fence !== null) {
				if (marker && marker[0] === fence[0] && marker.length >= fence.length && line.trim() === marker) {
					fence = null;
				}
				return line;
			}
			return rewriteLinks(/^#{1,5} /.test(line) ? `#${line}` : line);
		});

	return FRONTMATTER + body.join('\n');
}
