/**
 * cssnano configuration.
 *
 * Mirrors the default preset that @wordpress/scripts applies, with two changes:
 * the `@charset "utf-8"` rule is forced, because cssnano turns Dashicons escapes
 * in `content` values into literal glyphs; and `!rtl:` directive comments are
 * kept, because minification runs before rtlcss generates style-rtl.css. The
 * `!` is what gets them through Sass's compressed output in the first place.
 */
module.exports = {
	preset: [
		'default',
		{
			discardComments: {
				remove: ( comment ) => ! /^!\s*rtl:/.test( comment ),
			},
			normalizeCharset: { add: true },
		},
	],
};
