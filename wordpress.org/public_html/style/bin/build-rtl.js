/**
 * Generates wp4-rtl.css from wp4.css.
 *
 * Run via `npm run build:css` (after autoprefixing) or directly with
 * `node bin/build-rtl.js`. Ported from the former grunt-rtlcss task; the
 * options and plugins below are unchanged.
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
					if ( value === '"\\f141"' ) {
						// dashicons-arrow-left.
						value = '"\\f139"';
					} else if ( value === '"\\f340"' ) {
						// dashicons-arrow-left-alt.
						value = '"\\f344"';
					} else if ( value === '"\\f341"' ) {
						// dashicons-arrow-left-alt2.
						value = '"\\f345"';
					} else if ( value === '"\\f139"' ) {
						// dashicons-arrow-right.
						value = '"\\f141"';
					} else if ( value === '"\\f344"' ) {
						// dashicons-arrow-right-alt.
						value = '"\\f340"';
					} else if ( value === '"\\f345"' ) {
						// dashicons-arrow-right-alt2.
						value = '"\\f341"';
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
