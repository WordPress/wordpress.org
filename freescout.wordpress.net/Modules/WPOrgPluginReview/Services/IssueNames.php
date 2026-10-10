<?php
/**
 * Short names for the issues review emails list.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Services;

/**
 * Names issues by their titles, as the team calls them, and says how much each one matters.
 */
final class IssueNames {

	/**
	 * Issues that usually decide a review.
	 *
	 * @var int
	 */
	public const PRIORITY_HIGH = 2;

	/**
	 * Issues that matter.
	 *
	 * @var int
	 */
	public const PRIORITY_NORMAL = 1;

	/**
	 * Issues that are quick to fix.
	 *
	 * @var int
	 */
	public const PRIORITY_LOW = 0;

	/**
	 * Part of an issue's title, its short name, and its priority; the first whose part an issue's title contains names it.
	 *
	 * @var array[]
	 */
	private const NAMES = array(
		array( 'You haven\'t added yourself to the "Contributors" list for this plugin.', 'Readme: Contributors list', self::PRIORITY_LOW ),
		array( 'The URL(s) declared in your plugin seems to be invalid or does not work', 'URLs not working', self::PRIORITY_LOW ),
		array( 'Requires at least value has issues', 'Header: Requires at least', self::PRIORITY_LOW ),
		array( 'Tested Up To Value is Out of Date, Invalid, or Missing', 'Header: Tested Up', self::PRIORITY_LOW ),
		array( 'Requires Plugins: found possible dependencies', 'Header: Requires plugins (possible dependencies)', self::PRIORITY_LOW ),
		array( 'Requires Plugins, plugin not found in WordPress.org directory', 'Header: Requires plugins (plugin not found)', self::PRIORITY_NORMAL ),
		array( 'WordPress.org directory assets in the plugin code', 'Directory /assets/', self::PRIORITY_LOW ),
		array( 'Using composer but could not find composer.json file', 'composer.json not found', self::PRIORITY_LOW ),
		array( 'Editor block, apiVersion is outdated', 'block.json apiVersion outdated', self::PRIORITY_LOW ),
		array( 'The link to the ajax endpoint may not work in some configurations.', 'Static link ajax endpoint', self::PRIORITY_LOW ),
		array( 'Forcing PHP Sessions on all pages', 'PHP Sessions', self::PRIORITY_NORMAL ),
		array( 'Freemius is not set as compliant with WordPress.org', 'Freemius Compliance', self::PRIORITY_LOW ),
		array( 'Don\'t Force Set PHP Limits Globally', 'PHP limits change', self::PRIORITY_NORMAL ),
		array( 'Avoiding the use of certain PHP functions on remote files', 'PHP functions on remote files', self::PRIORITY_LOW ),
		array( 'Newer versions of Sweetalert violate our guidelines', 'Sweetalert', self::PRIORITY_NORMAL ),
		array( 'Errors on site when testing with WP_DEBUG', 'Errors using WP_DEBUG', self::PRIORITY_HIGH ),
		array( 'Don\'t Use Error Reporting in Production Code', 'error_reporting()', self::PRIORITY_LOW ),
		array( 'Calling file locations poorly', 'Files locations poorly', self::PRIORITY_NORMAL ),
		array( 'Determine files and directories locations correctly', 'Determine directories correctly', self::PRIORITY_LOW ),
		array( 'Linking directly to 5 stars reviews', '5 stars reviews', self::PRIORITY_LOW ),
		array( 'Using CURL Instead of HTTP API', 'Curl', self::PRIORITY_LOW ),
		array( 'Saving data in the plugin folder and/or asking users to edit/write to plugin.', 'Saving data out of /uploads/', self::PRIORITY_NORMAL ),
		array( 'Do not use HEREDOC syntax in your plugins', 'HEREDOC', self::PRIORITY_LOW ),
		array( 'Using anonymous functions as a callback for hooks', 'Anonymous function on hook callback', self::PRIORITY_LOW ),
		array( 'Code not compatible with the GPL license included', 'No GPL Code', self::PRIORITY_HIGH ),
		array( 'Names of files that might not be compatible across filesystems', 'Filenames compatibility', self::PRIORITY_LOW ),
		array( 'PHP libraries that might conflict with the same library loaded by other plugins', 'PHP Libraries may conflict', self::PRIORITY_LOW ),
		array( 'No publicly documented resource for your', 'Source code', self::PRIORITY_NORMAL ),
		array( 'Attempting to process custom CSS/JS/PHP / Allowing arbitrary script insertion', 'Script/CSS insertion', self::PRIORITY_HIGH ),
		array( 'Use wp_enqueue commands', 'Enqueue', self::PRIORITY_LOW ),
		array( 'Internationalization: Don\'t use variables or defines as text, context or text domain parameters.', 'i18n: Variables', self::PRIORITY_LOW ),
		array( 'Internationalization: Text domain does not match plugin slug.', 'i18n: Slug', self::PRIORITY_LOW ),
		array( 'Using load_plugin_textdomain() for loading the plugin translations is not needed for WordPress.org directory since WordPress 4.6.', 'load_plugin_textdomain()', self::PRIORITY_LOW ),
		array( 'Undocumented use of a 3rd Party / external service', 'External service not documented', self::PRIORITY_LOW ),
		array( 'Review: Missing permission_callback in REST API Route', 'permission_callback in REST API Route', self::PRIORITY_LOW ),
		array( 'Data Must be Sanitized, Escaped, and Validated', 'Sanitizing', self::PRIORITY_LOW ),
		array( 'Proper sanitization of inputs', 'Sanitizing', self::PRIORITY_LOW ),
		array( 'Proper escaping of outputs', 'Escaping', self::PRIORITY_LOW ),
		array( 'Variables and options must be escaped when echo\'d', 'Escaping', self::PRIORITY_LOW ),
		array( 'Nonces and User Permissions Before Processing Requests', 'Nonces', self::PRIORITY_LOW ),
		array( 'Nonces and User Permissions Needed for Security', 'Nonces', self::PRIORITY_LOW ),
		array( 'Generic function/class/define/namespace/option names', 'Prefix', self::PRIORITY_LOW ),
		array( 'Use Prefixes for declarations, globals and stored data', 'Prefix', self::PRIORITY_LOW ),
		array( 'Using Deprecated Code/Functions', 'Deprecated', self::PRIORITY_LOW ),
		array( 'Allowing Direct File Access to plugin files', 'Direct File Access', self::PRIORITY_LOW ),
		array( 'How can we check the functionality of this plugin?', 'Functionality check', self::PRIORITY_HIGH ),
		array( 'Calling files remotely', 'Calling files remotely', self::PRIORITY_LOW ),
		array( 'Processing the whole input', 'Processing the whole input', self::PRIORITY_LOW ),
		array( 'Trialware and Locked Features', 'Trialware and Locked Features', self::PRIORITY_HIGH ),
		array( 'Trialware and license checks are not permitted', 'Trialware and Locked Features', self::PRIORITY_HIGH ),
		array( 'Out of Date Libraries', 'Out of Date Libraries', self::PRIORITY_LOW ),
		array( 'Calling core loading files directly', 'Calling core files directly', self::PRIORITY_LOW ),
		array( 'Creating / login users', 'Creating / login users', self::PRIORITY_HIGH ),
		array( 'Changing global behaviour', 'Changing global behaviour', self::PRIORITY_HIGH ),
		array( 'Including An Update Checker', 'Including An Update Checker', self::PRIORITY_HIGH ),
		array( 'Requires PHP value has issues', 'Requires PHP', self::PRIORITY_LOW ),
		array( 'Included Unneeded Folders', 'Included Unneeded Folders', self::PRIORITY_LOW ),
		array( 'Phoning Home / Collecting User Data Without Opt-In Consent', 'Phoning Home / Collecting User Data', self::PRIORITY_HIGH ),
		array( 'The main file of the plugin has a name that does not follow the convention', 'Main plugin file naming', self::PRIORITY_NORMAL ),
		array( 'Arbitrary input in sensitive function parameters or calls', 'Arbitrary input in sensitive parameters/calls', self::PRIORITY_HIGH ),
	);

	/**
	 * An issue's short name and priority.
	 *
	 * @param string $title The issue's title in the email.
	 * @return array Name, and priority; an issue without a short name keeps its title, and has no priority.
	 */
	public static function name( string $title ): array {
		$title = trim( $title );

		foreach ( self::NAMES as list( $match, $name, $priority ) ) {
			if ( false !== mb_stripos( $title, $match ) ) {
				return array(
					'name'     => $name,
					'priority' => $priority,
				);
			}
		}

		return array(
			'name'     => $title,
			'priority' => null,
		);
	}
}
