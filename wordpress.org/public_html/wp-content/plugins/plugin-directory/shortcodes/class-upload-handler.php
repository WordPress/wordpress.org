<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use WP_Error;
use WordPressdotorg\Plugin_Directory\CLI\Import;
use WordPressdotorg\Plugin_Directory\Readme\Parser;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Readme\Validator as Readme_Validator;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use WordPressdotorg\Plugin_Directory\Trademarks;
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
		$upload_comment   = trim( wp_unslash( $_POST['comment'] ?? '' ) );
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

		// Make sure it doesn't use a TRADEMARK. We check the name first, and then the slug.
		$has_trademarked_slug = Trademarks::check( $this->plugin['Name'], wp_get_current_user() );
		$trademark_context    = $this->plugin['Name'];

		if ( ! $has_trademarked_slug && ! $updating_existing ) {
			// Check the slug on new submissions in addition to the name.
			$has_trademarked_slug = Trademarks::check_slug( $this->plugin_slug, wp_get_current_user() );
			$trademark_context    = $this->plugin_slug;
		}

		if ( $has_trademarked_slug && ! $has_upload_token ) {
			$error = Readme_Validator::instance()->translate_code_to_message(
				'trademarked_slug',
				[
					'trademark' => $has_trademarked_slug,
					'context'   => $trademark_context,
				]
			);

			$to_proceed_text = sprintf(
				/* translators: 1: Plugin Name header */
				__( 'To proceed with this submission you must change your %1$s line in both your main plugin file and readme to abide by these requirements. Once you\'ve finished, you may upload the plugin again. Do not attempt to work around this by removing letters (i.e. WordPess) or using numbers (4 instead of A). Those are seen as intentional actions to avoid our restrictions, and are not permitted.', 'wporg-plugins' ),
				'<code>Plugin Name:</code>'
			);
			$in_error_text   = sprintf(
				/* translators: plugins@wordpress.org */
				__( 'If you feel this is in error, such as you legally own the trademark for the term, please email us at %1$s and explain your situation.', 'wporg-plugins' ),
				'plugins@wordpress.org'
			);

			return new WP_Error( 'trademarked_name', "{$error} {$to_proceed_text} {$in_error_text}" );
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
		$plugin_check_result = $this->check_plugin();

		if ( ! $plugin_check_result['verdict'] && ! $has_upload_token ) {
			return new WP_Error(
				'failed_checks',
				__( 'Error: The plugin has failed the automated checks.', 'wporg-plugins' ) . ' ' .
				sprintf(
					/* translators: 1: Plugin Check Plugin URL, 2: plugins email. */
					__( 'Please correct the listed problems with your plugin and upload it again. You can also use the <a href="%1$s">Plugin Check Plugin</a> to test your plugin before uploading. If you have any questions about this please contact %2$s.', 'wporg-plugins' ),
					'https://wordpress.org/plugins/plugin-check/',
					'<a href="mailto:plugins@wordpress.org">plugins@wordpress.org</a>'
				) .
				'</p><p>' .
				( $plugin_check_result['html'] ?? '' )
			);
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
			'post_author'   => $plugin_post->post_author ?? get_current_user_id(),
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
			$post_args['meta_input']['_author_ip']         = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );
			$post_args['meta_input']['_submitted_date']    = time();
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

		$attachment = $this->save_zip_file( $plugin_post->ID, $upload_comment, $plugin_check_result );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}

		// Store the uploaded comment as a plugin audit log.
		if ( $upload_comment ) {
			Tools::audit_log(
				sprintf(
					"Upload Comment for <a href='%s'>%s</a>\n%s",
					wp_get_attachment_url( $attachment->ID ),
					esc_html( $attachment->submitted_name ),
					esc_html( $upload_comment )
				),
				$plugin_post->ID,
			);
		}

		// Store metadata about the uploaded ZIP.
		// Count lines of PHP code, this is not 100% accurate but it's a good indicator.
		$lines_of_code = (int) shell_exec( sprintf( "find %s -type f -name '*.php' -exec cat {} + | wc -l", escapeshellarg( $this->plugin_dir ) ) );

		update_post_meta( $plugin_post->ID, '_submitted_zip_size', filesize( get_attached_file( $attachment->ID ) ) );
		update_post_meta( $plugin_post->ID, '_submitted_zip_loc', $lines_of_code );

		// Keep a log of all plugin names used by the plugin over time.
		$plugin_names = get_post_meta( $plugin_post->ID, 'plugin_name_history', true ) ?: [];
		if ( ! isset( $plugin_names[ $this->plugin['Name'] ] ) ) {
			// [ 'Plugin Name' => '1.2.3', 'Plugin New Name' => '4.5.6' ]
			$plugin_names[ $this->plugin['Name'] ] = $this->plugin['Version'];
			update_post_meta( $plugin_post->ID, 'plugin_name_history', wp_slash( $plugin_names ) );
		}

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
			/* translators: 1: plugin name, 2: plugin slug */
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

		// Append the plugin check results.
		if ( ! empty( $plugin_check_result['html'] ) ) {
			$message .= $plugin_check_result['html'];
		}

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
	 * Sends a plugin through Plugin Check.
	 *
	 * @return array The results of the plugin check.
	 */
	public function check_plugin() {
		// Run the checks.
		if (
			! defined( 'WPCLI' ) ||
			! defined( 'WP_CLI_CONFIG_PATH' ) ||
			// The plugin must be activated in order to have plugin-check run.
			! defined( 'WP_PLUGIN_CHECK_VERSION' ) ||
			// WordPress.org only..
			! function_exists( 'notify_slack' )
		) {
			return true;
		}

		// Run plugin check via CLI
		$start_time = microtime(1);
		exec(
			'export WP_CLI_CONFIG_PATH=' . escapeshellarg( WP_CLI_CONFIG_PATH ) . '; ' .
			'timeout 30 ' . // Timeout after 30s if plugin-check is not done.
			WPCLI . ' --url=https://wordpress.org/plugins ' .
			' plugin check --error-severity=7 --format=json ' . escapeshellarg( $this->plugin_root ),
			$output,
			$return_code
		);
		$total_time = microtime(1) - $start_time;

		/**
		 * Anything that plugin-check outputs that we want to discard completely.
		 */
		$is_ignored_code = static function( $code ) {
			$ignored_codes = [
			];

			return (
				in_array( $code, $ignored_codes, true ) ||
				// All the Readme parser warnings are duplicated, we'll exclude those.
				str_starts_with( $code, 'readme_parser_warnings_' )
			);
		};

		/*
		 * Convert the output into an array.
		 * Format:
		 * FILE: example.extension
		 * [{.....}]
		 *
		 * FILE: example2.extension
		 * [{.....}]
		 */
		$verdict  = true;
		$results  = [];
		foreach ( array_chunk( $output, 3 ) as $file_result ) {
			if ( ! str_starts_with( $file_result[0], 'FILE:' ) ) {
				continue;
			}

			$filename = trim( explode( ':' , $file_result[0], 2 )[1] );
			$json     = json_decode( $file_result[1], true );

			foreach ( $json as $record ) {
				$record['file'] = $filename;

				if ( $is_ignored_code( $record['code'] ) ) {
					continue;
				}

				$results[] = $record;

				// Record submission stats.
				if ( function_exists( 'bump_stats_extra' ) && 'production' === wp_get_environment_type() ) {
					bump_stats_extra( 'plugin-check-' . $record['type'], $record['code'] );
				}

				// Determine if it failed the checks.
				if ( $verdict && 'ERROR' === $record['type'] ) {
					$verdict = false;
				}
			}
		}

		// Generage the HTML for the Plugin Check output.
		$html = sprintf(
			'<strong>' . __( 'Results of Automated Plugin Scanning: %s', 'wporg-plugins' ) . '</strong>',
			$verdict ? __( 'Pass', 'wporg-plugins' ) : __( 'Fail', 'wporg-plugins' )
		);
		if ( $results ) {
			$html .= '<ul class="pc-result" style="list-style: disc">';
			foreach ( $results as $result ) {
				$html .= sprintf(
					'<li>%s <a href="%s">%s</a>: %s</li>',
					esc_html( $result['file'] ),
					esc_url( $result['docs'] ?? '' ),
					esc_html( $result['type'] . ' ' . $result['code'] ),
					esc_html( $result['message'] )
				);
			}
			$html .= '</ul>';
		}
		$html .= __( 'Note: While the automated plugin scan is based on the Plugin Review Guidelines, it is not a complete review. A successful result from the scan does not guarantee that the plugin will be approved, only that it is sufficient to be reviewed. All submitted plugins are checked manually to ensure they meet security and guideline standards before approval.', 'wporg-plugins' );

		// If the upload is blocked; log it.
		if ( ! $verdict || true ) { // TODO: Temporarily logging all to slack, as it's not output to the submitter.
			// Slack dm the logs.
			$zip_name = reset( $_FILES )['name'];
			$failpass = $verdict ? 'passed' : 'failed';
			if ( $return_code > 1 ) { // TODO: Temporary, as we're always hitting this branch.
				$failpass = ' errored: ' . $return_code;
			}
			$text     = "Plugin check {$failpass} for {$zip_name}: {$this->plugin['Name']} ({$this->plugin_slug}) took {$total_time}s\n";

			// List the errors, then the warnings (which may be truncated).
			foreach ( [ wp_list_filter( $results, [ 'type' => 'ERROR' ] ), wp_list_filter( $results, [ 'type' => 'ERROR' ], 'NOT' ) ] as $result_set ) {
				foreach ( $result_set as $result ) {
					$text .= " - {$result['file']}: {$result['type']} - {$result['code']}: {$result['message']}\n";
				}
			}

			notify_slack( PLUGIN_CHECK_LOGS_SLACK_CHANNEL, $text, wp_get_current_user(), true );
		} elseif ( $return_code ) {
			// Log plugin-check timing out.
			$zip_name   = reset( $_FILES )['name'];
			$text       = "Plugin check error {$return_code} for {$zip_name}: {$this->plugin['Name']} ({$this->plugin_slug}) took {$total_time}s\n";
			notify_slack( PLUGIN_CHECK_LOGS_SLACK_CHANNEL, $text, wp_get_current_user(), true );
		}

		// TODO: Payload to always pass, and not show anything to the submitter, temporary.
		return [
			'verdict' => true,
			'results' => $results,
			'html'    => '',
		];

		// Return the results.
		return [
			'verdict' => $verdict,
			'results' => $results,
			'html'    => $html,
		];
	}

	/**
	 * Saves zip file and attaches it to the plugin post.
	 *
	 * @param int $post_id Post ID.
	 * @param string $upload_comment Comment for the upload.
	 * @param array|bool $plugin_check_result Plugin check results.
	 * @return WP_Post|WP_Error Attachment post or upload error.
	 */
	public function save_zip_file( $post_id, $upload_comment, $plugin_check_result = false ) {
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
		$post_details = array(
			'post_title'   => sprintf( '%s Version %s', $this->plugin['Name'], $this->plugin['Version'] ),
			'post_excerpt' => $this->plugin['Description'],
			'post_content' => esc_html( $upload_comment )
		);
		$attachment = media_handle_upload( 'zip_file', $post_id, $post_details );

		remove_filter( 'site_option_upload_filetypes', array( $this, 'whitelist_zip_files' ) );
		remove_filter( 'default_site_option_upload_filetypes', array( $this, 'whitelist_zip_files' ) );

		if ( ! is_wp_error( $attachment ) ) {
			$attachment = get_post( $attachment );

			// Save some basic details with the ZIP.
			update_post_meta( $attachment->ID, 'version', $this->plugin['Version'] );
			update_post_meta( $attachment->ID, 'submitted_name', $original_name );

			if ( $plugin_check_result ) {
				update_post_meta( $attachment->ID, 'pc_verdict', $plugin_check_result['verdict'] );
				update_post_meta( $attachment->ID, 'pc_results', $plugin_check_result['results'] );
			}

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
		if ( ! in_array( $post->post_status, [ 'new', 'pending' ] ) || ! $post->post_name ) {
			return false;
		}

		return Tools::get_helpscout_emails( $post, [ 'subject' => 'Review in Progress:', 'limit' => 1 ] );
	}

	/**
	 * Update the HelpScout review email.
	 *
	 * @param WP_Post $post       The plugin post.
	 * @param WP_Post $attachment The uploaded attachment post.
	 *
	 * @return bool True if the email was updated, false otherwise.
	 */
	public static function update_review_email( $post, $attachment ) {
		$review_email = self::find_review_email( $post );
		if ( ! $review_email ) {
			return false;
		}

		// Don't update the review email if the plugin author isn't the one who uploaded the ZIP.
		if ( $post->post_author != get_current_user_id() ) {
			return false;
		}

		$text = "This is an automated message to confirm that we have received your updated plugin file.\n\n";
		$text .= sprintf(
			"File updated by %s, version %s.\n",
			wp_get_current_user()->user_login,
			$attachment->version
		);

		// Was a comment added?
		if ( $attachment->post_content ) {
			$text .= "Comment: " . $attachment->post_content . "\n";
		}

		// Append the ZIP URL.
		$text .= "\n" . wp_get_attachment_url( $attachment->ID );

		$name = wp_get_current_user()->display_name ?: wp_get_current_user()->user_login;
		$payload = [
			'customer' => array_filter( [
				'firstName' => substr( explode( ' ', $name, 2 )[0], 0, 39 ),
				'lastName'  => trim( substr( explode( ' ', "{$name} ", 2 )[1], 0, 39 ) ),
				'email'     => wp_get_current_user()->user_email,
			] ),
			'text'   => $text,
			'status' => 'active',
		];

		$result = HelpScout::api(
			'/v2/conversations/' . $review_email->id . '/reply',
			$payload,
			'POST',
			$http_response_code
		);

		$success = ( 201 === $http_response_code );

		if ( ! $success ) {
			trigger_error( "Helpscout update failed: $http_response_code: " . var_export( $result, true ), E_USER_WARNING );
		}

		return $success;
	}
}
