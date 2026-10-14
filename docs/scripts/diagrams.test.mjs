import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const docsRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const featuresDir = join(docsRoot, 'src/content/docs/features');
const featurePages = readdirSync(featuresDir).filter((name) => /\.mdx?$/.test(name));

const diagramImports = (source) =>
	[...source.matchAll(/^import\s+(\w+)\s+from\s+'([^']*components\/diagrams\/[^']+\.astro)';$/gm)].map(
		([, name, path]) => ({ name, path }),
	);

test('every feature page renders a diagram', () => {
	assert.ok(featurePages.length > 0);
	for (const page of featurePages) {
		const source = readFileSync(join(featuresDir, page), 'utf8');
		const imports = diagramImports(source);
		assert.ok(imports.length > 0, `${page} imports no diagram`);
		for (const { name } of imports) {
			assert.match(source, new RegExp(`<${name}\\s*/>`), `${page} imports ${name} without rendering it`);
		}
	}
});

test('every diagram is an accessible figure with a caption', () => {
	for (const page of featurePages) {
		const source = readFileSync(join(featuresDir, page), 'utf8');
		for (const { path } of diagramImports(source)) {
			const file = resolve(featuresDir, path);
			assert.ok(existsSync(file), `${page} imports a missing diagram: ${path}`);
			const diagram = readFileSync(file, 'utf8');
			assert.match(diagram, /<Diagram\s[^>]*caption=/, `${path} has no caption`);
			assert.match(diagram, /<title[^>]*>[^<]+<\/title>/, `${path} has no SVG title`);
		}
	}
});

test('diagram marker ids are unique across diagrams', () => {
	const dir = join(docsRoot, 'src/components/diagrams');
	const ids = readdirSync(dir)
		.filter((name) => name.endsWith('.astro'))
		.flatMap((name) => [...readFileSync(join(dir, name), 'utf8').matchAll(/\sid="([^"]+)"/g)].map(([, id]) => id));
	assert.equal(new Set(ids).size, ids.length, `duplicate ids: ${ids.filter((id, i) => ids.indexOf(id) !== i)}`);
});
