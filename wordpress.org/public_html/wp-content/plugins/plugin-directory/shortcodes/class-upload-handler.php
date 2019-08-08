<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use WordPressdotorg\Plugin_Directory\Readme\Parser;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;

/**
 * The [wporg-plugin-upload] shortcode handler to display a plugin uploader.
 *
 * @package WordPressdotorg\Plugin_Directory\Shortcodes
 */
class Upload_Handler {

	/**
	 * Path to temporary plugin folder.
	 *
	 * @var string
	 */
	protected $plugin_dir;

	/**
	 * Path to the detected plugins files.
	 *
	 * @var string
	 */
	protected $plugin_root;

	/**
	 * The uploaded plugin headers.
	 *
	 * @var array
	 */
	protected $plugin;

	/**
	 * The plugin slug.
	 *
	 * @var string
	 */
	protected $plugin_slug;

	/**
	 * Get set up to run tests on the uploaded plugin.
	 */
	public function __construct() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}

	/**
	 * Processes the plugin upload.
	 *
	 * Runs various tests and creates plugin post.
	 *
	 * @return string|WP_Error Confirmation message on success, WP_Error object on failure.
	 */
	public function process_upload() {
		$zip_file         = $_FILES['zip_file']['tmp_name'];
		$this->plugin_dir = Filesystem::unzip( $zip_file );

		$plugin_files = Filesystem::list_files( $this->plugin_dir, true /* Recursive */, '!\.php$!i', 1 /* Depth */ );
		foreach ( $plugin_files as $plugin_file ) {
			$plugin_data = get_plugin_data( $plugin_file, false, false ); // No markup/translation needed.
			if ( ! empty( $plugin_data['Name'] ) ) {
				$this->plugin      = $plugin_data;
				$this->plugin_root = dirname( $plugin_file );
				break;
			}
		}

		// Let's check some plugin headers, shall we?
		// Catches both empty Plugin Name & when no valid files could be found.
		if ( empty( $this->plugin['Name'] ) ) {
			$error = __( 'Error: The plugin has no name.', 'wporg-plugins' );

			return new \WP_Error( 'no_name', $error . ' ' . sprintf(
				/* translators: 1: plugin header line, 2: Documentation URL */
				__( 'Add a %1$s line to your main plugin file and upload the plugin again. For more information, please review our documentation on <a href="%2$s">Plugin Headers</a>.', 'wporg-plugins' ),
				'<code>Plugin Name:</code>',
				__( 'https://developer.wordpress.org/plugins/plugin-basics/header-requirements/', 'wporg-plugins' )
			) );
		}

		// Determine the plugin slug based on the name of the plugin in the main plugin file.
		$this->plugin_slug = remove_accents( $this->plugin['Name'] );
		$this->plugin_slug = preg_replace( '/[^a-z0-9 _.-]/i', '', $this->plugin_slug );
		$this->plugin_slug = str_replace( '_', '-', $this->plugin_slug );
		$this->plugin_slug = sanitize_title_with_dashes( $this->plugin_slug );

		if ( ! $this->plugin_slug ) {
			$error = __( 'Error: The plugin has an unsupported name.', 'wporg-plugins' );

			return new \WP_Error( 'unsupported_name', $error . ' ' . sprintf(
				/* translators: %s: 'Plugin Name:' */
				__( 'Plugin names may only contain latin letters (A-z), numbers, spaces, and hyphens. Please change the %s line in your main plugin file and readme, then you may upload it again.', 'wporg-plugins' ),
				esc_html( $this->plugin['Name'] ),
				'<code>Plugin Name:</code>'
			) );
		}

		// Make sure it doesn't use a slug deemed not to be used by the public.
		if ( $this->has_reserved_slug() ) {
			$error = __( 'Error: The plugin has a reserved name.', 'wporg-plugins' );

			return new \WP_Error( 'reserved_name', $error . ' ' . sprintf(
				/* translators: 1: plugin slug, 2: 'Plugin Name:' */
				__( 'Your chosen plugin name - %1$s - has been reserved and cannot be used. Please change the %2$s line in your main plugin file and readme, then you may upload it again.', 'wporg-plugins' ),
				'<code>' . $this->plugin_slug . '</code>',
				'<code>Plugin Name:</code>'
			) );
		}

		// Make sure it doesn't use a TRADEMARK protected slug.
		if ( $this->has_trademarked_slug() ) {
			$error = __( 'Error: The plugin has a trademarked name.', 'wporg-plugins' );

			return new \WP_Error( 'trademarked_name', $error . ' ' . sprintf(
				/* translators: 1: plugin slug, 2: 'Plugin Name:', 3: plugin email address. */
				__( 'Your chosen plugin name - %1$s - has been flagged as trademark infringement and cannot be used. We have been legally compelled to protect specific trademarks and as such prevent the use of specific terms. Please change the %2$s line in your main plugin file and readme, then you may upload it again. If you feel this is in error, please email us at %3$s and explain why.', 'wporg-plugins' ),
				'<code>' . $this->plugin_slug . '</code>',
				'<code>Plugin Name:</code>',
				'<code>plugins@wordpress.org</code>'
			) );
		}

		$plugin_post = Plugin_Directory::get_plugin_post( $this->plugin_slug );

		// Is there already a plugin with the same slug by a different author?
		if ( $plugin_post && $plugin_post->post_author != get_current_user_id() ) {
			$error = __( 'Error: The plugin already exists.', 'wporg-plugins' );

			return new \WP_Error( 'already_exists', $error . ' ' . sprintf(
				/* translators: 1: plugin slug, 2: 'Plugin Name:' */
				__( 'There is already a plugin with the name %1$s in the directory. Please rename your plugin by changing the %2$s line in your main plugin file and upload it again.', 'wporg-plugins' ),
				'<code>' . $this->plugin_slug . '</code>',
				'<code>Plugin Name:</code>'
			) );
		}

		// Is there already a plugin with the same slug by the same author?
		if ( $plugin_post ) {
			$error = __( 'Error: The plugin has already been submitted.', 'wporg-plugins' );

			return new \WP_Error( 'already_submitted', $error . ' ' . sprintf(
				/* translators: 1: plugin slug, 2: Documentation URL, 3: plugins@wordpress.org */
				__( 'You have already submitted a plugin named %1$s. There is no need to resubmit existing plugins, even for new versions. Simply update your plugin within the directory via <a href="%2$s">SVN</a>. If you need assistance, email <a href="mailto:%3$s">%3$s</a> and let us know.', 'wporg-plugins' ),
				'<code>' . $this->plugin_slug . '</code>',
				__( 'https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/', 'wporg-plugins' ),
				'plugins@wordpress.org'
			) );
		}

		// Prevent short plugin names (they're generally SEO grabs).
		if ( strlen( $this->plugin_slug ) < 5 ) {
			$error = __( 'Error: The plugin slug is too short.', 'wporg-plugins' );

			return new \WP_Error( 'trademarked_name', $error . ' ' . sprintf(
				/* translators: 1: plugin slug, 2: 'Plugin Name:' */
				__( 'Your chosen plugin name - %1$s - is not permitted becuase it is too short. Please change the %2$s line in your main plugin file and readme and upload it again.', 'wporg-plugins' ),
				'<code>' . $this->plugin_slug . '</code>',
				'<code>Plugin Name:</code>'
			) );
		}

		if ( ! $this->plugin['Description'] ) {
			$error = __( 'Error: The plugin has no description.', 'wporg-plugins' );

			return new \WP_Error( 'no_description', $error . ' ' . sprintf(
				/* translators: 1: plugin header line, 2: Documentation URL */
				__( 'Add a %1$s line to your main plugin file and upload the plugin again. Please review our documentation on <a href="%2$s">Plugin Headers</a> for more information.', 'wporg-plugins' ),
				'<code>Description:</code>',
				__( 'https://developer.wordpress.org/plugins/the-basics/header-requirements/', 'wporg-plugins' )
			) );
		}

		if ( ! $this->plugin['Version'] ) {
			$error = __( 'Error: The plugin has no version.', 'wporg-plugins' );

			return new \WP_Error( 'no_version', $error . ' ' . sprintf(
				/* translators: 1: plugin header line, 2: Documentation URL */
				__( 'Add a %1$s line to your main plugin file and upload the plugin again. Please review our documentation on <a href="%2$s">Plugin Headers</a> for more information.', 'wporg-plugins' ),
				'<code>Version:</code>',
				__( 'https://developer.wordpress.org/plugins/the-basics/header-requirements/', 'wporg-plugins' )
			) );
		}

		if ( preg_match( '|[^\d\.]|', $this->plugin['Version'] ) ) {
			$error = __( 'Error: Plugin versions are expected to be numbers.', 'wporg-plugins' );

			return new \WP_Error( 'invalid_version', $error . ' ' . sprintf(
				/* translators: %s: 'Version:' */
				__( 'Version strings can only contain numeric and period characters (i.e. 1.2). Please correct the %s line in your main plugin file and upload the plugin again.', 'wporg-plugins' ),
				'<code>Version:</code>'
			) );
		}

		// Prevent duplicate URLs.
		if ( ! empty( $this->plugin['PluginURI'] ) && ! empty( $this->plugin['AuthorURI'] ) && $this->plugin['PluginURI'] == $this->plugin['AuthorURI'] ) {
			$error = __( 'Error: Your plugin and author URIs are the same.', 'wporg-plugins' );

			return new \WP_Error(
				'plugin_author_uri', $error . ' ' .
				__( 'A plugin URI (Uniform Resource Identifier) is a webpage that provides details about this specific plugin. An author URI is a webpage that provides information about the author of the plugin. Those two URIs must be different. You are not required to provide both, so pick the one that best applies to your situation.', 'wporg-plugins' )
			);
		}

		$readme = $this->find_readme_file();
		if ( empty( $readme ) ) {
			$error = __( 'Error: The plugin has no readme.', 'wporg-plugins' );

			return new \WP_Error( 'no_readme', $error . ' ' . sprintf(
				/* translators: 1: readme.txt, 2: readme.md */
				__( 'The zip file must include a file named %1$s or %2$s. We recommend using %1$s as it will allow you to fully utilize our directory.', 'wporg-plugins' ),
				'<code>readme.txt</code>',
				'<code>readme.md</code>'
			) );
		}
		$readme = new Parser( $readme );

		// Pass it through Plugin Check and see how great this plugin really is.
		// We're not actually using this right now
		$result = $this->check_plugin();

		if ( ! $result ) {
			$error = __( 'Error: The plugin has failed the automated checks.', 'wporg-plugins' );

			return new \WP_Error( 'failed_checks', $error . ' ' . sprintf(
				/* translators: 1: Plugin Check Plugin URL, 2: https://make.wordpress.org/plugins */
				__( 'Please correct the problems with the plugin and upload it again. You can also use the <a href="%1$s">Plugin Check Plugin</a> to test your plugin before uploading. If you have any questions about this please post them to %2$s.', 'wporg-plugins' ),
				'//wordpress.org/plugins/plugin-check/',
				'<a href="https://make.wordpress.org/plugins">https://make.wordpress.org/plugins</a>'
			) );
		}

		// Passed all tests!
		// Let's save everything and get things wrapped up.
		// Create a new post on first-time submissions.
		if ( ! $plugin_post ) {
			$content = '';
			foreach ( $readme->sections as $section => $section_content ) {
				$content .= "\n\n<!--section={$section}-->\n{$section_content}";
			}

			// Add a Plugin Directory entry for this plugin.
			$plugin_post = Plugin_Directory::create_plugin_post( array(
				'post_title'   => $this->plugin['Name'],
				'post_name'    => $this->plugin_slug,
				'post_status'  => 'new',
				'post_content' => $content,
				'post_excerpt' => $this->plugin['Description'],
				// 'tax_input'    => wp_unslash( $_POST['tax_input'] ), // for category selection
				'meta_input'   => array(
					'tested'                   => $readme->tested,
					'requires'                 => $readme->requires,
					'requires_php'             => $readme->requires_php,
					'stable_tag'               => $readme->stable_tag,
					'upgrade_notice'           => $readme->upgrade_notice,
					'contributors'             => $readme->contributors,
					'screenshots'              => $readme->screenshots,
					'donate_link'              => $readme->donate_link,
					'sections'                 => array_keys( $readme->sections ),
					'version'                  => $this->plugin['Version'],
					'header_name'              => $this->plugin['Name'],
					'header_plugin_uri'        => $this->plugin['PluginURI'],
					'header_author'            => $this->plugin['Author'],
					'header_author_uri'        => $this->plugin['AuthorURI'],
					'header_textdomain'        => $this->plugin['TextDomain'],
					'header_description'       => $this->plugin['Description'],
					'assets_screenshots'       => array(),
					'assets_icons'             => array(),
					'assets_banners'           => array(),
					'assets_banners_color'     => false,
					'support_threads'          => 0,
					'support_threads_resolved' => 0,
					'downloads'                => 0,
					'last_updated'             => gmdate( 'Y-m-d H:i:s' ),
					'plugin_status'            => 'new',
					'rating'                   => 0,
					'ratings'                  => array(),
					'active_installs'          => 0,
					'_active_installs'         => 0,
					'usage'                    => array(),
					'_author_ip'               => preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] ),
					'_submitted_date'          => time(),
				),
			) );
			if ( is_wp_error( $plugin_post ) ) {
				return $plugin_post->get_error_message();
			}
		}

		$attachment = $this->save_zip_file( $plugin_post->ID );
		if ( is_wp_error( $attachment ) ) {
			return $attachment->get_error_message();
		}

		// Send plugin author an email for peace of mind.
		$this->send_email_notification();

		do_action( 'plugin_upload', $this->plugin, $plugin_post );

		$message = sprintf(
			/* translators: 1: plugin name, 2: plugin slug, 3: plugins@wordpress.org */
			__( 'Thank you for uploading %1$s to the WordPress Plugin Directory. It has been given the initial plugin slug of %2$s, however that is subject to change based on the results of your code review. If this slug is incorrect, please contact us immediately, as it cannot be changed once your plugin is approved.' ),
			esc_html( $this->plugin['Name'] ),
			'<code>' . $this->plugin_slug . '</code>'
		) . '</p><p>';

		$message .= sprintf(
			/* translators: 1: plugins@wordpress.org */
			__( 'We&rsquo;ve sent you an email verifying this submission. Please make sure to whitelist our email address - <a href="mailto:%1$s">%1$s</a> - to ensure you receive all our communications.' ),
			'plugins@wordpress.org'
		) . '</p><p>';

		$message .= __( 'If there is any error in your submission, please email us as soon as possible. We can correct many issues before approval.', 'wporg-plugins' ) . '</p><p>';

		$message .= sprintf(
			/* translators: 1: URL to guidelines; 2: URL to FAQs; */
			wp_kses_post( __( 'While you&#8217;re waiting on your review, please take the time to read <a href="%1$s">the developer guidelines</a> and <a href="%2$s">the developer FAQ</a> as they will address most questions.', 'wporg-plugins' ) ),
			'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/',
			'https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/'
		);

		// Success!
		return $message;
	}

	/**
	 * Whether the uploaded plugin uses a reserved slug.
	 *
	 * @return bool
	 */
	public function has_reserved_slug() {
		$reserved_slugs = array(
			// Plugin Directory URL parameters.
			'browse',
			'tag',
			'search',
			'filter',
			'upload',
			'featured',
			'popular',
			'new',
			'updated',
			'about',
			'developers',
			'admin',
			'wp-admin',
			'wordpress',
			'jquery',
		);

		return in_array( $this->plugin_slug, $reserved_slugs );
	}

	/**
	 * Whether the uploaded plugin uses a trademark in the slug.
	 *
	 * @return bool
	 */
	public function has_trademarked_slug() {
		$trademarked_slugs = array(
			'contact-form-7',
			'facebook',
			'google',
			'-gram',
			'gram-',
			'instagram',
			'insta',
			'microsoft',
			'paypal',
			'twitter',
			'tweet',
			'whatsapp',
			'whats-app',
			'woocommerce',
			'wordpress',
			'yoast',
		);

		$has_trademarked_slug = false;

		foreach ( $trademarked_slugs as $trademark ) {
			if ( false !== strpos( $this->plugin_slug, $trademark ) ) {
				$has_trademarked_slug = true;
				break;
			}
		}

		return $has_trademarked_slug;
	}

	/**
	 * Find the plugin readme file.
	 *
	 * Looks for either a readme.txt or readme.md file, prioritizing readme.txt.
	 *
	 * @return string The plugin readme.txt or readme.md filename.
	 */
	protected function find_readme_file() {
		$files = Filesystem::list_files( $this->plugin_root, false /* non-recursive */, '!^readme\.(txt|md)$!i' );

		// Prioritize readme.txt file.
		foreach ( $files as $file ) {
			if ( '.txt' === strtolower( substr( $file, -4 ) ) ) {
				return $file;
			}
		}

		return reset( $files );
	}

	/**
	 * Sends a plugin through Plugin Check.
	 *
	 * @return bool Whether the plugin passed the checks.
	 */
	public function check_plugin() {
		return true;
		// Run the checks.
		// @todo Include plugin checker.
		// Pass $this->plugin_root as the plugin root.
		$result = true;

		// Display the errors.
		if ( $result ) {
			$verdict = array( 'pc-pass', __( 'Pass', 'wporg-plugins' ) );
		} else {
			$verdict = array( 'pc-fail', __( 'Fail', 'wporg-plugins' ) );
		}

		echo '<h4>' . sprintf( __( 'Results of Automated Plugin Scanning: %s', 'wporg-plugins' ), vsprintf( '<span class="%1$s">%2$s</span>', $verdict ) ) . '</h4>';
		echo '<ul class="tc-result">' . __( 'Result', 'wporg-plugins' ) . '</ul>';
		echo '<div class="notice notice-info"><p>' . __( 'Note: While the automated plugin scan is based on the Plugin Review Guidelines, it is not a complete review. A successful result from the scan does not guarantee that the plugin will pass review. All submitted plugins are reviewed manually before approval.', 'wporg-plugins' ) . '</p></div>';

		return $result;
	}

	/**
	 * Saves zip file and attaches it to the plugin post.
	 *
	 * @param int $post_id Post ID.
	 * @return int|\WP_Error Attachment ID or upload error.
	 */
	public function save_zip_file( $post_id ) {

		// Upload folders are already year/month based. A second-based prefix should be specific enough.
		$_FILES['zip_file']['name'] = date( 'd_H-i-s' ) . '_' . $_FILES['zip_file']['name'];

		add_filter( 'site_option_upload_filetypes', array( $this, 'whitelist_zip_files' ) );
		add_filter( 'default_site_option_upload_filetypes', array( $this, 'whitelist_zip_files' ) );

		$attachment_id = media_handle_upload( 'zip_file', $post_id );

		remove_filter( 'site_option_upload_filetypes', array( $this, 'whitelist_zip_files' ) );
		remove_filter( 'default_site_option_upload_filetypes', array( $this, 'whitelist_zip_files' ) );

		return $attachment_id;
	}

	/**
	 * Sends out an email confirmation to the plugin's author.
	 */
	public function send_email_notification() {

		/* translators: %s: plugin name */
		$email_subject = sprintf(
			__( '[WordPress Plugin Directory] Successful Plugin Submission - %s', 'wporg-plugins' ),
			$this->plugin['Name']
		);

		/*
			Please leave the blank lines in place.
		*/
		$email_content = sprintf(
			// translators: 1: plugin name, 2: plugin slug.
			__(
'Thank you for uploading %1$s to the WordPress Plugin Directory. We will review your submission as soon as possible and send you a follow up email with the results.

Your plugin has been given the initial slug of %2$s based on your diplay name of %1$s. This is subject to change based on the results of your review.

If there are any problems with your submission, please REPLY to this email and let us know right away. In most cases, we can correct errors as long as the plugin has not yet been approved. For situations like an incorrect plugin slug, we are unable to change that post approval. If you do not inform us of any requirements now, we will be unable to honor them later.

We recommend you review the following links to understand the review process and our expectations:

Guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
Frequently Asked Questions: https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/

Also, make sure to follow our official blog: https://make.wordpress.org/plugins/

--
The WordPress Plugin Directory Team
https://make.wordpress.org/plugins', 'wporg-plugins'
			),
			$this->plugin['Name'],
			$this->plugin_slug
		);

		$user_email = wp_get_current_user()->user_email;

		wp_mail( $user_email, $email_subject, $email_content, 'From: plugins@wordpress.org' );
	}

	// Helper.
	/**
	 * Whitelist zip files to be allowed to be uploaded to the media library.
	 *
	 * As we only want to accept *.zip uploads, we specifically exclude all other types here.
	 *
	 * @return string Whitelisted ZIP filetypes.
	 */
	public function whitelist_zip_files() {
		return 'zip';
	}

}
