module.exports = {
	plugins: {
		// This has to run before any other plugins, to concatenate all files into one.
		'postcss-import': {},

		// This must go before nesting plugins.
		'postcss-nesting': {},
		'postcss-custom-media': {},

		// This needs to come after any plugins that add "modern" CSS features.
		'postcss-preset-env': {},
		'cssnano': {},

		// This has to go after any plugins that output messages.
		'postcss-reporter': {},
	}
};
