#!/usr/bin/env node

import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, posix, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const pageDirectory = dirname( fileURLToPath( import.meta.url ) );
const rootDirectory = resolve( pageDirectory, '..' );
const outputFile = resolve( pageDirectory, 'index.html' );
const startMarker = '<!-- DOCS:START -->';
const endMarker = '<!-- DOCS:END -->';

const sources = [
	{ file: 'README.md', type: 'overview' },
	{ file: 'docs/content/wordpress-plugin.md', type: 'guide', title: 'WordPress plugin', id: 'wordpress-plugin' },
	{ file: 'docs/content/config-usage-example.md', type: 'guide', title: 'Configuration', id: 'configuration' },
];

const usedIds = new Map();
let activeSourceFile = 'README.md';

function escapeHtml( value ) {
	return value
		.replaceAll( '&', '&amp;' )
		.replaceAll( '<', '&lt;' )
		.replaceAll( '>', '&gt;' )
		.replaceAll( '"', '&quot;' );
}

function slugify( value ) {
	const base = value
		.toLowerCase()
		.replace( /<[^>]+>/g, '' )
		.replace( /[`*_()[\]]/g, '' )
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-|-$/g, '' ) || 'section';
	const count = usedIds.get( base ) || 0;
	usedIds.set( base, count + 1 );
	return count ? `${ base }-${ count + 1 }` : base;
}

function inline( value ) {
	let text = escapeHtml( value );
	const code = [];

	text = text.replace( /`([^`]+)`/g, ( match, content ) => {
		code.push( `<code>${ content }</code>` );
		return `%%CODE${ code.length - 1 }%%`;
	} );
	text = text.replace( /!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" loading="lazy">' );
	text = text.replace( /\[([^\]]+)\]\(([^)]+)\)/g, ( match, label, href ) => {
		let url = href;
		if ( href === 'docs/content/config-usage-example.md' ) url = '#configuration';
		if ( href === 'docs/content/wordpress-plugin.md' ) url = '#wordpress-plugin';
		if ( href === '../Container.php' || href === 'Container.php' ) url = 'https://github.com/doiftrue/litewire-di/blob/main/Container.php';
		if ( href.startsWith( '#' ) ) url = href.toLowerCase().replace( /[^#a-z0-9-]/g, '-' );
		if ( ! /^(#|https?:\/\/|\/\/)/.test( url ) ) {
			const sourceDirectory = posix.dirname( activeSourceFile );
			const repositoryPath = posix.normalize( posix.join( sourceDirectory, url ) );
			url = `https://github.com/doiftrue/litewire-di/blob/main/${ repositoryPath }`;
		}
		const external = /^(https?:)?\/\//.test( url );
		return `<a href="${ url }"${ external ? ' target="_blank" rel="noreferrer"' : '' }>${ label }</a>`;
	} );
	text = text.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' );
	text = text.replace( /(?<!\*)\*([^*]+)\*(?!\*)/g, '<em>$1</em>' );
	text = text.replace( /%%CODE(\d+)%%/g, ( match, index ) => code[ Number( index ) ] );

	return text;
}

function isTableSeparator( line ) {
	return /^\|?\s*:?-{3,}/.test( line ) && line.includes( '|' );
}

function tableCells( line ) {
	return line.replace( /^\||\|$/g, '' ).split( '|' ).map( ( cell ) => cell.trim() );
}

function renderMarkdown( markdown, headingOffset = 0 ) {
	const lines = markdown.replace( /\r/g, '' ).split( '\n' );
	const html = [];
	let paragraph = [];
	let listType = null;
	let inFence = false;
	let fenceLanguage = '';
	let fence = [];

	function flushParagraph() {
		if ( paragraph.length ) {
			html.push( `<p>${ inline( paragraph.join( ' ' ) ) }</p>` );
			paragraph = [];
		}
	}

	function closeList() {
		if ( listType ) {
			html.push( `</${ listType }>` );
			listType = null;
		}
	}

	for ( let index = 0; index < lines.length; index++ ) {
		const line = lines[ index ];

		if ( inFence ) {
			if ( /^```/.test( line ) ) {
				const language = escapeHtml( fenceLanguage || 'text' );
				html.push( `<div class="code-block"><div class="code-label"><span>${ language }</span><button class="copy-code" type="button">Copy</button></div><pre><code class="language-${ language }">${ escapeHtml( fence.join( '\n' ) ) }</code></pre></div>` );
				inFence = false;
				fence = [];
			} else {
				fence.push( line );
			}
			continue;
		}

		const fenceMatch = line.match( /^```\s*([\w-]*)/ );
		if ( fenceMatch ) {
			flushParagraph(); closeList();
			inFence = true;
			fenceLanguage = fenceMatch[ 1 ];
			continue;
		}

		if ( line && index + 1 < lines.length && /^(=+|-+)\s*$/.test( lines[ index + 1 ] ) ) {
			flushParagraph(); closeList();
			const baseLevel = lines[ index + 1 ].startsWith( '=' ) ? 1 : 2;
			const level = Math.min( 6, baseLevel + headingOffset );
			const id = slugify( line );
			html.push( `<h${ level } id="${ id }">${ inline( line ) }<a class="heading-anchor" href="#${ id }" aria-label="Link to this section">#</a></h${ level }>` );
			index++;
			continue;
		}

		const heading = line.match( /^(#{1,6})\s+(.+)$/ );
		if ( heading ) {
			flushParagraph(); closeList();
			const level = Math.min( 6, heading[ 1 ].length + headingOffset );
			const id = slugify( heading[ 2 ] );
			html.push( `<h${ level } id="${ id }">${ inline( heading[ 2 ] ) }<a class="heading-anchor" href="#${ id }" aria-label="Link to this section">#</a></h${ level }>` );
			continue;
		}

		if ( line.startsWith( '>' ) ) {
			flushParagraph(); closeList();
			const quote = line.replace( /^>\s?/, '' );
			const alert = quote.match( /^\[!(NOTE|IMPORTANT|WARNING|INFO)\]\s*(.*)$/i );
			if ( alert ) {
				const body = [];
				if ( alert[ 2 ] ) body.push( alert[ 2 ] );
				while ( lines[ index + 1 ]?.startsWith( '>' ) ) body.push( lines[ ++index ].replace( /^>\s?/, '' ) );
				html.push( `<aside class="callout ${ alert[ 1 ].toLowerCase() }"><strong>${ alert[ 1 ][ 0 ] + alert[ 1 ].slice( 1 ).toLowerCase() }</strong><p>${ inline( body.join( ' ' ) ) }</p></aside>` );
			} else {
				html.push( `<blockquote><p>${ inline( quote ) }</p></blockquote>` );
			}
			continue;
		}

		if ( line.includes( '|' ) && isTableSeparator( lines[ index + 1 ] || '' ) ) {
			flushParagraph(); closeList();
			const headers = tableCells( line );
			index++;
			const rows = [];
			while ( lines[ index + 1 ]?.includes( '|' ) && lines[ index + 1 ].trim() ) rows.push( tableCells( lines[ ++index ] ) );
			html.push( `<div class="table-wrap"><table><thead><tr>${ headers.map( ( cell ) => `<th>${ inline( cell ) }</th>` ).join( '' ) }</tr></thead><tbody>${ rows.map( ( row ) => `<tr>${ row.map( ( cell ) => `<td>${ inline( cell ) }</td>` ).join( '' ) }</tr>` ).join( '' ) }</tbody></table></div>` );
			continue;
		}

		const list = line.match( /^\s*([-*]|\d+\.)\s+(.+)$/ );
		if ( list ) {
			flushParagraph();
			const type = /\d/.test( list[ 1 ] ) ? 'ol' : 'ul';
			if ( listType !== type ) { closeList(); listType = type; html.push( `<${ type }>` ); }
			html.push( `<li>${ inline( list[ 2 ] ) }</li>` );
			continue;
		}

		if ( ! line.trim() ) {
			flushParagraph(); closeList();
			continue;
		}

		if ( /^<[^>]+>/.test( line ) ) {
			flushParagraph(); closeList(); html.push( line );
			continue;
		}

		paragraph.push( line.trim() );
	}

	flushParagraph(); closeList();
	return html.join( '\n' );
}

function renderSource( source ) {
	activeSourceFile = source.file;
	let markdown = readFileSync( resolve( rootDirectory, source.file ), 'utf8' );
	markdown = markdown.replace( /[ \t]+$/gm, '' );

	if ( source.type === 'overview' ) {
		markdown = markdown.replace( /^(?:!\[[^\n]+\n)+/, '' );
		markdown = markdown.replace( /^LiteWire DI Container\n=+\n/, '' );
		markdown = markdown.replace( /\nTable of contents\n-+\n[\s\S]*?(?=\nDesign goals\n-+)/, '\n' );
		return `<section class="doc-source doc-overview" data-doc-source="${ source.file }">${ renderMarkdown( markdown, 1 ) }</section>`;
	}

	markdown = markdown.replace( /^#\s+[^\n]+\n/, '' );
	return `<section class="doc-source doc-guide" data-doc-source="${ source.file }"><header class="guide-header"><p class="section-label">Extended guide</p><h2 id="${ source.id }">${ source.title }<a class="heading-anchor" href="#${ source.id }" aria-label="Link to this guide">#</a></h2><a class="source-link" href="https://github.com/doiftrue/litewire-di/blob/main/${ source.file }" target="_blank" rel="noreferrer">View Markdown source ↗</a></header>${ renderMarkdown( markdown, 1 ) }</section>`;
}

const generated = sources.map( renderSource ).join( '\n' );
const document = readFileSync( outputFile, 'utf8' );
const start = document.indexOf( startMarker );
const end = document.indexOf( endMarker );

if ( start === -1 || end === -1 || end < start ) {
	throw new Error( 'Documentation markers are missing from page/index.html.' );
}

const output = `${ document.slice( 0, start + startMarker.length ) }\n${ generated }\n\t\t\t\t${ document.slice( end ) }`;
writeFileSync( outputFile, output );
console.log( `Generated documentation from ${ sources.length } Markdown files.` );
