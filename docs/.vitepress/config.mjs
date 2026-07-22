import { defineConfig } from 'vitepress';

const guideSidebar = [
	{ text: 'Getting started', link: '/guide/getting-started' },
	{ text: 'Using the container', link: '/guide/using-the-container' },
	{ text: 'Configuring services', link: '/guide/configuration-and-factories' },
];

const resourcesSidebar = [
	{ text: 'Container comparison', link: '/reference/comparison' },
	{ text: 'Limitations', link: '/reference/limitations' },
	{ text: 'Benchmarks', link: '/reference/benchmarks' },
	{ text: 'FAQ and support', link: '/reference/faq' },
];

const documentationSidebar = [
	{ text: 'Container guide', link: '/guide/full-documentation' },
	{ text: 'Configuration cookbook', link: '/guide/full-configuration' },
	{ text: 'WordPress example', link: '/guide/full-wordpress' },
];

export default defineConfig( {
	lang: 'en-US',
	title: 'LiteWire DI',
	description: 'A tiny single-file autowiring DI container for PHP and WordPress.',
	base: '/litewire-di/',
	cleanUrls: true,
	head: [
		[ 'link', { rel: 'icon', href: '/litewire-di/logo.svg', type: 'image/svg+xml' } ],
	],
	themeConfig: {
		logo: '/logo.svg',
		outline: {
			level: [ 2, 3 ],
		},
		nav: [
			{ text: 'Guide', link: '/guide/getting-started' },
			{ text: 'Documentation', link: '/guide/full-documentation' },
			{ text: 'Benchmarks', link: '/reference/benchmarks' },
			{ text: 'Packagist', link: 'https://packagist.org/packages/doiftrue/litewire-di' },
		],
		sidebar: [
			{ text: 'Guides', items: guideSidebar },
			{ text: 'Documentation', items: documentationSidebar },
			{ text: 'More', items: resourcesSidebar },
		],
		socialLinks: [
			{ icon: 'github', link: 'https://github.com/doiftrue/litewire-di' },
		],
		search: {
			provider: 'local',
		},
		footer: {
			message: 'Released under the MIT License.',
			copyright: 'Copyright © Timur Kamaev',
		},
	},
} );
