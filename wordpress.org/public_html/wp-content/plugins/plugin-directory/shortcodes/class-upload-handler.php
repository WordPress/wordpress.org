<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use WP_Error;
use WordPressdotorg\Plugin_Directory\CLI\Import;
use WordPressdotorg\Plugin_Directory\Readme\Parser;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use WordPressdotorg\Plugin_Directory\Admin\Tools\Upload_Token;
use WordPressdotorg\Plugin_Directory\Clients\HelpScout;
use WordPressdotorg\Plugin_Directory\Email\Plugin_Submission as Plugin_Submission_Email;

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
	public $plugin_dir;

	/**
	 * Path to the detected plugins files.
	 *
	 * @var string
	 */
	public $plugin_root;

	/**
	 * The uploaded plugin headers.
	 *
	 * @var array
	 */
	public $plugin;

	/**
	 * The plugin slug.
	 *
	 * @var string
	 */
	public $plugin_slug;

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
	 * @param int $for_plugin Optional. The plugin being uploaded to. This is used when adding additional .zip files.
	 *
	 * @return string|WP_Error Confirmation message on success, WP_Error object on failure.
	 */
	public function process_upload( $for_plugin = 0 ) {
		if ( UPLOAD_ERR_OK !== $_FILES['zip_file']['error'] ) {
			return new WP_Error( 'error_upload', __( 'Error in file upload.', 'wporg-plugins' ) );
		}

		// Validate the maximum upload size.
		if ( $_FILES['zip_file']['size'] > wp_max_upload_size() ) {
			return new WP_Error( 'error_upload', __( 'Error in file upload.', 'wporg-plugins' ) );
		}

		$zip_file         = $_FILES['zip_file']['tmp_name'];
		$has_upload_token = $this->has_valid_upload_token();
		$this->plugin_dir = Filesystem::unzip( $zip_file );

		$plugin_post       = $for_plugin ? get_post( $for_plugin ) : false;
		$updating_existing = (bool) $plugin_post;
		$this->plugin_slug = $plugin_post->post_name ?? '';

		if ( $for_plugin && ! $updating_existing ) {
			return new WP_Error( 'error_upload', __( 'Error in file upload.', 'wporg-plugins' ) );
		}

		// Allow plugin reviewers to bypass some restrictions.
		if ( $updating_existing && current_user_can( 'approve_plugins' ) && ! $has_upload_token ) {
			$has_upload_token = true;
		}

		// If the plugin was uploaded using a token, we'll assume future uploads for the plugin should use one.
		if ( $updating_existing && ! $has_upload_token && $plugin_post->{'_used_upload_token'} ) {
			$has_upload_token = true;
		}

		$plugin_data = (array) Import::find_plugin_headers( $this->plugin_dir, 1 /* Max Depth to search */ );
		if ( ! empty( $plugin_data['Name'] ) ) {
			$this->plugin      = $plugin_data;
			$this->plugin_root = dirname( $plugin_data['PluginFile'] );
		}

		/*
		 * Validate the contents of the ZIP seems reasonable.
		 *
		 * We don't want Version Control direcories, or compressed/executable files.
		 */
		$unexpected_files = array_merge(
			Filesystem::list( $this->plugin_dir, 'directories', true, '!/\.(git|svn|hg|bzr)$!i' ),
			Filesystem::list( $this->plugin_dir, 'files', true, '!\.(phar|sh|zip|gz|tgz|rar|tar|7z)$!i' )
		);

		if ( $unexpected_files ) {
			$unexpected_files = array_map( 'basename', $unexpected_files );
			$unexpected_files = array_map( 'esc_html', $unexpected_files );

			$error = __( 'Error: The plugin contains unexpected files.', 'wporg-plugins' );
			return new WP_Error( 'unexpected_files', $error . ' ' . sprintf(
				/* translators: %s: Filenames */
				__( 'The following files are not permitted in plugins: %s. Please remove them and upload the plugin again.', 'wporg-plugins' ),
				'<code>' . implode( '</code>, <code>', $unexpected_files ) . '</code>'
			) );
		}

		// Let's check some plugin headers, shall we?
		// Catches both empty Plugin Name & when no valid files could be found.
		if ( empty( $this->plugin['Name'] ) ) {
			$error = __( 'Error: The plugin has no name.', 'wporg-plugins' );

			return new WP_Error( 'no_name', $error . ' ' . sprintf(
				/* translators: 1: plugin header line, 2: Documentation URL */
				__( 'Add a %1$s line to your main plugin file and upload the plugin again. For more information, please review our documentation on <a href="%2$s">Plugin Headers</a>.', 'wporg-plugins' ),
				'<code>Plugin Name:</code>',
				__( 'https://developer.wordpress.org/plugins/plugin-basics/header-requirements/', 'wporg-plugins' )
			) );
		}

		// Determine the plugin slug based on the name of the plugin in the main plugin file.
		if ( ! $this->plugin_slug ) {
			$this->plugin_slug = $this->generate_plugin_slug( $this->plugin['Name'] );
		}

		if ( ! $this->plugin_slug ) {
			$error = __( 'Error: The plugin has an unsupported name.', 'wporg-plugins' );

			return new WP_Error( 'unsupported_name', $error . ' ' . sprintf(
				/* translators: %s: 'Plugin Name:' */
				__( 'Plugin names may only contain latin letters (A-z), numbers, spaces, and hyphens. Please change the %s line in your main plugin file and readme, then you may upload it again.', 'wporg-plugins' ),
				esc_html( $this->plugin['Name'] ),
				'<code>Plugin Name:</code>'
			) );
		}

		// Make sure it doesn't use a slug deemed not to be used by the public.
		if ( $this->has_reserved_slug() ) {
			$error = __( 'Error: The plugin has a reserved name.', 'wporg-plugins' );

			return new WP_Error( 'reserved_name', $error . ' ' . sprintf(
				/* translators: 1: plugin slug, 2: 'Plugin Name:' */
				__( 'Your chosen plugin name - %1$s - has been reserved or otherwise restricted from use entirely. Please change the %2$s line in your main plugin file and readme, then you may upload it again.', 'wporg-plugins' ),
				'<code>' . $this->plugin_slug . '</code>',
				'<code>Plugin Name:</code>'
			) );
		}

		// Make sure it doesn't use a TRADEMARK protected slug.
		if ( ! $updating_existing ) {
			$has_trademarked_slug = $this->has_trademarked_slug( $this->plugin_slug );
		} else {
			// If we're updating an existing plugin, we need to check the new name, but the slug may be different.
			$has_trademarked_slug = $this->has_trademarked_slug(
				$this->generate_plugin_slug( $this->plugin['Name'] )
			);
		}
		if ( false !== $has_trademarked_slug && ! $has_upload_token ) {
			$error = __( 'Error: The plugin name includes a restricted term.', 'wporg-plugins' );

			if ( $has_trademarked_slug === trim( $has_trademarked_slug, '-' ) ) {
				// Trademarks that do NOT end in "-" indicate slug cannot contain term at all.
				$message = sprintf(
					/* translators: 1: plugin slug, 2: trademarked term, 3: 'Plugin Name:', 4: plugin email address */
					__( 'Your chosen plugin name - %1$s - contains the restricted term "%2$s" and cannot be used at all in your plugin permalink nor the display name. To proceed with this submission you must remove "%2$s" from the %3$s line in both your main plugin file and readme entirely. Once you\'ve finished, you may upload the plugin again. Do not attempt to work around this by removing letters (i.e. WordPess) or using numbers (4 instead of A). Those are seen as intentional actions to avoid our restrictions, and are not permitted. If you feel this is in error, such as you legally own the trademark for a term, please email us at %4$s and explain your situation.', 'wporg-plugins' ),
					'<code>' . esc_html( $this->plugin['Name'] ) . '</code>',
					trim( $has_trademarked_slug, '-' ),
					'<code>Plugin Name:</code>',
					'<code>plugins@wordpress.org</code>'
				);
			} else {
				// Trademarks ending in "-" indicate slug cannot BEGIN with that term.
				$message = sprintf(
					/* translators: 1: plugin slug, 2: trademarked term, 3: 'Plugin Name:', 4: plugin email address */
					__( 'Your chosen plugin name - %1$s - contains the restricted term "%2$s" and cannot be used to begin your permalink or display name. We disallow the use of certain terms in ways that are abused, or potentially infringe on and/or are misleading with regards to trademarks. In order to proceed with this submission, you must change the %3$s line in your main plugin file and readme to end with  "-%2$s" instead. Once you\'ve finished, you may upload the plugin again. If you feel this is in error, such as you legally own the trademark for the term, please email us at %4$s and explain your situation.', 'wporg-plugins' ),
					'<code>' . esc_html( $this->plugin['Name'] ) . '</code>',
					trim( $has_trademarked_slug, '-' ),
					'<code>Plugin Name:</code>',
					'<code>plugins@wordpress.org</code>'
				);
			}

			return new WP_Error( 'trademarked_name', $error . ' ' . $message );
		}

		if ( ! $plugin_post ) {
			$plugin_post = Plugin_Directory::get_plugin_post( $this->plugin_slug );
		}

		// If no matching plugin by that slug, check to see if a plugin exists with that Title in the database.
		if ( ! $plugin_post ) {
			$plugin_posts = get_posts( array(
				'post_type'   => 'plugin',
				'title'       => $this->plugin['Name'],
				'post_status' => array( 'publish', 'pending', 'disabled', 'closed', 'new', 'draft', 'approved' ),
			) );

			if ( $plugin_posts ) {
				$plugin_post = array_shift( $plugin_posts );
			}
		}

		// Is there already a plugin with the same slug by a different author?
		if (
			( $plugin_post && $plugin_post->post_author != get_current_user_id() ) &&
			! current_user_can( 'edit_post', $plugin_post ) /* reviewer uploading via wp-admin */
		) {
			$error = __( 'Error: The plugin already exists.', 'wporg-plugins' );

			return new WP_Error( 'already_exists', $error . ' ' . sprintf(
				/* translators: 1: plugin slug, 2: 'Plugin Name:' */
				__( 'There is already a plugin with the name %1$s in the directory. You must rename your plugin by changing the %2$s line in your main plugin file and in your readme. Once you have done so, you may upload it again.', 'wporg-plugins' ),
				'<code>' . esc_html( $this->plugin['Name'] ) . '</code>',
				'<code>Plugin Name:</code>'
			) );
		}

		// Is there already a plugin with the same slug by the same author?
		if ( $plugin_post && ! $updating_existing ) {
			$error = __( 'Error: The plugin has already been submitted.', 'wporg-plugins' );

			return new WP_Error( 'already_submitted', $error . ' ' . sprintf(
				/* translators: 1: plugin slug, 2: Documentation URL, 3: plugins@wordpress.org */
				__( 'You have already submitted a plugin named %1$s. There is no need to resubmit existing plugins, even for new versions. Instead, please update your plugin within the directory via <a href="%2$s">SVN</a>. If you need assistance, email <a href="mailto:%3$s">%3$s</a> and let us know.', 'wporg-plugins' ),
				'<code>' . esc_html( $this->plugin['Name'] ) . '</code>',
				__( 'https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/', 'wporg-plugins' ),
				'plugins@wordpress.org'
			) );
		}

		// Prevent short plugin names (they're generally SEO grabs).
		if ( strlen( $this->plugin_slug ) < 5 ) {
			$error = __( 'Error: The plugin slug is too short.', 'wporg-plugins' );

			return new WP_Error( 'trademarked_name', $error . ' ' . sprintf(
				/* translators: 1: plugin slug, 2: 'Plugin Name:' */
				__( 'Your chosen plugin name - %1$s - is not permitted because it is too short. Please change the %2$s line in your main plugin file and readme to a different name. When you have finished, you may upload your plugin again.', 'wporg-plugins' ),
				'<code>' . $this->plugin_slug . '</code>',
				'<code>Plugin Name:</code>'
			) );
		}

		// Plugins need descriptions.
		if ( ! $this->plugin['Description'] ) {
			$error = __( 'Error: The plugin has no description.', 'wporg-plugins' );

			return new WP_Error( 'no_description', $error . ' ' . sprintf(
				/* translators: 1: plugin header line, 2: Documentation URL */
				__( 'We cannot find a description in your plugin headers. Please add a %1$s line to your main plugin file and upload the complete plugin again. If you need more information, please review our documentation on <a href="%2$s">Plugin Headers</a>.', 'wporg-plugins' ),
				'<code>Description:</code>',
				__( 'https://developer.wordpress.org/plugins/the-basics/header-requirements/', 'wporg-plugins' )
			) );
		}

		// Plugins need versions.
		if ( ! $this->plugin['Version'] ) {
			$error = __( 'Error: The plugin has no version.', 'wporg-plugins' );

			return new WP_Error( 'no_version', $error . ' ' . sprintf(
				/* translators: 1: plugin header line, 2: Documentation URL */
				__( 'We cannot find a version listed in your plugin headers. Please add a %1$s line to your main plugin file and upload the complete plugin again. If you need more information, please review our documentation on <a href="%2$s">Plugin Headers</a>.', 'wporg-plugins' ),
				'<code>Version:</code>',
				__( 'https://developer.wordpress.org/plugins/the-basics/header-requirements/', 'wporg-plugins' )
			) );
		}

		// Versions should be NUMBERS.
		if ( preg_match( '|[^\d\.]|', $this->plugin['Version'] ) ) {
			$error = __( 'Error: Plugin versions are expected to be numbers.', 'wporg-plugins' );

			return new WP_Error( 'invalid_version', $error . ' ' . sprintf(
				/* translators: %s: 'Version:' */
				__( 'Version strings may only contain numeric and period characters (i.e. 1.2). Please correct the %s line in your main plugin file and upload the plugin again.', 'wporg-plugins' ),
				'<code>Version:</code>'
			) );
		}

		// Prevent duplicate URLs.
		// This is part of how the API looks for updates, so having them different helps prevent conflicts.
		if (
			! empty( $this->plugin['PluginURI'] ) &&
			! empty( $this->plugin['AuthorURI'] ) &&
			$this->plugin['PluginURI'] == $this->plugin['AuthorURI']
		) {
			$error = __( 'Error: Your plugin and author URIs are the same.', 'wporg-plugins' );

			return new WP_Error(
				'plugin_author_uri', $error . ' ' .
				__( 'Your plugin headers in the main plugin file headers have the same value for both the plugin and author URI (Uniform Resource Identifier). A plugin URI is a webpage that provides details about this specific plugin. An author URI is a webpage that provides information about the author of the plugin. Those two must be different. You are not required to provide both, so pick the one that best applies to your situation.', 'wporg-plugins' )
			);
		}

		// Prevent uploads using popular Plugin names in the wild.
		if ( function_exists( 'wporg_stats_get_plugin_name_install_count' ) && ! $has_upload_token && ! $updating_existing ) {
			$installs = wporg_stats_get_plugin_name_install_count( $this->plugin['Name'] );

			if ( $installs && $installs->count >= 100 ) {
				$error = __( 'Error: That plugin name is already in use.', 'wporg-plugins' );

				return new WP_Error( 'already_exists_in_the_wild', $error . ' ' . sprintf(
					/* translators: 1: plugin slug, 2: 'Plugin Name:' */
					__( 'There is already a plugin with the name %1$s known to exist, though it is not hosted on WordPress.org. This means the permalink %2$s is already in use, and has a significant user base. Were we to accept it as-is, our system would overwrite those other installs and potentially damage any existing users. This is especially true since WordPress 5.5 and up will automatically update plugins and themes. You must rename your plugin by changing the %3$s line in your main plugin file and in your readme. Once you have done so, you may upload it again. If you feel this is an incorrect assessment of the situation, please email <a href="mailto:%4$s">%4$s</a> and explain why so that we may help you.', 'wporg-plugins' ),
					'<code>' . esc_html( $this->plugin['Name'] ) . '</code>',
					'<code>' . $this->plugin_slug . '</code>',
					'<code>Plugin Name:</code>',
					'plugins@wordpress.org'
				) );
			}
		}

		// Check for a valid readme.
		$readme = Import::find_readme_file( $this->plugin_root );
		if ( empty( $readme ) ) {
			$error = __( 'Error: The plugin has no readme.', 'wporg-plugins' );

			return new WP_Error( 'no_readme', $error . ' ' . sprintf(
				/* translators: 1: readme.txt, 2: readme.md */
				__( 'The zip file must include a file named %1$s or %2$s. We recommend using %1$s as it will allow you to fully utilize our directory.', 'wporg-plugins' ),
				'<code>readme.txt</code>',
				'<code>readme.md</code>'
			) );
		}
		$readme = new Parser( $readme );

		// Double check no existing plugins clash with the readme title.
		$readme_plugin_post = get_posts( array(
			'post_type'    => 'plugin',
			'title'        => $readme->name,
			'post_status'  => array( 'publish', 'pending', 'disabled', 'closed', 'new', 'draft', 'approved' ),
			'post__not_in' => $plugin_post ? array( $plugin_post->ID ) : [],
		) );
		if ( $readme_plugin_post && trim( $readme->name ) ) {
			$error = __( 'README Error: The plugin has already been submitted.', 'wporg-plugins' );

			if ( $readme_plugin_post->post_author != get_current_user_id() ) {
				return new WP_Error( 'already_submitted', $error . ' ' . sprintf(
					/* translators: 1: plugin slug, 2: 'Plugin Name:' */
					__( 'There is already a plugin with the name %1$s in the directory. You must rename your plugin by changing the %2$s line in your main plugin file and in your readme. Once you have done so, you may upload it again.', 'wporg-plugins' ),
					'<code>' . esc_html( $readme->name ) . '</code>',
					'<code>Plugin Name:</code>'
				) );
			}

			return new WP_Error( 'already_submitted', $error . ' ' . sprintf(
				/* translators: 1: plugin slug, 2: Documentation URL, 3: plugins@wordpress.org */
				__( 'You have already submitted a plugin named %1$s. There is no need to resubmit existing plugins, even for new versions. Instead, please update your plugin within the directory via <a href="%2$s">SVN</a>. If you need assistance, email <a href="mailto:%3$s">%3$s</a> and let us know.', 'wporg-plugins' ),
				'<code>' . esc_html( $readme->name ) . '</code>',
				__( 'https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/', 'wporg-plugins' ),
				'plugins@wordpress.org'
			) );
		}

		if ( function_exists( 'wporg_stats_get_plugin_name_install_count' ) && ! $has_upload_token && ! $updating_existing ) {
			$installs = wporg_stats_get_plugin_name_install_count( $readme->name );

			if ( $installs && $installs->count >= 100 ) {
				$error = __( 'Error: That plugin name is already in use.', 'wporg-plugins' );

				return new WP_Error( 'already_exists_in_the_wild', $error . ' ' . sprintf(
					/* translators: 1: plugin slug, 2: 'Plugin Name:' */
					__( 'There is already a plugin with the name %1$s known to exist, though it is not hosted on WordPress.org. This means the permalink %2$s is already in use, and has a significant user base. Were we to accept it as-is, our system would overwrite those other installs and potentially damage any existing users. This is especially true since WordPress 5.5 and up will automatically update plugins and themes. You must rename your plugin by changing the %3$s line in your main plugin file and in your readme. Once you have done so, you may upload it again. If you feel this is an incorrect assessment of the situation, please email <a href="mailto:%4$s">%4$s</a> and explain why so that we may help you.', 'wporg-plugins' ),
					'<code>' . esc_html( $readme->name ) . '</code>',
					'<code>' . $this->plugin_slug . '</code>',
					'<code>Plugin Name:</code>',
					'plugins@wordpress.org'
				) );
			}
		}

		// Check for a readme license.
		if ( empty( $readme->license ) ) {
			$error = __( 'Error: No license defined.', 'wporg-plugins' );

			return new WP_Error( 'no_license', $error . ' ' . sprintf(
				/* translators: 1: readme.txt */
				__( 'Your plugin has no license declared. Please update your %1$s with a GPLv2 (or later) compatible license.', 'wporg-plugins' ),
				'<code>readme.txt</code>'
			) );
		}

		// Pass it through Plugin Check and see how great this plugin really is.
		// We're not actually using this right now.
		$plugin_check_result = $this->check_plugin();

		if ( ! $plugin_check_result && ! $has_upload_token ) {
			$error = __( 'Error: The plugin has failed the automated checks.', 'wporg-plugins' );

			return new WP_Error( 'failed_checks', $error . ' ' . sprintf(
				/* translators: 1: Plugin Check Plugin URL, 2: https://make.wordpress.org/plugins */
				__( 'Please correct the listed problems with your plugin and upload it again. You can also use the <a href="%1$s">Plugin Check Plugin</a> to test your plugin before uploading. If you have any questions about this please post them to %2$s.', 'wporg-plugins' ),
				'https://wordpress.org/plugins/plugin-check/',
				'<a href="https://make.wordpress.org/plugins">https://make.wordpress.org/plugins</a>'
			) );
		}

		// Passed all tests!
		// Let's save everything and get things wrapped up.
		// Create a new post on first-time submissions.
		$content = '';
		foreach ( $readme->sections as $section => $section_content ) {
			$content .= "\n\n<!--section={$section}-->\n{$section_content}";
		}

		$post_args = array(
			'ID'            => $plugin_post->ID ?? 0,
			'post_title'    => $this->plugin['Name'],
			'post_name'     => $this->plugin_slug,
			'post_status'   => $plugin_post->post_status ?? 'new',
			'post_content'  => $content,
			'post_excerpt'  => $this->plugin['Description'],
			'post_date'     => $plugin_post->post_date ?? null,
			'post_date_gmt' => $plugin_post->post_date_gmt ?? null,
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
				'license'                  => $readme->license,
				'license_uri'              => $readme->license_uri,
				'sections'                 => array_keys( $readme->sections ),
				'version'                  => $this->plugin['Version'],
				'header_name'              => $this->plugin['Name'],
				'header_plugin_uri'        => $this->plugin['PluginURI'],
				'header_author'            => $this->plugin['Author'],
				'header_author_uri'        => $this->plugin['AuthorURI'],
				'header_textdomain'        => $this->plugin['TextDomain'],
				'header_description'       => $this->plugin['Description'],
				'requires_plugins'         => array_filter( array_map( 'trim', explode( ',', $this->plugin['RequiresPlugins'] ) ) ),
				'assets_screenshots'       => array(),
				'assets_icons'             => array(),
				'assets_banners'           => array(),
				'assets_banners_color'     => false,
				'support_threads'          => 0,
				'support_threads_resolved' => 0,
				'downloads'                => 0,
				'last_updated'             => gmdate( 'Y-m-d H:i:s' ),
				'rating'                   => 0,
				'ratings'                  => array(),
				'active_installs'          => 0,
				'_active_installs'         => 0,
				'usage'                    => array(),
			),
		);

		// First time submission, track some additional metadata.
		if ( ! $updating_existing ) {
			$post_args['meta_input']['_author_ip']        = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );
			$post_args['meta_input']['_submitted_date']   = time();
			$post_args['meta_input']['_used_upload_token'] = $has_upload_token;
		}

		// Add/Update the Plugin Directory entry for this plugin.
		$plugin_post = Plugin_Directory::create_plugin_post( $post_args );

		if ( is_wp_error( $plugin_post ) ) {
			return $plugin_post;
		}

		// Record the submitter.
		if ( ! $updating_existing ) {
			Tools::audit_log(
				sprintf(
					'Submitted by <a href="%s">%s</a>.',
					esc_url( 'https://profiles.wordpress.org/' . wp_get_current_user()->user_nicename . '/' ),
					wp_get_current_user()->user_login
				),
				$plugin_post->ID
			);
		}

		$attachment = $this->save_zip_file( $plugin_post->ID );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}

		// Store metadata about the uploaded ZIP.
		// Count lines of PHP code, this is not 100% accurate but it's a good indicator.
		$lines_of_code = (int) shell_exec( sprintf( "find %s -type f -name '*.php' -exec cat {} + | wc -l", escapeshellarg( $this->plugin_dir ) ) );

		update_post_meta( $plugin_post->ID, '_submitted_zip_size', filesize( get_attached_file( $attachment->ID ) ) );
		update_post_meta( $plugin_post->ID, '_submitted_zip_loc', $lines_of_code );

		do_action( 'plugin_upload', $this->plugin, $plugin_post );

		if ( $updating_existing ) {

			// Update HelpScout, if in review.
			$this->update_review_email( $plugin_post, $attachment );

			$message = sprintf(
				__( 'New version of %s uploaded for review.', 'wporg-plugins' ),
				esc_html( $this->plugin['Name'] )
			);

			if ( 'pending' === $plugin_post->post_status ) {
				$message .= '<br>' . __( 'Please respond to the review email to let us know, and address any feedback that was given to you.', 'wporg-plugins' );
			}

			return $message;
		}

		// Send plugin author an email for peace of mind.
		$email = new Plugin_Submission_Email( $plugin_post, wp_get_current_user() );
		$email->send();

		$message = sprintf(
			/* translators: 1: plugin name, 2: plugin slug, 3: plugins@wordpress.org */
			__( 'Thank you for uploading %1$s to the WordPress Plugin Directory. Your plugin has been given the initial slug of %2$s, however that is subject to change based on the results of your code review. If this slug is incorrect, please change it below. Remember, a plugin slug cannot be changed once your plugin is approved.' ),
			esc_html( $this->plugin['Name'] ),
			'<code>' . $this->plugin_slug . '</code>'
		) . '</p><p>';

		$message .= __( 'We&rsquo;ve sent you an email verifying this submission. Make sure you set all emails from wordpress.org to never go to spam (i.e. via email filters or approval lists). That will ensure you won&rsquo;t miss any of our messages.', 'wporg-plugins' ) . '</p><p>';

		$message .= __( 'If there are any errors in your submission, such as having submitted via the wrong account, please don\'t resubmit! Instead, email us as soon as possible (you can reply to the automated email we sent you). We can correct most issues before approval.', 'wporg-plugins' ) . '</p><p>';

		$message .= sprintf(
			/* translators: 1: URL to guidelines; 2: URL to FAQs; */
			wp_kses_post( __( 'While you&#8217;re waiting on your review, please take the time to read <a href="%1$s">the developer guidelines</a> and <a href="%2$s">the developer FAQ</a> as they will address most questions.', 'wporg-plugins' ) ),
			'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/',
			'https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/'
		) . '</p><p>';

		$message .= __( 'Note: Reviews are currently in English only. We apologize for the inconvenience.', 'wporg-plugins' );

		$message .= '</p>';

		// Success!
		return $message;
	}

	/**
	 * Generate a plugin slug from a Plugin name.
	 *
	 * @param string $plugin_name The plugin name.
	 * @return string The generated plugin slug.
	 */
	public function generate_plugin_slug( $plugin_name ) {
		$plugin_slug = remove_accents( $plugin_name );
		$plugin_slug = preg_replace( '/[^a-z0-9 _.-]/i', '', $plugin_slug );
		$plugin_slug = str_replace( '_', '-', $plugin_slug );
		$plugin_slug = sanitize_title_with_dashes( $plugin_slug );

		return $plugin_slug;
	}

	/**
	 * Whether the uploaded plugin uses a reserved slug.
	 *
	 * @return bool True if the slug is reserved, false otherwise.
	 */
	public function has_reserved_slug() {
		$reserved_slugs = array(
			// Plugin Directory URL parameters.
			'about',
			'admin',
			'browse',
			'category',
			'developers',
			'developer',
			'featured',
			'filter',
			'new',
			'page',
			'plugins',
			'popular',
			'post',
			'search',
			'tag',
			'updated',
			'upload',
			'wp-admin',
			// Reserved names.
			'jquery',
			'wordpress',
			// common submissions by people trying to upload locally.
			'akismet-anti-spam',
			'site-kit-by-google',
			'yoast-seo',
			'woo',
			'wp-media-folder',
			'wp-file-download',
			'wp-table-manager',
			// reserved plugins that used to exist outside of .org -- reusing would be bad.
			'acf-repeater',
			'acf-flexible-content',
			'acf-options-page',
			'acf-gallery',
		);

		return in_array( $this->plugin_slug, $reserved_slugs );
	}

	/**
	 * Whether the uploaded plugin uses a trademark in the slug.
	 *
	 * @return string|false The trademarked slug if found, false otherwise.
	 */
	public function has_trademarked_slug( $plugin_slug = false ) {
		$plugin_slug = $plugin_slug ?: $this->plugin_slug;

		$trademarked_slugs = array(
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
			'woocommerce',  // technically ending with '-for-woocommerce' is allowed.
			'woocomerce',
			'woo-commerce',
			'woo-',
			'wo-',
			'wordpress',
			'wordpess',
			'wpress',
			'wp-',
			'wp-mail-smtp-',
			'yandex-',
			'yahoo-',
			'yoast',
			'youtube-',
			'you-tube-',
		);

		// Domains from which exceptions would be accepted.
		$trademark_exceptions = array(
			'adobe.com'             => array( 'adobe' ),
			'automattic.com'        => array( 'akismet', 'akismet-', 'jetpack', 'jetpack-', 'wordpress', 'wp-', 'woo', 'woo-', 'woocommerce', 'woocommerce-' ),
			'facebook.com'          => array( 'facebook', 'instagram', 'oculus', 'whatsapp' ),
			'support.microsoft.com' => array( 'bing-', 'microsoft-' ),
			'trustpilot.com'        => array( 'trustpilot' ),
			'microsoft.com'         => array( 'bing-', 'microsoft-' ),
			'yandex-team.ru'        => array( 'yandex' ),
			'yoast.com'             => array( 'yoast' ),
			'opera.com'             => array( 'opera-' ),
			'adobe.com'				=> array( 'adobe-' ),
		);

		// Trademarks that are allowed as 'for-whatever' ONLY.
		$for_use_exceptions = array(
			'woocommerce',
		);

		// Commonly used 'combo' names (to prevent things like 'woopress').
		$portmanteaus = array(
			'woo',
		);

		$has_trademarked_slug = false;

		foreach ( $trademarked_slugs as $trademark ) {
			if ( '-' === $trademark[-1] ) {
				// Trademarks ending in "-" indicate slug cannot begin with that term.
				if ( 0 === strpos( $plugin_slug, $trademark ) ) {
					$has_trademarked_slug = $trademark;
					break;
				}
			} elseif ( false !== strpos( $plugin_slug, $trademark ) ) {
				// Otherwise, the term cannot appear anywhere in slug.
				$has_trademarked_slug = $trademark;
				break;
			}
		}

		// check for 'for-TRADEMARK' exceptions.
		if ( $has_trademarked_slug && in_array( $has_trademarked_slug, $for_use_exceptions ) ) {
			$for_trademark = '-for-' . $has_trademarked_slug;
			// At this point we might be okay, but there's one more check.
			if ( $for_trademark === substr( $plugin_slug, -1 * strlen( $for_trademark ) ) ) {
				// Yes the slug ENDS with 'for-TRADEMARK'.
				$has_trademarked_slug = false;
			}
		}

		// Check portmanteaus.
		foreach ( $portmanteaus as $portmanteau ) {
			if ( 0 === strpos( $plugin_slug, $portmanteau ) ) {
				$has_trademarked_slug = $portmanteau;
				break;
			}
		}

		// Get the user email domain.
		list( ,$user_email_domain ) = explode( '@', wp_get_current_user()->user_email, 2 );

		// If email domain is on our list of possible exceptions, we have an extra check.
		if ( $has_trademarked_slug && array_key_exists( $user_email_domain, $trademark_exceptions ) ) {
			// If $has_trademarked_slug is in the array for that domain, they can use the term.
			if ( in_array( $has_trademarked_slug, $trademark_exceptions[ $user_email_domain ] ) ) {
				$has_trademarked_slug = false;
			}
		}

		return $has_trademarked_slug;
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
		echo '<div class="notice notice-info"><p>' . __( 'Note: While the automated plugin scan is based on the Plugin Review Guidelines, it is not a complete review. A successful result from the scan does not guarantee that the plugin will be approved, only that it is sufficient to be reviewed. All submitted plugins are checked manually to ensure they meet security and guideline standards before approval.', 'wporg-plugins' ) . '</p></div>';

		return $result;
	}

	/**
	 * Saves zip file and attaches it to the plugin post.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post|WP_Error Attachment post or upload error.
	 */
	public function save_zip_file( $post_id ) {
		$zip_hash = sha1_file( $_FILES['zip_file']['tmp_name'] );
		if ( in_array( $zip_hash, get_post_meta( $post_id, 'uploaded_zip_hash' ) ?: [], true ) ) {
			return new WP_Error( 'already_uploaded', __( "You've already uploaded that ZIP file.", 'wporg-plugins' ) );
		}

		// Upload folders are already year/month based. A second-based prefix should be specific enough.
		$original_name              = $_FILES['zip_file']['name'];
		$_FILES['zip_file']['name'] = date( 'd_H-i-s' ) . '_' . $_FILES['zip_file']['name'];

		add_filter( 'site_option_upload_filetypes', array( $this, 'whitelist_zip_files' ) );
		add_filter( 'default_site_option_upload_filetypes', array( $this, 'whitelist_zip_files' ) );

		// Store the plugin details against the media as well.
		$post_details  = array(
			'post_title'   => sprintf( '%s Version %s', $this->plugin['Name'], $this->plugin['Version'] ),
			'post_excerpt' => $this->plugin['Description'],
		);
		$attachment = media_handle_upload( 'zip_file', $post_id, $post_details );

		remove_filter( 'site_option_upload_filetypes', array( $this, 'whitelist_zip_files' ) );
		remove_filter( 'default_site_option_upload_filetypes', array( $this, 'whitelist_zip_files' ) );

		if ( ! is_wp_error( $attachment ) ) {
			$attachment = get_post( $attachment );

			// Save some basic details with the ZIP.
			update_post_meta( $attachment->ID, 'version', $this->plugin['Version'] );
			update_post_meta( $attachment->ID, 'submitted_name', $original_name );

			// And record this ZIP as having been uploaded.
			add_post_meta( $post_id, 'uploaded_zip_hash', $zip_hash );
		}

		return $attachment;
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

	/**
	 * Determine if the current user has a valid upload token.
	 *
	 * An upload token can be used to bypass various plugin checks.
	 */
	public function has_valid_upload_token() {
		$token = wp_unslash( $_REQUEST['upload_token'] ?? '' );

		return $token && Upload_Token::instance()->is_valid_for_user( get_current_user_id(), $token );
	}

	/**
	 * Locate the HelpScout review email and it's status.
	 *
	 * @param WP_Post $post The plugin post.
	 *
	 * @return array|false
	 */
	public static function find_review_email( $post ) {
		global $wpdb;

		if ( 'pending' !== $post->post_status || ! $post->post_name ) {
			return false;
		}

		// Find the latest email for this plugin that looks like a review email.
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT emails.*
				FROM %i emails
					JOIN %i meta ON emails.id = meta.helpscout_id
				WHERE meta.meta_key = 'plugins' AND meta.meta_value = %s
					AND emails.subject LIKE %s
				ORDER BY `created` DESC
				LIMIT 1",
			"{$wpdb->base_prefix}helpscout",
			"{$wpdb->base_prefix}helpscout_meta",
			$post->post_name,
			'%Review in Progress:%' // The subject line of the review email.
		) );
	}

	/**
	 * Update the HelpScout review email.
	 *
	 * @param WP_Post $post       The plugin post.
	 * @param WP_Post $attachment The uploaded attachment post.
	 *
	 * @return bool True if the email was updated, false otherwise.
	 */
	public function update_review_email( $post, $attachment ) {
		$review_email = self::find_review_email( $post );
		if ( ! $review_email ) {
			return false;
		}

		// Don't update the review email if the plugin author isn't the one who uploaded the ZIP.
		if ( $post->post_author != get_current_user_id() ) {
			return false;
		}

		$text = sprintf(
			"New ZIP uploaded by %s, version %s.\n%s\n%s",
			wp_get_current_user()->user_login,
			$attachment->version,
			get_edit_post_link( $post ),
			wp_get_attachment_url( $attachment->ID )
		);

		$result = HelpScout::api(
			'/v2/conversations/' . $review_email->id . '/notes',
			[
				'text'   => $text,
				'status' => 'active',
			],
			'POST',
			$http_response_code
		);

		return ( 201 === $http_response_code );
	}
}
