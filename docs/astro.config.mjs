import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import starlightLinksValidator from 'starlight-links-validator';

export default defineConfig({
	site: 'https://jeanmarcos-dev.github.io',
	base: '/msi-core',
	integrations: [
		starlight({
			title: 'msi-core',
			description: 'Multi-Source Inventory as the only inventory system in Magento Open Source and Mage-OS.',
			logo: { src: './src/assets/logo.svg', alt: 'msi-core' },
			favicon: '/favicon.svg',
			social: [{ icon: 'github', label: 'GitHub', href: 'https://github.com/jeanmarcos-dev/msi-core' }],
			editLink: { baseUrl: 'https://github.com/jeanmarcos-dev/msi-core/edit/main/docs/' },
			customCss: ['./src/styles/theme.css', './src/styles/diagrams.css'],
			lastUpdated: true,
			plugins: [starlightLinksValidator()],
			sidebar: [
				{
					label: 'Start here',
					items: ['start/why', 'start/install', 'start/upgrade', 'start/production'],
				},
				{
					label: 'Features',
					items: [
						'features/default-stock',
						'features/source-reservations',
						'features/salable-by-source',
						'features/adjustment-history',
						'features/stock-visualizer',
						'features/legacy-write-detection',
					],
				},
				{
					label: 'Reference',
					items: [
						'reference/configuration',
						'reference/cli',
						'reference/rest-api',
						'reference/versioning',
						'reference/upstream-differences',
					],
				},
				{
					label: 'Project',
					items: ['project/changelog', 'project/contributing', 'project/license'],
				},
			],
		}),
	],
});
