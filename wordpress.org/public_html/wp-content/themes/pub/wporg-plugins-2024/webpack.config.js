/**
 * WordPress dependencies
 */
const config = require( '@wordpress/scripts/config/webpack.config' );

/**
 * External dependencies
 */
const path = require( 'path' );
const rtlcss = require( 'rtlcss' );
const RemoveEmptyScriptsPlugin = require( 'webpack-remove-empty-scripts' );

/*
 * Directional Dashicons arrow glyphs and their left/right counterparts, keyed
 * by codepoint. rtlcss does not mirror `content` values on its own, so without
 * this swap the navigation arrows point the wrong way in right-to-left locales.
 *
 * Ported from the theme's previous Grunt build.
 */
const ARROW_SWAPS = {
	f141: 'f139', // dashicons-arrow-left   <-> dashicons-arrow-right
	f340: 'f344', // dashicons-arrow-left-alt  <-> dashicons-arrow-right-alt
	f341: 'f345', // dashicons-arrow-left-alt2 <-> dashicons-arrow-right-alt2
	f139: 'f141',
	f344: 'f340',
	f345: 'f341',
};

/**
 * rtlcss plugin that swaps directional Dashicons arrows in `content` values.
 *
 * The value reaching this processor may be either an escaped codepoint
 * (`"\f341"`) or the literal glyph character, depending on how cssnano
 * minified it, so matching is done by codepoint and the original escaping is
 * preserved in the output.
 */
const swapDashiconsArrows = {
	name: 'swap-dashicons-left-right-arrows',
	priority: 10,
	directives: { control: {}, value: [] },
	processors: [
		{
			expr: /content/im,
			action( prop, value ) {
				const quoted = value.match( /^(['"])(.*)\1$/ );
				if ( ! quoted ) {
					return { prop, value };
				}

				const [ , quote, inner ] = quoted;
				const escaped = inner.match( /^\\([0-9a-f]{1,6})\s?$/i );
				const codePoint = escaped
					? parseInt( escaped[ 1 ], 16 )
					: inner.codePointAt( 0 );
				const swap = ARROW_SWAPS[ codePoint?.toString( 16 ) ];
				if ( ! swap ) {
					return { prop, value };
				}

				const replacement = escaped
					? '\\' + swap
					: String.fromCodePoint( parseInt( swap, 16 ) );
				return { prop, value: quote + replacement + quote };
			},
		},
	],
};

/**
 * Emits an RTL stylesheet for every CSS asset, applying the Dashicons arrow
 * swap. Replaces the stock RtlCssPlugin shipped with @wordpress/scripts, which
 * runs rtlcss without any plugins.
 */
class DashiconsRtlCssPlugin {
	apply( compiler ) {
		const { RawSource } = compiler.webpack.sources;

		compiler.hooks.compilation.tap(
			'DashiconsRtlCssPlugin',
			( compilation ) => {
				compilation.hooks.processAssets.tapAsync(
					{
						name: 'DashiconsRtlCssPlugin',
						stage: compilation.PROCESS_ASSETS_STAGE_OPTIMIZE,
					},
					( assets, callback ) => {
						for ( const chunk of compilation.chunks ) {
							for ( const filename of Array.from(
								chunk.files
							) ) {
								if ( path.extname( filename ) !== '.css' ) {
									continue;
								}

								const source =
									compilation.assets[ filename ].source();
								const rtl = rtlcss.process( source, {}, [
									swapDashiconsArrows,
								] );
								const rtlFilename = compilation.getPath(
									'[name]-rtl.css',
									{
										chunk,
										cssFileName: filename,
									}
								);

								compilation.assets[ rtlFilename ] =
									new RawSource( rtl );
								chunk.files.add( rtlFilename );
							}
						}

						callback();
					}
				);
			}
		);
	}
}

/*
 * Add the theme's standalone entries -- the global stylesheet and the legacy
 * screenshots bundle -- to the block entries that @wordpress/scripts discovers
 * automatically under `src/`.
 */
const getEntryPoints = config.entry;
config.entry = async () => {
	const entryPoints =
		'function' === typeof getEntryPoints
			? await getEntryPoints()
			: getEntryPoints;

	return {
		...entryPoints,
		style: path.resolve( __dirname, 'client/main.scss' ),
		theme: path.resolve( __dirname, 'client/theme.js' ),
	};
};

/*
 * Leave `url()` references untouched, matching the previous Grunt build. The
 * stylesheet only points at server-absolute paths (e.g. /wp-admin/images/), so
 * there is nothing for css-loader to bundle and resolving them only errors.
 *
 * The css-loader rules are walked recursively (they may be nested under
 * `oneOf`/`rules`), and a missing match throws rather than silently leaving
 * url() handling enabled, in case @wordpress/scripts reshapes its config.
 */
const isCssLoader = ( loader ) =>
	'string' === typeof loader && /[\\/]css-loader[\\/]/.test( loader );

let patchedCssLoaders = 0;
const disableCssUrlHandling = ( rules ) => {
	( rules || [] ).forEach( ( rule ) => {
		if ( ! rule || 'object' !== typeof rule ) {
			return;
		}

		disableCssUrlHandling( rule.oneOf );
		disableCssUrlHandling( rule.rules );

		( Array.isArray( rule.use ) ? rule.use : [] ).forEach( ( use ) => {
			if ( use && isCssLoader( use.loader ) ) {
				use.options = { ...use.options, url: false };
				patchedCssLoaders++;
			}
		} );
	} );
};
disableCssUrlHandling( config.module.rules );

if ( 0 === patchedCssLoaders ) {
	throw new Error(
		'webpack.config.js: found no css-loader rule to disable url() handling on; the @wordpress/scripts config layout has likely changed.'
	);
}

/*
 * Swap the stock RtlCssPlugin for the Dashicons-aware one, and drop the empty
 * JS file webpack would otherwise emit for the CSS-only `style` entry. A
 * missing stock plugin throws, so a future rename cannot leave us silently
 * generating RTL without the arrow swap.
 */
const pluginsWithoutStockRtl = config.plugins.filter(
	( plugin ) => 'RtlCssPlugin' !== plugin.constructor.name
);

if ( pluginsWithoutStockRtl.length === config.plugins.length ) {
	throw new Error(
		'webpack.config.js: the stock RtlCssPlugin was not found to replace; the @wordpress/scripts config has likely changed.'
	);
}

config.plugins = [
	...pluginsWithoutStockRtl,
	new RemoveEmptyScriptsPlugin(),
	new DashiconsRtlCssPlugin(),
];

module.exports = config;
