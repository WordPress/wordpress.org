/**
 * cssnano configuration.
 *
 * Mirrors the default preset that @wordpress/scripts applies, but forces the
 * `@charset "utf-8"` rule to be emitted. The stylesheet embeds Dashicons glyphs
 * as literal (non-ASCII) characters in `content` values, and the charset rule
 * ensures they are decoded correctly regardless of how the file is served.
 */
module.exports = {
	preset: [
		'default',
		{
			discardComments: { removeAll: true },
			normalizeCharset: { add: true },
		},
	],
};
