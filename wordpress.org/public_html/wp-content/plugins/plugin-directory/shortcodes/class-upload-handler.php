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
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
	}

	/**
	 * Processes the plugin upload.
	 *
	 * Runs various tests and creates plugin post.
	 *
	 * @return string Failure or success message.
	 */
	public function process_upload() {
		$zip_file = $_FILES['zip_file']['tmp_name'];
		$this->plugin_dir = Filesystem::unzip( $zip_file );

		$plugin_files = Filesystem::list_files( $this->plugin_dir, true /* Recursive */, '!\.php$!i' );
		foreach ( $plugin_files as $plugin_file ) {
			$plugin_data = get_plugin_data( $plugin_file, false, false ); // No markup/translation needed.
			if ( ! empty( $plugin_data['Name'] ) ) {
				$this->plugin = $plugin_data;
				$this->plugin_root = dirname( $plugin_file );
				break;
			}
		}

		// Let's check some plugin headers, shall we?
		// Catches both empty Plugin Name & when no valid files could be found.
		if ( empty( $this->plugin['Name'] ) ) {
			$error = __( 'The plugin has no name.', 'wporg-plugins' );

			/* translators: 1: plugin header line, 2: Codex URL */
			return $error . ' ' . sprintf( __( 'Add a %1$s line to your main plugin file and upload the plugin again. <a href="%2$s">Plugin Headers</a>', 'wporg-plugins' ),
				'<code>Plugin Name:</code>',
				__( 'https://codex.wordpress.org/File_Header', 'wporg-plugins' )
			);
		}

		// Determine the plugin slug based on the name of the plugin in the main plugin file.
		$this->plugin_slug = remove_accents( $this->plugin['Name'] );
		$this->plugin_slug = preg_replace( '/[^a-z0-9 _.-]/i', '', $this->plugin_slug );
		$this->plugin_slug = sanitize_title_with_dashes( $this->plugin_slug );

		if ( ! $this->plugin_slug ) {
			$error = __( 'The plugin has an unsupported name.', 'wporg-plugins' );

			/* translators: %s: 'Plugin Name:' */
			return $error . ' ' . sprintf( __( 'Plugin names can only contain latin letters (A-z), numbers, spaces, and hyphens. Please change the %s line in your main plugin file and upload it again.', 'wporg-plugins' ),
				esc_html( $this->plugin['Name'] ),
				'<code>Plugin Name:</code>'
			);
		}

		// Make sure it doesn't use a slug deemed not to be used by the public.
		if ( $this->has_reserved_slug() ) {
			$error = __( 'The plugin has a reserved name.', 'wporg-plugins' );
			
			/* translators: 1: plugin slug, 2: 'Plugin Name:' */
			return $error . ' ' . sprintf( __( 'Your chosen plugin name - %1$s - has been reserved for use by WordPress. Please change the %2$s line in your main plugin file and upload it again.', 'wporg-plugins' ),
				'<code>' . $this->plugin_slug . '</code>',
				'<code>Plugin Name:</code>'
			);
		}

		$plugin_post = Plugin_Directory::get_plugin_post( $this->plugin_slug );

		// Is there already a plugin with the same slug by a different author?
		if ( $plugin_post && $plugin_post->post_author != get_current_user_id() ) {
			$error = __( 'The plugin already exists.', 'wporg-plugins' );
			
			/* translators: 1: plugin slug, 2: 'Plugin Name:' */
			return $error . ' ' . sprintf( __( 'There is already a plugin called %1$s by a different author. Please change the %2$s line in your main plugin file and upload it again.', 'wporg-plugins' ),
				'<code>' . $this->plugin_slug . '</code>',
				'<code>Plugin Name:</code>'
			);
		}

		// Is there already a plugin with the same slug by the same author?
		if ( $plugin_post ) {
			$error = __( 'The plugin has already been submitted.', 'wporg-plugins' );
			
			/* translators: 1: plugin slug, 2: plugins@wordpress.org */
			return $error . ' ' . sprintf( __( 'You have already submitted a plugin called %1$s. Please be patient and wait for a review. If you have made a mistake, please email <a href="mailto:%2$s">%2$s</a> and let us know.', 'wporg-plugins' ),
				'<code>' . $this->plugin_slug . '</code>',
				'plugins@wordpress.org'
			);
		}

		if ( ! $this->plugin['Description'] ) {
			$error = __( 'The plugin has no description.', 'wporg-plugins' );

			/* translators: 1: plugin header line, 2: Codex URL */
			return $error . ' ' . sprintf( __( 'Add a %1$s line to your main plugin file and upload the plugin again. <a href="%2$s">Plugin Headers</a>', 'wporg-plugins' ),
				'<code>Description:</code>',
				__( 'https://codex.wordpress.org/File_Header', 'wporg-plugins' )
			);
		}

		if ( ! $this->plugin['Version'] ) {
			$error = __( 'The plugin has no version.', 'wporg-plugins' );

			/* translators: 1: plugin header line, 2: Codex URL */
			return $error . ' ' . sprintf( __( 'Add a %1$s line to your main plugin file and upload the plugin again. <a href="%2$s">Plugin Headers</a>', 'wporg-plugins' ),
				'<code>Version:</code>',
				__( 'https://codex.wordpress.org/File_Header', 'wporg-plugins' )
			);
		}

		if ( preg_match( '|[^\d\.]|', $this->plugin['Version'] ) ) {
			$error = __( 'The plugin has an invalid version.', 'wporg-plugins' );
			
			/* translators: %s: 'Version:' */
			return $error . ' ' . sprintf( __( 'Version strings can only contain numeric and period characters (like 1.2). Please fix your %s line in your main plugin file and upload the plugin again.', 'wporg-plugins' ),
				'<code>Version:</code>'
			);
		}

		// Prevent duplicate URLs.
		if ( ! empty( $this->plugin['PluginURI'] ) && ! empty( $this->plugin['AuthorURI'] ) && $this->plugin['PluginURI'] == $this->plugin['AuthorURI'] ) {
			$error = __( 'The plugin has duplicate plugin and author URLs.', 'wporg-plugins' );
			
			return $error . ' ' . __( 'A plugin URL is a page/site that provides details about this specific plugin. An author URL is a page/site that provides information about the author of the plugin. You are not required to provide both, so pick the one that best applies to your URL.', 'wporg-plugins' );
		}

		$readme = $this->find_readme_file();
		if ( empty( $readme ) ) {
			$error = __( 'The plugin is missing a readme.', 'wporg-plugins' );
			
			/* translators: 1: readme.txt, 2: readme.md */
			return $error . ' ' . sprintf( __( 'The zip file must include a file named %1$s or %2$s.', 'wporg-plugins' ),
				'<code>readme.txt</code>',
				'<code>readme.md</code>'
			);
		}
		$readme = new Parser( $readme );

		// Pass it through Plugin Check and see how great this plugin really is.
		// We're not actually using this right now
		$result = $this->check_plugin();

		if ( ! $result ) {
			$error = __( 'The plugin has failed the automated checks.', 'wporg-plugins' );
			
			/* translators: 1: Plugin Check Plugin URL, 2: make.wordpress.org/plugins */
			return $error . ' ' . sprintf( __( 'Please correct the problems with it and upload it again. You can also use the <a href="%1$s">Plugin Check Plugin</a> to test your plugin before uploading. If you have any questions about this please post them to %2$s.', 'wporg-plugins' ),
				'//wordpress.org/plugins/plugin-check/',
				'<a href="https://make.wordpress.org/plugins">https://make.wordpress.org/plugins</a>'
			);
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
			//	'tax_input'    => wp_unslash( $_POST['tax_input'] ), // for category selection
				'meta_input'   => array(
					'tested'                   => $readme->tested,
					'requires'                 => $readme->requires,
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

		/* translators: 1: plugin name, 2: plugin slug, 3: plugins@wordpress.org */
		$message = sprintf( __( 'Thank you for uploading %1$s to the WordPress Plugin Directory. It has been given the initial plugin slug of %2$s, however that is subject to change based on the results of your code review.' ),
			esc_html( $this->plugin['Name'] ),
			'<code>' . $this->plugin_slug . '</code>'
		) . '</p><p>';

		/* translators: 1: plugins@wordpress.org */
		$message .= sprintf( __( 'We&rsquo;ve sent you an email verifying this submission. Please make sure to whitelist our email address - <a href="mailto:%1$s">%1$s</a> - to ensure you receive all our communications.' ),
			'plugins@wordpress.org'
		) . '</p><p>';

		$message .= __( 'If there is an error in your submission, such as you require a specific plugin slug or need a different user account to own the plugin, please email us as we can correct many issues before approval.', 'wporg-plugins' );

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
		);

		return in_array( $this->plugin_slug, $reserved_slugs );
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

		// Prioritize readme.txt
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
		// pass $this->plugin_root as the plugin root
		$result = true;

		// Display the errors.
		if ( $result ) {
			$verdict = array( 'pc-pass', __( 'Pass', 'wporg-plugins' ) );
		} else {
			$verdict = array( 'pc-fail', __( 'Fail', 'wporg-plugins' ) );
		}

		echo '<h4>' . sprintf( __( 'Results of Automated Plugin Scanning: %s', 'wporg-plugins' ), vsprintf( '<span class="%1$s">%2$s</span>', $verdict ) ) . '</h4>';
		echo '<ul class="tc-result">' . 'Result' . '</ul>';
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
		$email_subject = sprintf( __( '[WordPress Plugin Directory] New Plugin - %s', 'wporg-plugins' ),
			$this->plugin['Name']
		);

		/* translators: 1: plugin name, 2: plugin slug */
		$email_content = sprintf( __( 'Thank you for uploading %1$s to the WordPress Plugin Directory. We will review your submission as soon as possible and send you a follow up email with the results.

Your plugin has been given the initial slug of %2$s, however this is subject to change based on the results of your review.

If there is a problem with this submission, such as an incorrect display name or slug, please reply to this email and let us know. In most cases, we can correct errors as long as the plugin has not yet been approved. Please do not submit your plugin multiple times in an attempt to correct the issue, just email us.

--
The WordPress Plugin Directory Team
https://make.wordpress.org/plugins', 'wporg-plugins' ),
			$this->plugin['Name'],
			$this->plugin_slug
		);

		$user_email = wp_get_current_user()->user_email;

		wp_mail( $user_email, $email_subject, $email_content, 'From: plugins@wordpress.org' );
	}

	// Helper

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
