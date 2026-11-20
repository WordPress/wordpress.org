/**
 * Webpack config for the WordPress.org Documentation theme.
 *
 * Extends the `@wordpress/scripts` default config.
 */

/**
 * External dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

/**
 * Ends every emitted text file with a newline.
 *
 * The build output is committed, and minified files and copied `block.json`
 * files otherwise end without one.
 */
class FinalNewlinePlugin {
	/**
	 * Hooks into the compilation after all other asset processing.
	 *
	 * @param {Object} compiler Webpack compiler instance.
	 */
	apply( compiler ) {
		const { Compilation, sources } = compiler.webpack;

		compiler.hooks.thisCompilation.tap(
			'FinalNewlinePlugin',
			( compilation ) => {
				compilation.hooks.processAssets.tap(
					{
						name: 'FinalNewlinePlugin',
						stage: Compilation.PROCESS_ASSETS_STAGE_REPORT,
					},
					( assets ) => {
						for ( const name of Object.keys( assets ) ) {
							if ( ! /\.(css|js|json|php)$/.test( name ) ) {
								continue;
							}

							const content = assets[ name ].source().toString();
							if ( ! content.endsWith( '\n' ) ) {
								compilation.updateAsset(
									name,
									new sources.RawSource( content + '\n' )
								);
							}
						}
					}
				);
			}
		);
	}
}

module.exports = {
	...defaultConfig,
	plugins: [ ...defaultConfig.plugins, new FinalNewlinePlugin() ],
};
