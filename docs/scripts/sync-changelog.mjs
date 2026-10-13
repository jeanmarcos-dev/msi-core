import { readFile, writeFile } from 'node:fs/promises';
import { toChangelogPage } from './changelog.mjs';

const source = new URL('../../CHANGELOG.md', import.meta.url);
const target = new URL('../src/content/docs/project/changelog.md', import.meta.url);

await writeFile(target, toChangelogPage(await readFile(source, 'utf8')));
