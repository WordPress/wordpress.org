<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;
use WordPressdotorg\Plugin_Directory\Readme_Parser;
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

			/* translators: 1: comment header line, 2: Codex URL */
			return $error . ' ' . sprintf( __( 'Add a %1$s line to your main plugin file and upload the plugin again. <a href="%2$s">Plugin Headers</a>', 'wporg-plugins' ),
				'<code>Plugin Name:</code>',
				__( 'https://codex.wordpress.org/File_Header', 'wporg-plugins' )
			);
		}

		// Determine the plugin slug based on the name of the plugin in the main plugin file.
		$this->plugin_slug = sanitize_title_with_dashes( $this->plugin['Name'] );

		// Make sure it doesn't use a slug deemed not to be used by the public.
		if ( $this->has_reserved_slug() ) {
			/* translators: 1: plugin slug, 2: style.css */
			return sprintf( __( 'Sorry, the plugin name %1$s is reserved for use by WordPress. Please change the name of your plugin and upload it again.', 'wporg-plugins' ),
				'<code>' . $this->plugin_slug . '</code>'
			);
		}

		$plugin_post = Plugin_Directory::get_plugin_post( $this->plugin_slug );

		// Is there already a plugin by a different author?
		if ( $plugin_post instanceof \WP_Post && $plugin_post->post_author != get_current_user_id() ) {
			/* translators: 1: plugin slug, 2: style.css */
			return sprintf( __( 'There is already a plugin called %1$s by a different author. Please change the name of your plugin in the plugin header file and upload it again.', 'wporg-plugins' ),
				'<code>' . $this->plugin_slug . '</code>'
			);
		}

		if ( ! $this->plugin['Description'] ) {
			$error = __( 'The plugin has no description.', 'wporg-plugins' );

			/* translators: 1: comment header line, 2: style.css, 3: Codex URL */
			return $error . ' ' . sprintf( __( 'Add a %1$s line to your main plugin file and upload the plugin again. <a href="%3$s">Plugin Headers</a>', 'wporg-plugins' ),
				'<code>Description:</code>',
				__( 'https://codex.wordpress.org/File_Header', 'wporg-plugins' )
			);
		}

		if ( ! $this->plugin['Version'] ) {
			$error = __( 'The plugin has no version.', 'wporg-plugins' );

			/* translators: 1: comment header line, 2: style.css, 3: Codex URL */
			return $error . ' ' . sprintf( __( 'Add a %1$s line to your main plugin file and upload the plugin again. <a href="%3$s">Plugin Headers</a>', 'wporg-plugins' ),
				'<code>Version:</code>',
				__( 'https://codex.wordpress.org/File_Header', 'wporg-plugins' )
			);
		}

		if ( preg_match( '|[^\d\.]|', $this->plugin['Version'] ) ) {
			/* translators: %s: Version header */
			return sprintf( __( 'Version strings can only contain numeric and period characters (like 1.2). Please fix your %s line in your main plugin file and upload the plugin again.', 'wporg-plugins' ),
				'<code>Version:</code>'
			);
		}

		// Prevent duplicate URLs.
		if ( ! empty( $this->plugin['PluginURI'] ) && ! empty( $this->plugin['AuthorURI'] ) && $this->plugin['PluginURI'] == $this->plugin['AuthorURI'] ) {
			return __( 'Duplicate plugin and author URLs. A plugin URL is a page/site that provides details about this specific plugin. An author URL is a page/site that provides information about the author of the plugin. You aren&rsquo;t required to provide both, so pick the one that best applies to your URL.', 'wporg-plugins' );
		}

		$readme = $this->find_readme_file();
		if ( empty( $readme ) ) {
			/* translators: 1: readme.txt, 2: readme.md */
			return sprintf( __( 'The zip file must include a file named %1$s or %2$s.', 'wporg-plugins' ),
				'<code>readme.txt</code>',
				'<code>readme.md</code>'
			);
		}
		$readme = new Readme_Parser( $readme );

		// Pass it through Plugin Check and see how great this plugin really is.
		$result = $this->check_plugin();

		if ( ! $result ) {
			/* translators: 1: Plugin Check Plugin URL, 2: make.wordpress.org/plugins */
			return sprintf( __( 'Your plugin has failed the plugin check. Please correct the problems with it and upload it again. You can also use the <a href="%1$s">Plugin Check Plugin</a> to test your plugin before uploading. If you have any questions about this please post them to %2$s.', 'wporg-plugins' ),
				'//wordpress.org/plugins/plugin-check/',
				'<a href="https://make.wordpress.org/plugins">https://make.wordpress.org/plugins</a>'
			);
		}

		// Passed all tests!
		// Let's save everything and get things wrapped up.

		// Give the author wp-admin access if they don't have it yet.
		// @todo: Enable.
		if ( ! current_user_can( 'plugin_dashboard_access' ) ) {
		//	wp_get_current_user()->add_role( 'plugin_committer' );
		}

		// Create a new post on first-time submissions.
		if ( ! ( $plugin_post instanceof \WP_Post ) ) {
			$content = '';
			foreach ( $readme->sections as $section => $section_content ) {
				$content .= "\n\n<!--section={$section}-->\n{$section_content}";
			}

			// Add a Plugin Directory entry for this plugin.
			$plugin_post = Plugin_Directory::create_plugin_post( array(
				'post_title'   => $this->plugin['Name'],
				'post_name'    => $this->plugin_slug,
				'post_content' => $content,
				'post_excerpt' => $this->plugin['Description'],
				'tax_input'    => wp_unslash( $_POST['tax_input'] ),
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
					'_author_ip'               => preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] ),
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

		// Success!
		/* translators: 1: plugin name */
		return sprintf( __( 'Thank you for uploading %1$s to the WordPress Plugin Directory. We&rsquo;ve sent you an email verifying that we&rsquo;ve received it.', 'wporg-plugins' ),
			esc_html( $this->plugin['Name'] )
		);
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

		/* translators: 1: plugin name, 2: Trac ticket URL */
		$email_content = sprintf( __( 'Thank you for uploading %1$s to the WordPress Plugin Directory. If your plugin is selected to be part of the directory we\'ll send a follow up email.

--
The WordPress Plugin Directory Team
https://make.wordpress.org/plugins', 'wporg-plugins' ),
			$this->plugin['Name']
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
