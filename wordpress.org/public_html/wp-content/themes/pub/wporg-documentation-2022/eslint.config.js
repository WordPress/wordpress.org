/**
 * ESLint config for the WordPress.org Documentation theme.
 *
 * Extends the `@wordpress/scripts` default config.
 */

/**
 * External dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...defaultConfig,
	{
		rules: {
			/*
			 * WordPress packages are script dependencies provided at runtime,
			 * not installed via npm.
			 */
			'import/no-unresolved': [ 'error', { ignore: [ '^@wordpress/' ] } ],
		},
	},
];
