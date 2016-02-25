<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

/**
 * The [wporg-plugin-upload] shortcode handler to display a plugin uploader.
 *
 * @package WordPressdotorg\Plugin_Directory\Shortcodes
 */
class Upload_Handler {

	/**
	 * Path to `rm` script.
	 *
	 * @var string
	 */
	const RM = '/bin/rm';

	/**
	 * Path to `unzip` script.
	 *
	 * @var string
	 */
	const UNZIP = '/usr/bin/unzip';

	/**
	 * Path to temporary directory.
	 *
	 * @var string
	 */
	protected $tmp_dir;

	/**
	 * Path to temporary plugin folder.
	 *
	 * @var string
	 */
	protected $plugin_dir;

	/**
	 * The uploaded plugin.
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
	 * The plugin post if it already exists in the repository.
	 *
	 * @var \WP_Post
	 */
	protected $plugin_post;

	/**
	 * The plugin author (current user).
	 *
	 * @var \WP_User
	 */
	protected $author;

	/**
	 * Get set up to run tests on the uploaded plugin.
	 */
	public function __construct() {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );

		$this->create_tmp_dirs();
		$this->unwrap_package();

		add_filter( 'extra_plugin_headers', array( $this, 'extra_plugin_headers' ) );
	}

	/**
	 * Processes the plugin upload.
	 *
	 * Runs various tests and creates plugin post.
	 *
	 * @return string Failure or success message.
	 */
	public function process_upload() {
		$plugin_files = $this->get_all_files( $this->plugin_dir );

		// First things first. Do we have something to work with?
		if ( empty( $plugin_files ) ) {
			return __( 'The zip file was empty.', 'wporg-plugins' );
		}

		foreach ( $plugin_files as $plugin_file ) {
			if ( ! is_readable( $plugin_file ) ) {
				continue;
			}

			$plugin_data = get_plugin_data( $plugin_file, false, false ); // No markup/translation needed.
			if ( ! empty( $plugin_data['Name'] ) ) {
				$this->plugin = $plugin_data;
				break;
			}
		}

		// Let's check some plugin headers, shall we?

		if ( ! $this->plugin['Name'] ) {
			$error = __( 'The plugin has no name.', 'wporg-plugins' ) . ' ';

			/* translators: 1: comment header line, 2: Codex URL */
			$error .= sprintf( __( 'Add a %1$s line to your main plugin file and upload the plugin again. <a href="%2$s">Plugin Headers</a>', 'wporg-plugins' ),
				'<code>Plugin Name:</code>',
				__( 'https://codex.wordpress.org/File_Header', 'wporg-plugins' )
			);

			return $error;
		}

		// Determine the plugin slug based on the name of the plugin in the main plugin file.
		$this->plugin_slug = sanitize_title_with_dashes( $this->plugin['Name'] );
		$this->author      = wp_get_current_user();

		// Make sure it doesn't use a slug deemed not to be used by the public.
		if ( $this->has_reserved_slug() ) {
			/* translators: 1: plugin slug, 2: style.css */
			return sprintf( __( 'Sorry, the plugin name %1$s is reserved for use by WordPress Core. Please change the name of your plugin and upload it again.', 'wporg-plugins' ),
				'<code>' . $this->plugin_slug . '</code>'
			);
		}

		// Populate the plugin post and author.
		$this->plugin_post = $this->get_plugin_post();

		// Is there already a plugin with the name name?
		if ( ! empty( $this->plugin_post ) ) {
			/* translators: 1: plugin slug, 2: style.css */
			return sprintf( __( 'There is already a plugin called %1$s by a different author. Please change the name of your plugin and upload it again.', 'wporg-plugins' ),
				'<code>' . $this->plugin_slug . '</code>'
			);
		}

		$plugin_description = $this->strip_non_utf8( (string) $this->plugin['Description'] );
		if ( empty( $plugin_description ) ) {
			$error = __( 'The plugin has no description.', 'wporg-plugins' ) . ' ';

			/* translators: 1: comment header line, 2: style.css, 3: Codex URL */
			$error .= sprintf( __( 'Add a %1$s line to your main plugin file and upload the plugin again. <a href="%3$s">Plugin Headers</a>', 'wporg-plugins' ),
				'<code>Description:</code>',
				__( 'https://codex.wordpress.org/File_Header', 'wporg-plugins' )
			);

			return $error;
		}

		if ( ! $this->plugin['Version'] ) {
			$error = __( 'The plugin has no version.', 'wporg-plugins' ) . ' ';

			/* translators: 1: comment header line, 2: style.css, 3: Codex URL */
			$error .= sprintf( __( 'Add a %1$s line to your main plugin file and upload the plugin again. <a href="%3$s">Plugin Headers</a>', 'wporg-plugins' ),
				'<code>Version:</code>',
				__( 'https://codex.wordpress.org/File_Header', 'wporg-plugins' )
			);

			return $error;
		}

		if ( preg_match( '|[^\d\.]|', $this->plugin['Version'] ) ) {
			/* translators: %s: style.css */
			return sprintf( __( 'Version strings can only contain numeric and period characters (like 1.2). Please fix your %s line in your main plugin file and upload the plugin again.', 'wporg-plugins' ),
				'<code>Version:</code>'
			);
		}

		// Prevent duplicate URLs.
		$plugin_uri = $this->plugin['PluginURI'];
		$author_uri = $this->plugin['AuthorURI'];
		if ( ! empty( $plugin_uri ) && ! empty( $author_uri ) && $plugin_uri == $author_uri ) {
			return __( 'Duplicate plugin and author URLs. A plugin URL is a page/site that provides details about this specific plugin. An author URL is a page/site that provides information about the author of the plugin. You aren&rsquo;t required to provide both, so pick the one that best applies to your URL.', 'wporg-plugins' );
		}

		// Don't send special plugins through Plugin Check.
		if ( ! has_category( 'special-case-plugin', $this->plugin_post ) ) {
			// Pass it through Plugin Check and see how great this plugin really is.
			$result = $this->check_plugin( $plugin_files );

			if ( ! $result ) {
				/* translators: 1: Plugin Check Plugin URL, 2: make.wordpress.org/plugins */
				return sprintf( __( 'Your plugin has failed the plugin check. Please correct the problems with it and upload it again. You can also use the <a href="%1$s">Plugin Check Plugin</a> to test your plugin before uploading. If you have any questions about this please post them to %2$s.', 'wporg-plugins' ),
					'//wordpress.org/plugins/plugin-check/',
					'<a href="https://make.wordpress.org/plugins">https://make.wordpress.org/plugins</a>'
				);
			}
		}

		// Passed all tests!
		// Let's save everything and get things wrapped up.

		// Add a Plugin Directory entry for this plugin.
		$post_id = $this->create_plugin_post();

		$attachment = $this->save_zip_file( $post_id );
		if ( is_wp_error( $attachment ) ) {
			return $attachment->get_error_message();
		}

		// Send plugin author an email for peace of mind.
		$this->send_email_notification();

		do_action( 'plugin_upload', $this->plugin, $this->plugin_post );

		// Success!
		/* translators: 1: plugin name */

		return sprintf( __( 'Thank you for uploading %1$s to the WordPress Plugin Directory. We&rsquo;ve sent you an email verifying that we&rsquo;ve received it.', 'wporg-plugins' ),
			$this->plugin['Name']
		);
	}

	/**
	 * Creates a temporary directory, and the plugin dir within it.
	 */
	public function create_tmp_dirs() {
		// Create a temporary directory if it doesn't exist yet.
		$tmp = '/tmp/wporg-plugin-upload';
		if ( ! is_dir( $tmp ) ) {
			mkdir( $tmp, 0777 );
		}

		// Create file with unique file name.
		$this->tmp_dir = tempnam( $tmp, 'WPORG_PLUGIN_' );

		// Remove that file.
		unlink( $this->tmp_dir );

		// Create a directory with that unique name.
		mkdir( $this->tmp_dir, 0777 );

		// Get a sanitized name for that plugin and create a directory for it.
		$base_name        = $this->get_sanitized_zip_name();
		$this->plugin_dir = "{$this->tmp_dir}/{$base_name}";
		mkdir( $this->plugin_dir, 0777 );

		// Make sure we clean up after ourselves.
		add_action( 'shutdown', array( $this, 'remove_files' ) );
	}

	/**
	 * Unzips the uploaded plugin and saves it in the temporary plugin dir.
	 */
	public function unwrap_package() {
		$unzip      = escapeshellarg( self::UNZIP );
		$zip_file   = escapeshellarg( $_FILES['zip_file']['tmp_name'] );
		$plugin_dir = escapeshellarg( $this->plugin_dir );

		// Unzip it into the plugin directory.
		exec( escapeshellcmd( "{$unzip} -DD {$zip_file} -d {$plugin_dir}" ) );

		// Fix any permissions issues with the files. Sets 755 on directories, 644 on files.
		exec( escapeshellcmd( "chmod -R 755 {$plugin_dir}" ) );
		exec( escapeshellcmd( "find {$plugin_dir} -type f -print0" ) . ' | xargs -I% -0 chmod 644 %' );
	}

	/**
	 * Adds plugin headers that are expected in the directory.
	 *
	 * @param array $headers Additional plugin headers. Default empty array.
	 * @return array
	 */
	public function extra_plugin_headers( $headers ) {
		$headers['Tags'] = 'Tags';

		return $headers;
	}

	/**
	 * Returns the the plugin post if it already exists in the repository.
	 *
	 * @return \WP_Post|null
	 */
	public function get_plugin_post() {
		$plugins = get_posts( array(
			'name'             => $this->plugin_slug,
			'posts_per_page'   => 1,
			'post_type'        => 'plugin',
			'orderby'          => 'ID',
			/*
			 * Specify post stati so this query returns a result for draft plugins, even
			 * if the uploading user doesn't have have the permission to view drafts.
			 */
			'post_status'      => array( 'publish', 'pending', 'draft', 'future', 'trash', 'suspend' ),
			'suppress_filters' => false,
		) );

		return current( $plugins );
	}

	/**
	 * Whether the uploaded plugin uses a reserved slug.
	 *
	 * Passes if the author happens to be `wordpressdotorg`.
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
		);

		return in_array( $this->plugin_slug, $reserved_slugs ) && 'wordpressdotorg' !== $this->author->user_login;
	}

	/**
	 * Sends a plugin through Plugin Check.
	 *
	 * @param array $files All plugin files to check.
	 * @return bool Whether the plugin passed the checks.
	 */
	public function check_plugin( $files ) {

		// Run the checks.
		// @todo Include plugin checker.
		$result = true;

		// Display the errors.
		$verdict = $result ? array( 'tc-pass', __( 'Pass', 'wporg-plugins' ) ) : array(
			'tc-fail',
			__( 'Fail', 'wporg-plugins' )
		);
		echo '<h4>' . sprintf( __( 'Results of Automated Plugin Scanning: %s', 'wporg-plugins' ), vsprintf( '<span class="%1$s">%2$s</span>', $verdict ) ) . '</h4>';
		echo '<ul class="tc-result">' . 'Result' . '</ul>';
		echo '<div class="notice notice-info"><p>' . __( 'Note: While the automated plugin scan is based on the Plugin Review Guidelines, it is not a complete review. A successful result from the scan does not guarantee that the plugin will pass review. All submitted plugins are reviewed manually before approval.', 'wporg-plugins' ) . '</p></div>';

		return $result;
	}

	/**
	 * Creates a plugin post.
	 *
	 * @return int|\WP_Error The post ID on success. The value 0 or WP_Error on failure.
	 */
	public function create_plugin_post() {
		$upload_date = current_time( 'mysql' );

		return wp_insert_post( array(
			'post_author'    => $this->author->ID,
			'post_title'     => $this->plugin['Name'],
			'post_name'      => $this->plugin_slug,
			'post_excerpt'   => $this->plugin['Description'],
			'post_date'      => $upload_date,
			'post_date_gmt'  => $upload_date,
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'post_status'    => 'pending',
			'post_type'      => 'plugin',
			'tags_input'     => $this->plugin['Tags'],
		) );
	}

	/**
	 * Saves zip file and attaches it to the plugin post.
	 *
	 * @param int $post_id Post ID.
	 * @return int|\WP_Error Attachment ID or upload error.
	 */
	public function save_zip_file( $post_id ) {
		$_FILES['zip_file']['name'] = wp_generate_password( 12 ) . '-' . $_FILES['zip_file']['name'];

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
		$email_subject = sprintf( __( '[WordPress Plugins] New Plugin - %s', 'wporg-plugins' ),
			$this->plugin['Name']
		);

		/* translators: 1: plugin name, 2: Trac ticket URL */
		$email_content = sprintf( __( 'Thank you for uploading %1$s to the WordPress Plugin Directory. If your plugin is selected to be part of the directory we\'ll send a follow up email.

--
The WordPress.org Plugins Team
https://make.wordpress.org/plugins', 'wporg-plugins' ),
			$this->plugin['Name']
		);

		wp_mail( $this->author->user_email, $email_subject, $email_content, 'From: plugins@wordpress.org' );
	}

	// Helper

	/**
	 * Returns a sanitized version of the uploaded zip file name.
	 *
	 * @return string
	 */
	public function get_sanitized_zip_name() {
		return preg_replace( '|\W|', '', strtolower( basename( $_FILES['zip_file']['name'], '.zip' ) ) );
	}

	/**
	 * Returns all (usable) files of a given directory.
	 *
	 * @param string $dir Path to directory to search.
	 *
	 * @return array All files within the passed directory.
	 */
	public function get_all_files( $dir ) {
		$files        = array();
		$dir_iterator = new \RecursiveDirectoryIterator( $dir );
		$iterator     = new \RecursiveIteratorIterator( $dir_iterator, \RecursiveIteratorIterator::SELF_FIRST );

		foreach ( $iterator as $file ) {
			// Only return files that are no directory references or Mac resource forks.
			if ( $file->isFile() && ! in_array( $file->getBasename(), array(
					'..',
					'.'
				) ) && ! stristr( $file->getPathname(), '__MACOSX' )
			) {
				array_push( $files, $file->getPathname() );
			}
		}

		return $files;
	}

	/**
	 * Whitelist zip files to be allowed to be uploaded to the media library.
	 *
	 * @param string $site_exts Whitelisted file extentions.
	 *
	 * @return string Whitelisted file extentions.
	 */
	public function whitelist_zip_files( $site_exts ) {
		$file_extenstions   = explode( ' ', $site_exts );
		$file_extenstions[] = 'zip';

		return implode( ' ', array_unique( $file_extenstions ) );
	}

	/**
	 * Deletes the temporary directory.
	 */
	public function remove_files() {
		$rm    = escapeshellarg( self::RM );
		$files = escapeshellarg( $this->tmp_dir );

		exec( escapeshellcmd( "{$rm} -rf {$files}" ) );
	}

	/**
	 * Strips invalid UTF-8 characters.
	 *
	 * Non-UTF-8 characters in plugin descriptions will causes blank descriptions in plugins.trac.
	 *
	 * @param string $string The string to be converted.
	 *
	 * @return string The converted string.
	 */
	protected function strip_non_utf8( $string ) {
		ini_set( 'mbstring.substitute_character', 'none' );

		return mb_convert_encoding( $string, 'UTF-8', 'UTF-8' );
	}
}
