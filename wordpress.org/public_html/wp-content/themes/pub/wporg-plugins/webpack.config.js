/* global module:false */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	cache: true,
	entry: './client/build.js',
	output: {
		path: __dirname + '/js/build',
		filename: 'theme.js'
	}
};
