/**
 * ESLint configuration for the Plugin Directory theme.
 */

const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	{
		ignores: [ '*.js' ],
	},
	...defaultConfig,
	{
		rules: {
			// WordPress packages are provided at runtime as script dependencies.
			'import/no-unresolved': [ 'error', { ignore: [ '^@wordpress/' ] } ],
		},
	},
];
