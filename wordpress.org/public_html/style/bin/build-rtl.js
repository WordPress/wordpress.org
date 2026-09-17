/**
 * Generates wp4-rtl.css from wp4.css.
 *
 * Run via `npm run build:css` or directly with
 * `node bin/build-rtl.js`. Ported from the former grunt-rtlcss task.
 */

const fs = require( 'fs' );
const path = require( 'path' );
const rtlcss = require( 'rtlcss' );

/**
 * RTLCSS options.
 *
 * Rewrites `.css` to `-rtl.css` in url() strings of at-rules (@import), so a
 * flipped stylesheet pulls in the flipped versions of its dependencies.
 */
const options = {
	clean: false,
	processUrls: { atrule: true, decl: false },
	stringMap: [
		{
			name: 'import-rtl-stylesheet',
			priority: 10,
			exclusive: true,
			search: [ '.css' ],
			replace: [ '-rtl.css' ],
			options: {
				scope: 'url',
				ignoreCase: false,
			},
		},
	],
};

/**
 * Dashicons arrow code points, keyed by the glyph to replace.
 *
 * arrow-left <-> arrow-right, arrow-left-alt <-> arrow-right-alt,
 * arrow-left-alt2 <-> arrow-right-alt2.
 */
const mirroredArrows = {
	f141: 'f139',
	f139: 'f141',
	f340: 'f344',
	f344: 'f340',
	f341: 'f345',
	f345: 'f341',
};

/**
 * RTLCSS plugins.
 *
 * Swaps the Dashicons left/right arrow glyphs, which RTLCSS cannot infer from
 * the CSS itself.
 */
const plugins = [
	{
		name: 'swap-dashicons-left-right-arrows',
		priority: 10,
		directives: {
			control: {},
			value: [],
		},
		processors: [
			{
				expr: /content/im,

				/**
				 * Swaps a Dashicons arrow code point for its mirrored counterpart.
				 *
				 * @param {string} prop  The declaration property.
				 * @param {string} value The declaration value.
				 * @return {Object} The declaration with the arrow glyph flipped.
				 */
				action( prop, value ) {
					// Match either quote style; the Grunt-era check only matched double quotes and never fired.
					const match = value.match( /^(['"])\\(f[0-9a-f]{3})\1$/i );
					if ( match && mirroredArrows[ match[ 2 ].toLowerCase() ] ) {
						value = match[ 1 ] + '\\' + mirroredArrows[ match[ 2 ].toLowerCase() ] + match[ 1 ];
					}
					return { prop, value };
				},
			},
		],
	},
];

const source = path.resolve( __dirname, '..', 'wp4.css' );
const destination = path.resolve( __dirname, '..', 'wp4-rtl.css' );

fs.writeFileSync( destination, rtlcss.process( fs.readFileSync( source, 'utf8' ), options, plugins ) );
