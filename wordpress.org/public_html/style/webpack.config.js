/**
 * Webpack configuration.
 *
 * Extends the @wordpress/scripts default configuration to build
 * js/navigation.js into js/navigation.min.js in place, instead of the
 * default src/ -> build/ layout. Plugins that only make sense for that
 * layout (cleaning the output directory, copying block metadata, and
 * generating *.asset.php files) are removed.
 */

const { resolve } = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		'navigation.min': resolve( __dirname, 'js', 'navigation.js' ),
	},
	output: {
		path: resolve( __dirname, 'js' ),
		filename: '[name].js',
	},
	plugins: defaultConfig.plugins.filter(
		( plugin ) =>
			! [ 'CleanWebpackPlugin', 'CopyPlugin', 'DependencyExtractionWebpackPlugin' ].includes(
				plugin.constructor.name
			)
	),
};
