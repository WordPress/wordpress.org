<?php
/**
 * Plugin name: GlotPress: Custom Translation Warnings
 * Description: Provides custom translation warnings like mismatching URLs for translate.wordpress.org.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

class WPorg_GP_Custom_Translation_Warnings {

	/**
	 * Mapping for allowed domain changes.
	 *
	 * @var array
	 */
	private $allowed_domain_changes = array(
		// Allow links to wordpress.org to be changed to a subdomain.
		'wordpress.org' => '[^.]+\.wordpress\.org',
		// Allow links to wordpress.com to be changed to a subdomain.
		'wordpress.com' => '[^.]+\.wordpress\.com',
		// Allow links to gravatar.org to be changed to a subdomain.
		'en.gravatar.com' => '[^.]+\.gravatar\.com',
		// Allow links to wikipedia.org to be changed to a subdomain.
		'en.wikipedia.org' => '[^.]+\.wikipedia\.org',
	);

	/**
	 * Adds a warning for changing plain-text URLs.
	 *
	 * This allows for the scheme to change, and for WordPress.org URL's to change to a subdomain.
	 *
	 * @param string $original    The original string.
	 * @param string $translation The translated string.
	 */
	public function warning_mismatching_urls( $original, $translation ) {
		// Any http/https/schemeless URLs which are not encased in quotation marks
		// nor contain whitespace and end with a valid URL ending char.
		$urls_regex = '@(?<![\'"])((https?://|(?<![:\w])//)[^\s<]+[a-z0-9\-_&=#/])(?![\'"])@i';

		preg_match_all( $urls_regex, $original, $original_urls );
		$original_urls = array_unique( $original_urls[0] );

		preg_match_all( $urls_regex, $translation, $translation_urls );
		$translation_urls = array_unique( $translation_urls[0] );

		$missing_urls = array_diff( $original_urls, $translation_urls );
		$added_urls = array_diff( $translation_urls, $original_urls );
		if ( ! $missing_urls && ! $added_urls ) {
			return true;
		}

		// Check to see if only the scheme (https <=> http) or a trailing slash was changed, discard if so.
		foreach ( $missing_urls as $key => $missing_url ) {
			$scheme               = parse_url( $missing_url, PHP_URL_SCHEME );
			$alternate_scheme     = ( 'http' == $scheme ? 'https' : 'http' );
			$alternate_scheme_url = preg_replace( "@^$scheme(?=:)@", $alternate_scheme, $missing_url );

			$alt_urls = [
				// Scheme changes
				$alternate_scheme_url,

				// Slashed/unslashed changes.
				( '/' === substr( $missing_url, -1 )          ? rtrim( $missing_url, '/' )          : "{$missing_url}/" ),

				// Scheme & Slash changes.
				( '/' === substr( $alternate_scheme_url, -1 ) ? rtrim( $alternate_scheme_url, '/' ) : "{$alternate_scheme_url}/" ),
			];

			foreach ( $alt_urls as $alt_url ) {
				if ( false !== ( $alternate_index = array_search( $alt_url, $added_urls ) ) ) {
					unset( $missing_urls[ $key ], $added_urls[ $alternate_index ] );
				}
			}
		}

		// Check if just the domain was changed, and if so, if it's to a whitelisted domain
		foreach ( $missing_urls as $key => $missing_url ) {
			$host = parse_url( $missing_url, PHP_URL_HOST );
			if ( ! isset( $this->allowed_domain_changes[ $host ] ) ) {
				continue;
			}
			$allowed_host_regex = $this->allowed_domain_changes[ $host ];

			list( , $missing_url_path ) = explode( $host, $missing_url, 2 );

			$alternate_host_regex = '!^https?://' . $allowed_host_regex . preg_quote( $missing_url_path, '!' ) . '$!i';
			foreach ( $added_urls as $added_index => $added_url ) {
				if ( preg_match( $alternate_host_regex, $added_url, $match ) ) {
					unset( $missing_urls[ $key ], $added_urls[ $added_index ] );
				}
			}

		}

		if ( ! $missing_urls && ! $added_urls ) {
			return true;
		}

		// Error.
		$error = '';
		if ( $missing_urls ) {
			$error .= "The translation appears to be missing the following URLs: " . implode( ', ', $missing_urls ) . "\n";
		}
		if ( $added_urls ) {
			$error .= "The translation contains the following unexpected URLs: " . implode( ', ', $added_urls );
		}

		return trim( $error );
	}

	/**
	 * Replaces the GlotPress tags warning to allow some URL changes.
	 * 
	 * Differences from GlotPress:
	 *  - URLs (href + src) are run through `self::warning_mismatching_urls()`
	 *    - The domain may change for some safe domains
	 *    - The protocol may change between https & http
	 *    - The URL may include/remove a trailing slash
	 *  - The value of translatable/url attributes is excluded from the error message if it's not related to the issue at hand.
	 *  - Tags are sorted, <em>One</em> <strong>Two</strong> can be translated as <strong>foo</strong> <em>bar</em> without generating warning.
	 *  - East asian languages can remove emphasis/italic tags.
	 *  - TODO: Tags are not validated to be nested correctly. GlotPress handles this by validating the ordering of the tags remained the same.
	 *
	 * @param string    $original    The source string.
	 * @param string    $translation The translation.
	 * @param GP_Locale $locale      The locale of the translation.
	 * @return string|true True if check is OK, otherwise warning message.
	 */
	public function warning_tags( $original, $translation, $locale ) {
		$tag_pattern       = '(<[^>]*>)';
		$tag_re            = "/$tag_pattern/Us";
		$original_parts    = [];
		$translation_parts = [];

		if ( preg_match_all( $tag_re, $original, $m ) ) {
			$original_parts = $m[1];
		}
		if ( preg_match_all( $tag_re, $translation, $m ) ) {
			$translation_parts = $m[1];
		}

		// Allow certain laguages to exclude certain tags.
		if ( count( $original_parts ) > count( $translation_parts ) ) {

			$languages_without_italics = [
				'ja', 'ko', 'zh', 'zh-hk', 'zh-cn', 'zh-sg', 'zh-tw'
			];

			// Remove Italic requirements.
			if ( in_array( $locale->slug, $languages_without_italics, true ) ) {
				$original_parts = array_diff( $original_parts, [ '<em>', '</em>', '<i>', '</i>' ] );
			}
		}

		if ( count( $original_parts ) > count( $translation_parts ) ) {
			return 'Missing tags from translation. Expected: ' . implode( ' ', array_diff( $original_parts, $translation_parts ) );
		}
		if ( count( $original_parts ) < count( $translation_parts ) ) {
			return 'Too many tags in translation. Found: ' . implode( ' ', array_diff( $translation_parts, $original_parts ) );
		}

		// TODO: Validate nesting of HTML is same.
		// GlotPress handled this by requiring the HTML be in the same order.

		// Sort the tags, from this point out as long as all the tags are present is okay.
		rsort( $original_parts );
		rsort( $translation_parts );

		$changeable_attributes = array(
			// We allow certain attributes to be different in translations.
			'title',
			'aria-label',
			// src and href will be checked separately.
			'src',
			'href',
		);

		$attribute_regex       = '/(\s*(?P<attr>%s))=([\'"])(?P<value>.+)\\3(\s*)/i';
		$attribute_replace     = '$1=$3...$3$5';
		$changeable_attr_regex = sprintf( $attribute_regex, implode( '|', $changeable_attributes ) );
		$link_attr_regex       = sprintf( $attribute_regex, 'href|src' );

		// Items are sorted, so if all is well, will match up.
		$parts_tags = array_combine( $original_parts, $translation_parts );

		$warnings = [];
		foreach ( $parts_tags as $original_tag => $translation_tag ) {
			if ( $original_tag === $translation_tag ) {
				continue;
			}

			// Remove any attributes that can be expected to differ.
			$original_tag    = preg_replace( $changeable_attr_regex, $attribute_replace, $original_tag );
			$translation_tag = preg_replace( $changeable_attr_regex, $attribute_replace, $translation_tag );

			if ( $original_tag !== $translation_tag ) {
				$warnings[] = "Expected $original_tag, got $translation_tag.";
			}
		}

		// Now check that the URLs mentioned within href & src tags match.
		$original_links    = '';
		$translation_links = '';

		if ( preg_match_all( $link_attr_regex, implode( ' ', $original_parts ), $m ) ) {
			$original_links = implode( "\n", $m['value'] );
		}
		if ( preg_match_all( $link_attr_regex, implode( ' ', $translation_parts ), $m ) ) {
			$translation_links = implode( "\n", $m['value'] );
		}

		// Validate the URLs if present.
		if ( $original_links || $translation_links ) {
			$url_warnings = $this->warning_mismatching_urls( $original_links, $translation_links );

			if ( true !== $url_warnings ) {
				$warnings[] = $url_warnings;
			}
		}

		if ( empty( $warnings ) ) {
			return true;
		}

		return implode( "\n", $warnings );
   }

	/**
	 * Adds a warning for changing placeholders.
	 *
	 * This only supports placeholders in the format of '###[A-Za-z_-]+###'.
	 *
	 * @param string $original    The original string.
	 * @param string $translation The translated string.
	 */
	public function warning_mismatching_placeholders( $original, $translation ) {
		$placeholder_regex = '@(###[A-Za-z_-]+###)@';

		preg_match_all( $placeholder_regex, $original, $original_placeholders );
		$original_placeholders = array_unique( $original_placeholders[0] );

		preg_match_all( $placeholder_regex, $translation, $translation_placeholders );
		$translation_placeholders = array_unique( $translation_placeholders[0] );

		$missing_placeholders = array_diff( $original_placeholders, $translation_placeholders );
		$added_placeholders = array_diff( $translation_placeholders, $original_placeholders );
		if ( ! $missing_placeholders && ! $added_placeholders ) {
			return true;
		}

		// Error.
		$error = '';
		if ( $missing_placeholders ) {
			$error .= "The translation appears to be missing the following placeholders: " . implode( ', ', $missing_placeholders ) . "\n";
		}
		if ( $added_placeholders ) {
			$error .= "The translation contains the following unexpected placeholders: " . implode( ', ', $added_placeholders );
		}

		return trim( $error );
	}

	/**
	 * Adds a warning for adding unexpected percent signs in a sprintf-like string.
	 * 
	 * This is to catch translations for originals like this:
	 *  - Original: `<a href="%s">100 percent</a>`
	 *  - Submitted translation: `<a href="%s">100%</a>`
	 *  - Proper translation: `<a href="%s">100%%</a>`
	 *
	 * @param string $original    The original string.
	 * @param string $translation The translated string.
	 */
	public function warning_unexpected_sprintf_token( $original, $translation ) {
		$unexpected_tokens = [];
		$is_sprintf        = preg_match( '!%((\d+\$(?:\d+)?)?[bcdefgosuxl])\b!i', $original );

		// Find any percents that are not valid or escaped.
		if ( $is_sprintf ) {
			// Negative/Positive lookahead not used to allow the warning to include the context around the % sign.
			preg_match_all( '/(?P<context>[^\s%]*)%((\d+\$(?:\d+)?)?(?P<char>.))/i', $translation, $m );

			foreach ( $m['char'] as $i => $char ) {
				// % is included for escaped %%.
				if ( false === strpos( 'bcdefgosux%l.', $char ) ) {
					$unexpected_tokens[] = $m[0][ $i ];
				}
			}
		}

		if ( $unexpected_tokens ) {
			return "The translation contains the following unexpected placeholders: " . implode( ', ', $unexpected_tokens );
		}

		return true; // All is okay.
	}

	/**
	 * Registers all methods starting with warning_ with GlotPress.
	 */
	public function __construct() {
		$warnings = array_filter( get_class_methods( get_class( $this ) ), function( $key ) {
			return gp_startswith( $key, 'warning_' );
		} );

		foreach ( $warnings as $warning ) {
			GP::$translation_warnings->add( str_replace( 'warning_', '', $warning ), array( $this, $warning ) );
		}

		// https://github.com/GlotPress/GlotPress-WP/pull/1237
		add_filter( 'gp_warning_placeholders_re', function( $re ) {
			// bcdefgosuxEFGX are standard printf placeholders.
			// % is included to allow/expect %%.
			// l is included for wp_sprintf_l()'s custom %l format.
			// @ is included for Swift (as used for iOS mobile app) %@ string format.
			return '(?<!%)%(\d+\$(?:\d+)?)?(\.\d+)?[bcdefgosuxEFGX%l@]';
		} );
	}

}

function wporg_gp_custom_translation_warnings() {
	global $wporg_gp_custom_translation_warnings;

	if ( ! isset( $wporg_gp_custom_translation_warnings ) ) {
		$wporg_gp_custom_translation_warnings = new WPorg_GP_Custom_Translation_Warnings();
	}

	return $wporg_gp_custom_translation_warnings;
}
add_action( 'plugins_loaded', 'wporg_gp_custom_translation_warnings' );
