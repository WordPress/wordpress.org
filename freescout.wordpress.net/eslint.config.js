/**
 * ESLint configuration for the FreeScout modules' JavaScript.
 */

const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...defaultConfig,
	{
		// FreeScout loads these as plain scripts, next to its own jQuery.
		files: [ 'Modules/*/Public/js/**/*.js' ],
		languageOptions: {
			sourceType: 'script',
			globals: {
				jQuery: 'readonly',
			},
		},
	},
];
