/**
 * Prettier configuration.
 *
 * The default @wordpress/prettier-config, with a wider line length.
 */

const wpConfig = require( '@wordpress/prettier-config' );

module.exports = {
	...wpConfig,
	printWidth: 120,
};
