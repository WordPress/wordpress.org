<?php
namespace WordPressdotorg\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Tools;

use WP_Post;
use WP_User;

/**
 * Validate trademarks for a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Trademarks {

	/**
	 * List of trademarked slugs.
	 *
	 * @var array
	 */
	public static $trademarked_slugs = array(
		'adobe-',
		'adsense-',
		'advanced-custom-fields-',
		'adwords-',
		'akismet-',
		'all-in-one-wp-migration',
		'amazon-',
		'android-',
		'apple-',
		'applenews-',
		'applepay-',
		'aws-',
		'azon-',
		'bbpress-',
		'bing-',
		'booking-com',
		'bootstrap-',
		'buddypress-',
		'chatgpt-',
		'chat-gpt-',
		'cloudflare-',
		'contact-form-7-',
		'cpanel-',
		'disqus-',
		'divi-',
		'dropbox-',
		'easy-digital-downloads-',
		'elementor-',
		'envato-',
		'fbook',
		'facebook',
		'fb-',
		'fb-messenger',
		'fedex-',
		'feedburner',
		'firefox-',
		'fontawesome-',
		'font-awesome-',
		'ganalytics-',
		'gberg',
		'github-',
		'givewp-',
		'google-',
		'googlebot-',
		'googles-',
		'gravity-form-',
		'gravity-forms-',
		'gravityforms-',
		'gtmetrix-',
		'gutenberg',
		'guten-',
		'hubspot-',
		'ig-',
		'insta-',
		'instagram',
		'internet-explorer-',
		'ios-',
		'jetpack-',
		'macintosh-',
		'macos-',
		'mailchimp-',
		'microsoft-',
		'ninja-forms-',
		'oculus',
		'onlyfans-',
		'only-fans-',
		'opera-',
		'paddle-',
		'paypal-',
		'pinterest-',
		'plugin',
		'skype-',
		'stripe-',
		'tiktok-',
		'tik-tok-',
		'trustpilot',
		'twitch-',
		'twitter-',
		'tweet',
		'ups-',
		'usps-',
		'vvhatsapp',
		'vvcommerce',
		'vva-',
		'vvoo',
		'wa-',
		'webpush-vn',
		'wh4tsapps',
		'whatsapp',
		'whats-app',
		'watson',
		'windows-',
		'wocommerce',
		'woocom-',
		'woocommerce', // technically ending with '-for-woocommerce' is allowed.
		'woocomerce',
		'woo-commerce',
		'woo-',
		'wo-',
		'wordpress',
		'wordpess',
		'wpress',
		'wp-', // Can be used, but often abused.
		'wp-mail-smtp-',
		'yandex-',
		'yahoo-',
		'yoast',
		'youtube-',
		'you-tube-',
	);

	/**
	 * List of trademark exceptions.
	 *
	 * @var array
	 */
	public static $trademark_exceptions = array(
		'adobe.com'             => array( 'adobe' ),
		'automattic.com'        => array( 'akismet', 'akismet-', 'jetpack', 'jetpack-', 'wordpress', 'wp-', 'woo', 'woo-', 'woocommerce', 'woocommerce-' ),
		'facebook.com'          => array( 'facebook', 'instagram', 'oculus', 'whatsapp' ),
		'support.microsoft.com' => array( 'bing-', 'microsoft-' ),
		'trustpilot.com'        => array( 'trustpilot' ),
		'microsoft.com'         => array( 'bing-', 'microsoft-' ),
		'yandex-team.ru'        => array( 'yandex' ),
		'yoast.com'             => array( 'yoast' ),
		'opera.com'             => array( 'opera-' ),
		'adobe.com'             => array( 'adobe-' ),
		'stripe.com'            => array( 'stripe-' ),

		// Published plugins can use 'wp-'.
		'published-plugin'      => array( 'wp-' ),
	);

	/**
	 * List of trademarks that are allowed as 'for-whatever' ONLY.
	 *
	 * @var array
	 */
	public static $for_use_exceptions = array(
		'woocommerce',
	);

	/**
	 * List of portmanteaus, commonly used 'combo' names.
	 * To prevent things like 'woopress'.
	 *
	 * @var array
	 */
	public static $portmanteaus = array(
		'woo',
	);

	/**
	 * Check if a plugin name is trademarked.
	 *
	 * @param string $check                     The plugin name to check.
	 * @param array|WP_User|WP_Post $exceptions An array of exceptions to the trademark checks, or a WP_User or WP_Post object to fetch their exceptions.
	 * @return array|false The trademarked slug if found, false otherwise.
	 */
	public static function check( $check, $exceptions = [] ) {
		// This logic is from Upload_Handler::generate_plugin_slug()
		$check = remove_accents( $check );
		$check = preg_replace( '/[^a-z0-9 _.-]/i', '', $check );
		$check = str_replace( '_', '-', $check );
		$check = sanitize_title_with_dashes( $check );

		return self::check_slug( $check, $exceptions );
	}

	/**
	 * Whether a slug-like-text passes trademark checks.
	 *
	 * @param string                $plugin_slug The slug-like-text to check.
	 * @param array|WP_User|WP_Post $exceptions  An array of exceptions to the trademark checks, or a WP_User or WP_Post object to fetch their exceptions.
	 * @return array|false The trademarked slug if found, false otherwise.
	 */
	public static function check_slug( $plugin_slug, $exceptions = [] ) {
		if ( $exceptions instanceof WP_User ) {
			$exceptions = self::get_user_exceptions( $exceptions );
		} elseif ( $exceptions instanceof WP_Post ) {
			$exceptions = self::get_plugin_exceptions( $exceptions );
		}

		// The list of trademarks to check for.
		$trademarked_slugs    = self::$trademarked_slugs;

		// Domains from which exceptions would be accepted.
		$trademark_exceptions = self::$trademark_exceptions;

		// Trademarks that are allowed as 'for-whatever' ONLY.
		$for_use_exceptions   = self::$for_use_exceptions;

		// Commonly used 'combo' names (to prevent things like 'woopress').
		$portmanteaus         = self::$portmanteaus;

		// If this check has exceptions, remove those trademarks from the checks.
		foreach ( (array) $exceptions as $exception ) {
			if ( ! isset( $trademark_exceptions[ $exception ] ) ) {
				continue;
			}

			// Remove any that are in the exception list.
			$trademarked_slugs = array_diff( $trademarked_slugs, $trademark_exceptions[ $exception ] );
			$portmanteaus      = array_diff( $portmanteaus, $trademark_exceptions[ $exception ] );
		}

		$has_trademarked_slug = [];

		foreach ( $trademarked_slugs as $trademark ) {
			if ( str_ends_with( $trademark, '-' ) ) {
				// Trademarks ending in "-" indicate slug cannot begin with that term.
				if ( str_starts_with( $plugin_slug, $trademark ) ) {
					$has_trademarked_slug[] = $trademark;
				}

			} elseif (
				// Otherwise, the term cannot appear anywhere in slug.
				str_contains( $plugin_slug, $trademark )
			) {
				// But first we must check to see if this is allowed as "for-TRADEMARK".
				if ( in_array( $trademark, $for_use_exceptions ) ) {
					$for_trademark = '-for-' . $trademark;

					// At this point we might be okay, but there's one more check.
					if ( str_ends_with( $plugin_slug, $for_trademark ) ) {
						// Yes the slug ENDS with 'for-TRADEMARK'.
						continue; // Check the next trademark
					}
				}

				// The term cannot exist anywhere in the plugin slug, and it's not a for-use exception.
				$has_trademarked_slug[] = $trademark;
			}
		}

		// Check portmanteaus.
		foreach ( $portmanteaus as $portmanteau ) {
			if ( str_starts_with( $plugin_slug, $portmanteau ) ) {
				// Check there isn't a longer matching trademark already flagged.
				// For example, 'woo' should not flag if 'woocommerce' is already flagged.
				if ( ! preg_grep( '!^' . preg_quote( $portmanteau, '!' ) . '!', $has_trademarked_slug ) ) {
					$has_trademarked_slug[] = $portmanteau . '-'; // State that the portmanteau cannnot start the text.
				}
			}
		}

		return array_unique( $has_trademarked_slug ) ?: false;
	}

	/**
	 * Get list of trademark exceptions for the current user.
	 *
	 * @return array
	 */
	public static function get_user_exceptions( $user = false ) {
		$exceptions = [];

		$user = $user ?: wp_get_current_user();

		// The users email domain.
		if ( $user && $user instanceof WP_User && $user->exists() ) {
			$exceptions[] = explode( '@', $user->user_email, 2 )[1];
		}

		return $exceptions;
	}

	/**
	 * Get the exceptions allowed for a plugin.
	 *
	 * @param WP_Post $plugin The plugin object.
	 * @return array
	 */
	public static function get_plugin_exceptions( $post ) {
		// Assume all of the committers (and owner) are exceptions.
		$committers = Tools::get_plugin_committers( $post );

		$committers = array_map( function( $user_login ) { return get_user_by( 'login', $user_login); }, $committers );
		$committers[] = get_user_by( 'id', $post->post_author );

		$exceptions = [];
		foreach ( $committers as $user ) {
			$exceptions = array_merge( $exceptions, self::get_user_exceptions( $user ) );
		}

		// A published plugin gets some exceptions too.
		if ( $post && in_array( $post->post_status, [ 'publish', 'closed', 'disabled', 'approved' ] ) ) {
			$exceptions[] = 'published-plugin';
		}

		return array_unique( $exceptions );
	}
}