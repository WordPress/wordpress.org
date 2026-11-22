/**
 * WordPress dependencies
 */
const config = require( '@wordpress/scripts/config/webpack.config' );

/**
 * External dependencies
 */
const path = require( 'path' );
const RemoveEmptyScriptsPlugin = require( 'webpack-remove-empty-scripts' );

module.exports = {
	...config,
	entry: {
		style: path.resolve( __dirname, 'sass/main.scss' ),
	},
	// Drop the empty JS file webpack emits for the CSS-only entry.
	plugins: [ ...config.plugins, new RemoveEmptyScriptsPlugin() ],
};
