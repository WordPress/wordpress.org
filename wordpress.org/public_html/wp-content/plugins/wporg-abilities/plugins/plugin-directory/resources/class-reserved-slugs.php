<?php
/**
 * Reserved & Trademarked Slugs resource.
 *
 * @package WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resources
 */

// phpcs:disable WordPress.WP.CapitalPDangit.MisspelledInText -- Lowercase "wordpress" is intentional (slugs).

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resources;

use WordPressdotorg\Abilities\Plugins\Plugin_Directory\Ability_Base;
use WordPressdotorg\Plugin_Directory\Admin\Metabox\Review_Tools;
use WordPressdotorg\Plugin_Directory\Trademarks;

defined( 'ABSPATH' ) || exit;

/**
 * Reserved_Slugs class.
 */
class Reserved_Slugs extends Ability_Base {

	/**
	 * Register this resource as an ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'wporg/plugins/plugin-directory/reserved-slugs',
			array(
				'label'               => 'Reserved & Trademarked Slugs',
				'description'         => 'Plugin slug terms that are reserved or trademarked and cannot be used in new plugin submissions.',
				'category'            => 'wporg-plugins-plugin-directory',
				'execute_callback'    => array( __CLASS__, 'execute' ),
				'permission_callback' => '__return_true',
				'meta'                => array(
					'mcp' => array( 'type' => 'resource' ),
					'uri' => 'wporg://plugins/plugin-directory/reserved-slugs',
				),
			)
		);
	}

	/**
	 * Return the resource content.
	 *
	 * @return array MCP resource contents array.
	 */
	public static function execute(): array {
		self::maybe_load_plugin_directory();

		if ( class_exists( Trademarks::class ) && class_exists( Review_Tools::class ) ) {
			$text = self::get_dynamic_content();
		} else {
			$text = self::get_static_content();
		}

		return array(
			array(
				'uri'      => 'wporg://plugins/plugin-directory/reserved-slugs',
				'text'     => $text,
				'mimeType' => 'text/markdown',
			),
		);
	}

	/**
	 * Build content dynamically from plugin directory source classes.
	 *
	 * @return string Markdown content.
	 */
	private static function get_dynamic_content(): string {
		$trademarked_slugs = implode( ', ', Trademarks::$trademarked_slugs );
		$for_use_only      = implode( ', ', Trademarks::$for_use_exceptions );
		$portmanteaus      = implode( ', ', Trademarks::$portmanteaus );
		$review_reserved   = implode( ', ', Review_Tools::$reserved_slugs );
		$review_restricted = implode( ', ', Review_Tools::$restricted_slugs );

		$exceptions = array();
		foreach ( Trademarks::$trademark_exceptions as $domain => $terms ) {
			$exceptions[] = sprintf( '- **%s**: %s', $domain, implode( ', ', $terms ) );
		}
		$exceptions_list = implode( "\n", $exceptions );

		return <<<MD
# Reserved & Trademarked Plugin Slugs

The following terms cannot be used as plugin slugs or slug prefixes when submitting to the WordPress.org plugin directory.

## Slug Rules

- Minimum 5 characters
- Only lowercase alphanumeric characters and hyphens
- Cannot start with a number
- Cannot match an existing plugin slug in the directory

## Reserved Slugs

These slugs are permanently reserved (plugin directory URL parameters and special cases):

about, admin, browse, category, developers, developer, featured, filter, new, page, plugins, popular, post, search, tag, updated, upload, wp-admin, jquery, wordpress, akismet-anti-spam, site-kit-by-google, yoast-seo, woo, wp-media-folder, wp-file-download, wp-table-manager, acf-repeater, acf-flexible-content, acf-options-page, acf-gallery

## Commonly Abused Terms

Slugs containing these terms receive extra scrutiny during review:

{$review_reserved}

## Restricted Slugs

These generic or high-value terms are restricted:

{$review_restricted}

## Trademarked Terms

Plugin slugs cannot use these trademarked terms (unless you own the trademark). Terms ending in `-` block slugs that start with them; others block exact matches or containment:

{$trademarked_slugs}

## Portmanteau Restrictions

These terms cannot be combined with other trademarked terms (e.g., "woopress"):

{$portmanteaus}

## Trademark Exceptions

Trademark owners can use their own trademarks, verified by email domain:

{$exceptions_list}

## For-Use-Only Terms

These terms are only allowed as a suffix in the pattern "{name}-for-{term}":

{$for_use_only}

## Tip

Before submitting, verify your chosen slug against these rules to avoid rejection.
MD;
	}

	/**
	 * Fallback content when plugin directory classes are unavailable.
	 *
	 * @return string Markdown content.
	 */
	private static function get_static_content(): string {
		return <<<'MD'
# Reserved & Trademarked Plugin Slugs

The following terms cannot be used as plugin slugs or slug prefixes when submitting to the WordPress.org plugin directory.

## Slug Rules

- Minimum 5 characters
- Only lowercase alphanumeric characters and hyphens
- Cannot start with a number
- Cannot match an existing plugin slug in the directory

## Reserved Slugs

These slugs are permanently reserved and cannot be used:

about, admin, browse, category, developers, developer, featured, filter, new, page, plugins, popular, post, search, tag, updated, upload, wp-admin, jquery, wordpress

## Trademarked Prefixes

Plugin slugs cannot start with trademarked terms (unless you own the trademark), including but not limited to:

adobe-, adsense-, advanced-custom-fields-, akismet-, amazon-, android-, apple-, aws-, bbpress-, bing-, bootstrap-, buddypress-, chatgpt-, chat-gpt-, cloudflare-, contact-form-7-, cpanel-, disqus-, divi-, dropbox-, easy-digital-downloads-, elementor-, envato-, facebook, fb-, fedex-, firefox-, fontawesome-, font-awesome-, github-, givewp-, google-, gravity-form-, gravity-forms-, gravityforms-, gtmetrix-, gutenberg, guten-, hubspot-, ig-, instagram, ios-, jetpack-, macintosh-, macos-, mailchimp-, microsoft-, ninja-forms-, oculus, onlyfans-, opera-, paddle-, paypal-, pinterest-, skype-, stripe-, tiktok-, tik-tok-, trustpilot, twitch-, twitter-, ups-, usps-, whatsapp, windows-, woocommerce, woo-commerce, woo-, wordpress, wp-, yandex-, yahoo-, yoast, youtube-

## Exceptions

- Trademark owners can use their own trademarks (verified by email domain)
- Already-published plugins can use the "wp-" prefix
- "woocommerce" is allowed only as a suffix in the pattern "{name}-for-woocommerce"

## Tip

Before submitting, verify your chosen slug against these rules to avoid rejection.
MD;
	}
}
