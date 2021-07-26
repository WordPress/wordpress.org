<?php
use WordPressdotorg\Theme_Directory\Lib\GitHub;

/**
 * Class WPORG_Themes_Upload
 *
 * Processes a theme upload.
 */
class WPORG_Themes_Upload {
	/**
	 * Path to `svn` script.
	 *
	 * @var string
	 */
	const SVN = '/usr/bin/svn';

	/**
	 * Path to `rm` script.
	 *
	 * @var string
	 */
	const RM = '/bin/rm';

	/**
	 * Path to `cp` script.
	 *
	 * @var string
	 */
	const CP = '/bin/cp';

	/**
	 * Path to `unzip` script.
	 *
	 * @var string
	 */
	const UNZIP = '/usr/bin/unzip';

	/**
	 * Temporary directory where uploads are stored.
	 * 
	 * @var string
	 */
	const TMP = '/tmp/wporg-theme-upload';

	/**
	 * Path to temporary directory.
	 *
	 * @var string
	 */
	protected $tmp_dir;

	/**
	 * Path to a temporary SVN checkout directory.
	 * 
	 * @var string
	 */
	protected $tmp_svn_dir;

	/**
	 * Path to temporary theme folder.
	 *
	 * @var string
	 */
	protected $theme_dir;

	/**
	 * The uploaded theme.
	 *
	 * @var WP_Theme
	 */
	protected $theme;

	/**
	 * The theme post if it already exists in the repository.
	 *
	 * @var WP_Post
	 */
	protected $theme_post;

	/**
	 * The theme author (current user).
	 *
	 * @var WP_User
	 */
	protected $author;

	/**
	 * The theme readme.txt data.
	 *
	 * @var array
	 */
	 protected $readme;

	/**
	 * Trac ticket information.
	 *
	 * @var object
	 */
	protected $trac_ticket;

	/**
	 * Trac changeset.
	 * 
	 * @var string
	 */
	protected $trac_changeset;

	/**
	 * A Trac instance to communicate with theme.trac.
	 *
	 * @var Trac
	 */
	protected $trac;

	/**
	 * The list of headers to extract from readme.txt.
	 *
	 * @var array
	 */
	protected $readme_header_fields = array(
		'tested'       => 'tested up to',
		'contributors' => 'contributors',
		'license'      => 'license',
		'license_uri'  => 'license uri',
	);

	/**
	 * Validate that a theme upload succeeded and was a valid file.
	 */
	public function validate_upload( $file ) {
		// Check to see if PHP detected any errors in the upload.
		if ( 0 !== $file['error'] ) {
			return false;
		}

		// Validate the file uploaded correctly.
		$size = filesize( $file['tmp_name'] );
		if ( ! $size || $size !== $file['size'] ) {
			return false;
		}

		// Validate that the file is a ZIP file before processing it.
		$check = wp_check_filetype_and_ext(
			$file['tmp_name'],
			$file['name'],
			[
				'zip' => 'application/zip'
			]
		);
		if ( ! $check['ext'] || ! $check['type'] ) {
			return false;
		}

		// Everything seems fine, the ZIP file might still be invalid still if it was corrupted on the authors computer.
		return true;
	}

	/**
	 * Processes the theme upload.
	 *
	 * Runs various tests, creates Trac ticket, repopackage post, and saves the files to the SVN repo.
	 *
	 * @return mixed Failure or success message.
	 */
	public function process_upload( $file_upload ) {
		$valid_upload = $this->validate_upload( $file_upload );
		if ( ! $valid_upload ) {
			return __( 'Error in file upload.', 'wporg-themes' );
		}

		$this->create_tmp_dirs( $file_upload['name'] );
		$this->unzip_package( $file_upload );

		$theme_files = $this->get_all_files( $this->theme_dir );

		// First things first. Do we have something to work with?
		if ( empty( $theme_files ) ) {
			return __( 'The zip file was empty.', 'wporg-themes' );
		}

		// Do we have a stylesheet? Life is kind of pointless without.
		$style_css = $this->get_style_css( $theme_files );
		if ( empty( $style_css ) ) {
			/* translators: %s: style.css */
			return sprintf( __( 'The zip file must include a file named %s.', 'wporg-themes' ),
				'<code>style.css</code>'
			);
		}

		$style_errors = [];

		// Do we have a readme.txt? Fetch extra data from there too.
		$this->readme = $this->get_readme_data( $theme_files );

		// We have a stylesheet, let's set up the theme, theme post, and author.
		$this->theme = new WP_Theme( basename( dirname( $style_css ) ), dirname( dirname( $style_css ) ) );

		// We need a screen shot. People love screen shots.
		if ( ! $this->has_screenshot( $theme_files ) ) {
			/* translators: 1: screenshot.png, 2: screenshot.jpg */
			array_push( $style_errors, sprintf( __( 'The zip file must include a file named %1$s or %2$s.', 'wporg-themes' ),
				'<code>screenshot.png</code>',
				'<code>screenshot.jpg</code>'
			);
		}

		// reset the theme directory to be where the stylesheet is
		$this->theme_dir = dirname( $style_css );

		// Let's check some theme headers, shall we?
		$this->theme_name = $this->theme->get( 'Name' );

		// Determine the theme slug (ascii only for compatibility) based on the name of the theme in the stylesheet
		$this->theme_slug = remove_accents( $this->theme_name );
		$this->theme_slug = preg_replace( '/%[a-f0-9]{2}/i', '', $this->theme_slug );
		$this->theme_slug = sanitize_title_with_dashes( $this->theme_slug );

		if ( ! $this->theme_name || ! $this->theme_slug ) {
			$error = __( 'The theme has no name.', 'wporg-themes' ) . ' ';

			/* translators: 1: comment header line, 2: style.css, 3: Codex URL */
			$error .= sprintf( __( 'Add a %1$s line to your %2$s file and upload the theme again. <a href="%3$s">Theme Style Sheets</a>', 'wporg-themes' ),
				'<code>Theme Name:</code>',
				'<code>style.css</code>',
				__( 'https://codex.wordpress.org/Theme_Development#Theme_Stylesheet', 'wporg-themes' )
			);

			array_push( $style_errors, $error );
		}

		// Do not allow themes with WordPress and Theme in the theme name.
		if ( false !== strpos( $this->theme_slug, 'wordpress' ) || preg_match( '/\btheme\b/i', $this->theme_slug ) ) {
			/* translators: 1: 'WordPress', 2: 'theme' */
			array_push( $style_errors, sprintf( __( 'You cannot use %1$s or %2$s in your theme name.', 'wporg-themes' ),
				'WordPress',
				'theme'
			) );
		}

		// Populate author.
		$this->author = wp_get_current_user();

		// Make sure it doesn't use a slug deemed not to be used by the public.
		// This check must be run before `get_theme_post()` to account for "twenty" themes.
		if ( $this->has_reserved_slug() ) {
			/* translators: 1: theme slug, 2: style.css */
			array_push( $style_errors, sprintf( __( 'Sorry, the theme name %1$s is reserved for use by WordPress Core. Please change the name of your theme in %2$s and upload it again.', 'wporg-themes' ),
				'<code>' . $this->theme_slug . '</code>',
				'<code>style.css</code>'
			) );
		}

		// Populate the theme post.
		$this->theme_post = $this->get_theme_post();

		$theme_description = $this->strip_non_utf8( (string) $this->theme->get( 'Description' ) );
		if ( empty( $theme_description ) ) {
			$error = __( 'The theme has no description.', 'wporg-themes' ) . ' ';

			/* translators: 1: comment header line, 2: style.css, 3: Codex URL */
			$error .= sprintf( __( 'Add a %1$s line to your %2$s file and upload the theme again. <a href="%3$s">Theme Style Sheets</a>', 'wporg-themes' ),
				'<code>Description:</code>',
				'<code>style.css</code>',
				__( 'https://codex.wordpress.org/Theme_Development#Theme_Stylesheet', 'wporg-themes' )
			);

			array_push( $style_errors, $error );
		}

		if ( ! $this->theme->get( 'Tags' ) ) {
			$error = __( 'The theme has no tags.', 'wporg-themes' ) . ' ';

			/* translators: 1: comment header line, 2: style.css, 3: Codex URL */
			$error .= sprintf( __( 'Add a %1$s line to your %2$s file and upload the theme again. <a href="%3$s">Theme Style Sheets</a>', 'wporg-themes' ),
				'<code>Tags:</code>',
				'<code>style.css</code>',
				__( 'https://codex.wordpress.org/Theme_Development#Theme_Stylesheet', 'wporg-themes' )
			);

			array_push( $style_errors, $error );
		}

		if ( ! $this->theme->get( 'Version' ) ) {
			$error = __( 'The theme has no version.', 'wporg-themes' ) . ' ';

			/* translators: 1: comment header line, 2: style.css, 3: Codex URL */
			$error .= sprintf( __( 'Add a %1$s line to your %2$s file and upload the theme again. <a href="%3$s">Theme Style Sheets</a>', 'wporg-themes' ),
				'<code>Version:</code>',
				'<code>style.css</code>',
				__( 'https://codex.wordpress.org/Theme_Development#Theme_Stylesheet', 'wporg-themes' )
			);

			array_push( $style_errors, $error );

		} else if ( preg_match( '|[^\d\.]|', $this->theme->get( 'Version' ) ) ) {
			/* translators: %s: style.css */
			array_push( $style_errors, sprintf( __( 'Version strings can only contain numeric and period characters (like 1.2). Please fix your Version: line in %s and upload your theme again.', 'wporg-themes' ),
				'<code>style.css</code>'
			) );
		}

		// Version is greater than current version happens after authorship checks.

		// Prevent duplicate URLs.
		$themeuri = $this->theme->get( 'ThemeURI' );
		$authoruri = $this->theme->get( 'AuthorURI' );
		if ( !empty( $themeuri ) && !empty( $authoruri ) && $themeuri == $authoruri ) {
			array_push( $style_errors, __( 'Duplicate theme and author URLs. A theme URL is a page/site that provides details about this specific theme. An author URL is a page/site that provides information about the author of the theme. You aren&rsquo;t required to provide both, so pick the one that best applies to your URL.', 'wporg-themes' ) );
		}

		// Check for child theme's parent in the directory (non-buddypress only)
		if ( $this->theme->parent() && ! in_array( 'buddypress', $this->theme->get( 'Tags' ) ) && ! $this->is_parent_available() ) {
			/* translators: %s: parent theme */
			array_push( $style_errors, sprintf( __( 'There is no theme called %s in the directory. For child themes, you must use a parent theme that already exists in the directory.', 'wporg-themes' ),
				'<code>' . $this->theme->parent() . '</code>'
			) );
		}

		// Generic text to suggest "Are you in the right place?"
		$are_you_in_the_right_place = '<br>' . 
			__( 'The WordPress.org Theme Directory is for sharing a unique theme with others, duplicates are not allowed.', 'wporg-themes' ) .
			'<br>' .
			sprintf(
				/* translators: %s: A link to https://wordpress.org/support/article/using-themes/ */
				__( "If you're attempting to install a theme on your website, <a href='%s'>please see this article</a>.", 'wporg-themes' ),
				'https://wordpress.org/support/article/using-themes/'
			);

		// Is there already a theme with the name name by a different author?
		if ( ! empty( $this->theme_post ) && $this->theme_post->post_author != $this->author->ID ) {

			$is_allowed_to_upload_for_theme = false;
			if (
				// The theme is owned by WordPress.org.
				'wordpressdotorg' === get_user_by( 'id', $this->theme_post->post_author )->user_nicename &&
				// The current user is a Core Committer. [ 'user_login' => 'Trac Title', ... ]
				! empty( $GLOBALS['committers'][ $this->author->user_login ] )
			) {
				// Allow core committers to update default themes (as authored by @wordpressdotorg)
				$is_allowed_to_upload_for_theme = true;
			}

			if ( ! $is_allowed_to_upload_for_theme ) {
				/* translators: 1: theme slug, 2: style.css */
				array_push( $style_errors, sprintf( __( 'There is already a theme called %1$s by a different author. Please change the name of your theme in %2$s and upload it again.', 'wporg-themes' ),
					'<code>' . $this->theme_slug . '</code>',
					'<code>style.css</code>'
				) . $are_you_in_the_right_place );
			}
		}

		// Check if the ThemeURI is already in use by another theme by another author.
		if ( empty( $this->theme_post ) && ! empty( $themeuri ) ) {
			$theme_uri_matches = get_posts( [
				'post_type'        => 'repopackage',
				'post_status'      => 'publish',
				'meta_query'       => [
					'theme_uri_search' => [
						'key'     => '_theme_url',
						'value'   => '"' . $themeuri . '"', // Searching within a Serialized PHP value
						'compare' => 'LIKE'
					],
				]
			] );
			$theme_owners = wp_list_pluck( $theme_uri_matches, 'post_author' );

			if ( $theme_owners && ! in_array( $this->author->ID, $theme_owners ) ) {
				array_push( $style_errors, sprintf(
					/* translators: 1: theme name, 2: style.css */
					__( 'There is already a theme using the Theme URL %1$s by a different author. Please check the URL of your theme in %2$s and upload it again.', 'wporg-themes' ),
					'<code>' . esc_html( $themeuri ) . '</code>',
					'<code>style.css</code>'
				) . $are_you_in_the_right_place );
			}
		}

		// We know it's the correct author, now we can check if it's suspended.
		if ( ! empty( $this->theme_post ) && 'suspend' === $this->theme_post->post_status ) {
			/* translators: %s: mailto link */
			array_push( $style_errors, sprintf( __( 'This theme is suspended from the Theme Repository and it can&rsquo;t be updated. If you have any questions about this please contact %s.', 'wporg-themes' ),
				'<a href="mailto:themes@wordpress.org">themes@wordpress.org</a>'
			) );
		}

		// Make sure we have version that is higher than any previously uploaded version of this theme. This check happens last to allow the non-author blocks to kick in.
		if ( ! empty( $this->theme_post ) && ! version_compare( $this->theme->get( 'Version' ), $this->theme_post->max_version, '>' ) ) {
			/* translators: 1: theme name, 2: theme version, 3: style.css */
			array_push( $style_errors, sprintf( __( 'You need to upload a version of %1$s higher than %2$s. Increase the theme version number in %3$s, then upload your zip file again.', 'wporg-themes' ),
				$this->theme->display( 'Name' ),
				'<code>' . $this->theme_post->max_version . '</code>',
				'<code>style.css</code>'
			) );
		}

		// If we had any issues with information in the style.css, exit early.
		if ( ! empty( $style_errors ) ) {
			return $style_errors;
		}

		// Don't send special themes through Theme Check.
		if ( ! has_category( 'special-case-theme', $this->theme_post ) ) {
			// Pass it through Theme Check and see how great this theme really is.
			$result = $this->check_theme( $theme_files );

			if ( ! $result ) {
				// Log it to slack.
				$this->log_to_slack( 'blocked' );

				/* translators: 1: Theme Check Plugin URL, 2: make.wordpress.org/themes */
				return sprintf( __( 'Your theme has failed the theme check. Please correct the problems with it and upload it again. You can also use the <a href="%1$s">Theme Check Plugin</a> to test your theme before uploading. If you have any questions about this please post them to %2$s.', 'wporg-themes' ),
					'//wordpress.org/plugins/theme-check/',
					'<a href="https://make.wordpress.org/themes">https://make.wordpress.org/themes</a>'
				);
			}
		}

		// Passed all tests!
		// Let's save everything and get things wrapped up.

		// Create a new version in SVN.
		$result = $this->add_to_svn();
		if ( ! $result ) {
			/* translators: %s: mailto link */
			return sprintf( __( 'There was an error adding your theme to SVN. Please try again, if this error persists report the error to %s.', 'wporg-themes' ),
				'<a href="mailto:themes@wordpress.org">themes@wordpress.org</a>'
			);
		}

		// Get all Trac ticket information set up.
		$this->prepare_trac_ticket();

		// Talk to Trac and let them know about our new version. Or new theme.
		$ticket_id = $this->create_or_update_trac_ticket();

		if ( ! $ticket_id  ) {
			// Since it's been added to SVN at this point, remove it from SVN to prevent future issues.
			$this->remove_from_svn( 'Trac ticket creation failed.' );

			/* translators: %s: mailto link */
			return sprintf( __( 'There was an error creating a Trac ticket for your theme, please report this error to %s', 'wporg-themes' ),
				'<a href="mailto:themes@wordpress.org">themes@wordpress.org</a>'
			);
		}

		$this->trac_ticket->id = $ticket_id;

		// Add a or update the Theme Directory entry for this theme.
		$this->create_or_update_theme_post( $ticket_id );

		// Send theme author an email for peace of mind.
		$this->send_email_notification( $ticket_id );

		do_action( 'theme_upload', $this->theme, $this->theme_post );

		// Log it to slack.
		$this->log_to_slack( 'allowed' );

		// Initiate a GitHub actions run for the theme.
		$this->trigger_e2e_run( $ticket_id );

		// Success!
		/* translators: 1: theme name, 2: Trac ticket URL */
		return sprintf( __( 'Thank you for uploading %1$s to the WordPress Theme Directory. We&rsquo;ve sent you an email verifying that we&rsquo;ve received it. Feedback will be provided at <a href="%2$s">%2$s</a>', 'wporg-themes' ),
			$this->theme->display( 'Name' ),
			esc_url( 'https://themes.trac.wordpress.org/ticket/' . $ticket_id )
		);
	}

	/**
	 * Creates a temporary directory, and the theme dir within it.
	 */
	public function create_tmp_dirs( $base_name ) {
		// Create a temporary directory if it doesn't exist yet.
		$tmp = self::TMP;
		if ( ! is_dir( $tmp ) ) {
			mkdir( $tmp );
			chmod( $tmp, 0777 );
		}

		// Create file with unique file name.
		$this->tmp_dir = tempnam( $tmp, 'WPORG_THEME_' );

		// Remove that file.
		unlink( $this->tmp_dir );

		// Create a directory with that unique name.
		mkdir( $this->tmp_dir );
		chmod( $this->tmp_dir, 0777 );

		// Get a sanitized name for that theme and create a directory for it.
		$base_name         = $this->get_sanitized_zip_name( $base_name );
		$this->theme_dir   = "{$this->tmp_dir}/{$base_name}";
		$this->tmp_svn_dir = "{$this->tmp_dir}/svn";
		mkdir( $this->theme_dir );
		mkdir( $this->tmp_svn_dir );
		chmod( $this->theme_dir, 0777 );
		chmod( $this->tmp_svn_dir, 0777 );

		// Make sure we clean up after ourselves.
		add_action( 'shutdown', array( $this, 'remove_files' ) );
	}

	/**
	 * Unzips the uploaded theme and saves it in the temporary theme dir.
	 */
	public function unzip_package( $file ) {
		$base_name = $this->get_sanitized_zip_name( $file['name'] );
		$zip_file  = "{$this->tmp_dir}/{$base_name}.zip";

		// Move the uploaded zip into the temporary directory.
		move_uploaded_file( $file['tmp_name'], $zip_file );

		$unzip     = escapeshellarg( self::UNZIP );
		$zip_file  = escapeshellarg( $zip_file );
		$tmp_dir   = escapeshellarg( $this->tmp_dir );

		// Unzip it into the theme directory.
		$this->exec_with_notify( escapeshellcmd( "{$unzip} -DDn {$zip_file} -d {$tmp_dir}/{$base_name}" ) );

		// Fix any permissions issues with the files. Sets 755 on directories, 644 on files.
		$this->exec_with_notify( escapeshellcmd( "chmod -R 755 {$tmp_dir}/{$base_name}" ) );
		$this->exec_with_notify( escapeshellcmd( "find {$tmp_dir}/{$base_name} -type f -print0" ) . ' | xargs -I% -0 chmod 644 %' );

		// Remove any unexpected entries, we only need basic files and directories, anything else will cause problems when installed onto a site.
		$this->exec_with_notify( escapeshellcmd( "find {$tmp_dir}/{$base_name} -not -type f -not -type d -delete" ) );
	}

	/**
	 * Find the first style.css file with the shortest path.
	 *
	 * @param array $theme_files
	 * @return string
	 */
	public function get_style_css( $theme_files ) {
		$stylesheets = preg_grep( '/style.css/', $theme_files );
		usort( $stylesheets, array( $this, 'sort_by_string_length' ) );

		return (string) array_pop( $stylesheets );
	}

	/**
	 * Find the first readme.txt file, and extract it's headers.
	 *
	 * @param array $theme_files The theme files.
	 * @return array Array of headers if present.
	 */
	public function get_readme_data( $theme_files ) {
		$readmes = preg_grep( '/readme.txt/i', $theme_files );
		usort( $readmes, array( $this, 'sort_by_string_length' ) );

		if ( ! $readmes ) {
			return array();
		}

		$readme_txt = (string) array_pop( $readmes );

		// WARNING: This function call will be changed in the future to a readme parser.
		$data = get_file_data( $readme_txt, $this->readme_header_fields );
		$data = array_filter( $data, 'strlen' );

		// Sanitize Contributors down to a user_nicename
		if ( isset( $data['contributors'] ) ) {
			$data['contributors'] = explode( ',', $data['contributors'] );
			$data['contributors'] = array_map( 'trim', $data['contributors'] );
			foreach ( $data['contributors'] as $i => $name ) {
				$user = get_user_by( 'login', $name ) ?: get_user_by( 'slug', $name );

				if ( $user ) {
					$data['contributors'][ $i ] = $user->user_nicename;
				} else {
					unset( $data['contributors'][ $i ] );
				}
			}

			if ( ! $data['contributors'] ) {
				unset( $data['contributors'] );
			}
		}

		if ( isset( $data['tested'] ) ) {
			$data['tested'] = $this->sanitize_version_like_field( $data['tested'], 'tested' );
			if ( ! $data['tested'] ) {
				unset( $data['tested'] );
			}
		}

		return $data;
	}

	/**
	 * Sanitize/strip a field back to it's bare-basics version-like string.
	 *
	 * @param string $value The field value.
	 * @param string $field The name of the field being processed.
	 * @return bool|string The version-like field or false on failure.
	 */
	public function sanitize_version_like_field( $value, $field = false ) {
		// Strip 'WP', 'WordPress', and 'PHP' from the fields.
		$value = trim( str_ireplace( array( 'PHP', 'WP', 'WordPress', '+' ), '', $value ) );

		// Require a version-like value, x.y or x.y.z
		if ( ! preg_match( '!^\d+\.\d(\.\d+)?$!', $value ) ) {
			return false;
		}

		// Allow themes to mark themselves as compatible with Stable+0.1 (trunk/master) but not higher
		if (
			( 'requires' === $field || 'tested' === $field ) &&
			defined( 'WP_CORE_STABLE_BRANCH' ) &&
			version_compare( (float)$value, (float)WP_CORE_STABLE_BRANCH+0.1, '>' )
		) {
			return false;
		}

		return $value;
	}

	/**
	 * Returns the the theme post if it already exists in the repository.
	 *
	 * @return WP_Post|null
	 */
	public function get_theme_post() {
		$themes = get_posts( array(
			'name'             => $this->theme_slug,
			'posts_per_page'   => 1,
			'post_type'        => 'repopackage',
			'orderby'          => 'ID',

			/*
			 * Specify post stati so this query returns a result for draft themes, even
			 * if the uploading user doesn't have have the permission to view drafts.
			 */
			'post_status'      => array( 'publish', 'pending', 'draft', 'future', 'trash', 'suspend', 'delist' ),
			'suppress_filters' => false,
		) );
		$theme = current( $themes );

		if ( ! empty( $theme ) ) {
			$theme = $this->populate_post_with_meta( $theme );
		}

		return $theme;
	}

	/**
	 * Find the first screen shot file with the shortest path.
	 *
	 * Also adds the file extension to the theme information object for later use.
	 *
	 * @param array $theme_files
	 * @return bool
	 */
	public function has_screenshot( $theme_files ) {
		$screenshots = preg_grep( '/screenshot.(jpg|jpeg|png|gif)/', $theme_files );
		usort( $screenshots, array( $this, 'sort_by_string_length' ) );
		$screenshot = array_pop( $screenshots );

		$this->theme->screenshot = basename( $screenshot );

		return (bool) $this->theme->screenshot;
	}

	/**
	 * Whether the parent theme for this theme is available in the repository.
	 *
	 * @return bool
	 */
	public function is_parent_available() {
		$parent = get_posts( array(
			'fields'           => 'ids',
			'name'             => $this->theme->get_template(),
			'posts_per_page'   => 1,
			'post_type'        => 'repopackage',
			'orderby'          => 'ID',
			'suppress_filters' => false,
		) );
		$this->theme->post_parent = current( $parent );

		return ! empty( $parent );
	}

	/**
	 * Whether the uploaded theme uses a reserved slug.
	 *
	 * Passes if the author happens to be `wordpressdotorg`.
	 *
	 * @return bool
	 */
	public function has_reserved_slug() {
		$slug = str_replace(
			array( 'twenty-ten', 'twenty-eleven', 'twenty-twelve', 'twenty-thirteen', 'twenty-fourteen', 'twenty-fifteen', 'twenty-sixteen', 'twenty-seventeen', 'twenty-eighteen', 'twenty-nineteen', 'twenty-twenty-one', 'twenty-twenty-two', 'twenty-twenty-three', 'twenty-twenty-four', 'twenty-twenty-five', 'twenty-twenty-six', 'twenty-twenty-seven', 'twenty-twenty-eight', 'twenty-twenty-nine', 'twenty-thirty', 'twenty-twenty'),
			array( 'twentyten',  'twentyeleven',  'twentytwelve',  'twentythirteen',  'twentyfourteen',  'twentyfifteen',  'twentysixteen',  'twentyseventeen',  'twentyeighteen',  'twentynineteen', 'twentytwentyone', 'twentytwentytwo', 'twentytwentythree', 'twentytwentyfour',  'twentytwentyfive', 'twentytwentysix', 'twentytwentyseven', 'twentytwentyeight', 'twentytwentynine', 'twentythirty', 'twentytwenty' ),
			$this->theme_slug
		);

		$reserved_slugs = array(
			// Reserve "twenty" names for wordpressdotorg.
			'twentyten', 'twentyeleven', 'twentytwelve','twentythirteen', 'twentyfourteen', 'twentyfifteen',
			'twentysixteen', 'twentyseventeen','twentyeighteen', 'twentynineteen', 'twentytwenty',
			'twentytwentyone', 'twentytwentytwo', 'twentytwentythree', 'twentytwentyfour', 'twentytwentyfive',
			'twentytwentysix', 'twentytwentyseven', 'twentytwentyeight', 'twentytwentynine', 'twentythirty',
			// Theme Showcase URL parameters.
			'browse', 'tag', 'search', 'filter', 'upload', 'commercial',
			'featured', 'popular', 'new', 'updated',
		);

		// If it's not a reserved slug, they can have it.
		if (
			! in_array( $slug, $reserved_slugs, true ) &&
			! in_array( $this->theme_slug, $reserved_slugs, true )
		) {
			return false;
		}

		// force the slug to be correct for the twenty-x themes.
		$this->theme_slug = $slug;

		// WordPress.org user is always allowed to upload reserved slugs.
		if ( 'wordpressdotorg' === $this->author->user_login ) {
			return false;
		}

		// Only committers uploading a default theme *update* are left to be checked for.
		$theme_post = $this->get_theme_post();

		// New default themes MUST be uploaded by `wordpressdotorg` and will fail this check.
		if (
			// Updates only.
			$theme_post &&
			// The current user is a Core Committer. [ 'user_login' => 'Trac Title', ... ]
			! empty( $GLOBALS['committers'][ $this->author->user_login ] ) &&
			// The theme is owned by WordPress.org.
			'wordpressdotorg' === get_user_by( 'id', $theme_post->post_author )->user_login
		) {
			// Slug is reserved, but an update is being uploaded by a core committer.
			return false;
		}

		// Slug is reserved, user is not authorized.
		return true;
	}

	/**
	 * Sends a theme through Theme Check.
	 *
	 * @param array $files All theme files to check.
	 * @return bool Whether the theme passed the checks.
	 */
	public function check_theme( $files ) {
		// Load the theme checking code.
		if ( ! function_exists( 'run_themechecks_against_theme' ) ) {
			include_once WP_PLUGIN_DIR . '/theme-check/checkbase.php';
		}

		// Run the checks.
		$result = run_themechecks_against_theme( $this->theme, $this->theme_slug );

		// Display the errors.
		$verdict = $result ? array( 'tc-pass', __( 'Pass', 'wporg-themes' ) ) : array( 'tc-fail', __( 'Fail', 'wporg-themes' ) );
		echo '<h4>' . sprintf( __( 'Results of Automated Theme Scanning: %s', 'wporg-themes' ), vsprintf( '<span class="%1$s">%2$s</span>', $verdict ) ) . '</h4>';
		echo '<ul class="tc-result">' . display_themechecks() . '</ul>';
		echo '<div class="notice notice-info"><p>' . __( 'Note: While the automated theme scan is based on the Theme Review Guidelines, it is not a complete review. A successful result from the scan does not guarantee that the theme will pass review. All submitted themes are reviewed manually before approval.', 'wporg-themes' ) . '</p></div>';

		// Override ALL of the upload checks for child themes.
		if ( $this->theme->parent() ) {
			$result = true;
		}

		return $result;
	}

	/**
	 * Sets up all Trac ticket information that we need later.
	 */
	public function prepare_trac_ticket() {
		$this->trac_ticket = new StdClass;

		$this->trac_ticket->resolution = '';

		// todo - check trac xml-rpc, maybe data needs to be escaped before sending it there.
		$this->trac_ticket->summary = sprintf( 'THEME: %1$s – %2$s', $this->theme->display( 'Name' ), $this->theme->display( 'Version' ) );

		// Keywords
		$this->trac_ticket->keywords = array(
			'theme-' . $this->theme_slug,
		);

		$this->trac_ticket->parent_link = '';
		if ( $this->theme->parent() ) {
			if ( in_array( 'buddypress', $this->theme->get( 'Tags' ) ) ) {
				$this->trac_ticket->keywords[] = 'buddypress';
			} else {
				$this->trac_ticket->keywords[]  = 'child-theme';
				$this->trac_ticket->keywords[]  = 'parent-' . $this->theme->get_template();
				$this->trac_ticket->parent_link = "Parent Theme: https://wordpress.org/themes/{$this->theme->get_template()}";
			}
		}

		if ( in_array( 'accessibility-ready', $this->theme->get( 'Tags' ) ) ) {
			$this->trac_ticket->keywords[] = 'accessibility-ready';
		}

		if ( in_array( 'full-site-editing', $this->theme->get( 'Tags' ) ) ) {
			$this->trac_ticket->keywords[] = 'full-site-editing';
		}

		if ( in_array( 'holiday', $this->theme->get( 'Tags' ) ) ) {
			$this->trac_ticket->keywords[] = 'holiday';
		}

		// Priority
		$this->trac_ticket->priority = 'new theme';
		if ( ! empty( $this->theme_post->_status ) ) {

			// Is this an update to an existing, approved theme?
			if ( 'live' === $this->theme_post->_status[ $this->theme_post->max_version ] ) {
				$this->trac_ticket->priority = 'theme update';

				// Apparently not, it must be a new upload for previously unapproved theme.
			} else {
				$this->trac_ticket->priority = 'previously reviewed';
			}
		}

		// Diff line.
		$this->trac_ticket->diff_line = '';
		if ( ! empty( $this->theme_post->max_version ) ) {
			$this->trac_ticket->diff_line = "\nDiff with previous version: [{$this->trac_changeset}] https://themes.trac.wordpress.org/changeset?old_path={$this->theme_slug}/{$this->theme_post->max_version}&new_path={$this->theme_slug}/{$this->theme->display( 'Version' )}\n";
		}

		// Description
		$theme_description = $this->strip_non_utf8( (string) $this->theme->display( 'Description' ) );

		// Hacky way to prevent a problem with xml-rpc.
		$this->trac_ticket->description = <<<TICKET
{$this->theme->display( 'Name' )} - {$this->theme->display( 'Version' )}

{$theme_description}

Theme URL - {$this->theme->display( 'ThemeURI' )}
Author URL - {$this->theme->display( 'AuthorURI' )}

Trac Browser - https://themes.trac.wordpress.org/browser/{$this->theme_slug}/{$this->theme->display( 'Version' )}
WordPress.org - https://wordpress.org/themes/{$this->theme_slug}/

SVN - https://themes.svn.wordpress.org/{$this->theme_slug}/{$this->theme->display( 'Version' )}
ZIP - https://wordpress.org/themes/download/{$this->theme_slug}.{$this->theme->display( 'Version' )}.zip?nostats=1
{$this->trac_ticket->parent_link}
{$this->trac_ticket->diff_line}
History:
[[TicketQuery(format=table, keywords=~theme-{$this->theme_slug}, col=id|summary|status|resolution|owner)]]

[[Image(https://themes.svn.wordpress.org/{$this->theme_slug}/{$this->theme->display( 'Version' )}/{$this->theme->screenshot}, width=640)]]
TICKET;

		$theme_check_results = $this->generate_themecheck_results_for_trac();
		if ( $theme_check_results ) {
			$this->trac_ticket->description .= "\nTheme Check Results:\n" . $theme_check_results;
		}

	}

	/*
	 * Add a comment to the trac ticket with the results of the theme check, if any exists.
	 */
	public function generate_themecheck_results_for_trac() {
		global $themechecks;
		if ( empty( $themechecks ) ) {
			return '';
		}

		$tc_errors = $tc_results = array();
		foreach ( $themechecks as $check ) {
			if ( $check instanceof \themecheck ) {
				$error = (array) $check->getError();
				if ( $error ) {
					$tc_errors = array_unique( array_merge( $tc_errors, $error ) );
				}
			}
		}

		if ( $tc_errors ) {
			foreach ( $tc_errors as $e ) {
				$trac_left = array( '<strong>', '</strong>' );
				$trac_right= array( "'''", "'''" );
				$html_link = '/<a\s?href\s?=\s?[\'|"]([^"|\']*)[\'|"]>([^<]*)<\/a>/i';
				$html_new = '[$1 $2]';
				$e = preg_replace( $html_link, $html_new, $e );
				$e = str_replace( $trac_left, $trac_right, $e );
				$e = preg_replace( '/<pre.*?>/', "\r\n{{{\r\n", $e );
				$e = str_replace( '</pre>', "\r\n}}}\r\n", $e );
				$e = preg_replace( '!<span class=[^>]+>([^<]+)</span>!', '$1', $e );
				$e = str_replace( '<br>', ' ', $e );

				// Decode some entities.
				$e = preg_replace_callback( '!(&[lg]t;)!', function( $f ) {
					return html_entity_decode( $f[0] );
				}, $e );

				if ( 'INFO' !== substr( $e, 0, 4 ) ) {
					$tc_results[] = '* ' . $e;
				}
			}
		}

		if ( ! $tc_results ) {
			return '';
		}

		return implode( "\n", $tc_results );
	}


	/**
	 * Updates an existing Trac ticket or creates a new one.
	 *
	 * @return bool|int Ticket ID on success, false on failure.
	 */
	public function create_or_update_trac_ticket() {
		// Set up a way to communicate with Trac.
		if ( empty( $this->trac ) ) {
			if ( ! class_exists( 'Trac' ) ) {
				require_once ABSPATH . WPINC . '/class-IXR.php';
				require_once ABSPATH . WPINC . '/class-wp-http-ixr-client.php';
				require_once __DIR__ . '/lib/class-trac.php';
			}

			$this->trac = new Trac( 'themetracbot', THEME_TRACBOT_PASSWORD, 'https://themes.trac.wordpress.org/login/xmlrpc' );
		}

		// If there's a previous version and the most current version's status is `new`, we update.
		if ( ! empty( $this->theme_post->max_version ) && 'new' == $this->theme_post->_status[ $this->theme_post->max_version ] ) {
			$ticket_id = (int) $this->theme_post->_ticket_id[ $this->theme_post->max_version ];
			$ticket    = $this->trac->ticket_get( $ticket_id );

			// Make sure the ticket has not yet been resolved.
			if ( $ticket && empty( $ticket[3]['resolution'] ) ) {
				$result    = $this->trac->ticket_update( $ticket_id, $this->trac_ticket->description, array( 'summary' => $this->trac_ticket->summary, 'keywords' => implode( ' ', $this->trac_ticket->keywords ) ), true /* Trigger email notifications */ );
				$ticket_id = $result ? $ticket_id : false;
			} else {
				$ticket_id = $this->trac->ticket_create( $this->trac_ticket->summary, $this->trac_ticket->description, array(
					'type'      => 'theme',
					'keywords'  => implode( ' ', $this->trac_ticket->keywords ),
					'reporter'  => $this->author->user_login,
					'cc'        => $this->author->user_email,
					'priority'  => $this->trac_ticket->priority,
					'owner'     => '',
				) );
			}

			// In all other cases we create a new ticket.
		} else {
			$ticket_id = $this->trac->ticket_create( $this->trac_ticket->summary, $this->trac_ticket->description, array(
				'type'      => 'theme',
				'keywords'  => implode( ' ', $this->trac_ticket->keywords ),
				'reporter'  => $this->author->user_login,
				'cc'        => $this->author->user_email,
				'priority'  => $this->trac_ticket->priority,
				'owner'     => '',
			) );

			// Theme review team auto-approves theme-updates, so mark the theme as live immediately.
			// Note that this only applies to new ticket creation, so it won't happen on themes with existing outstanding tickets
			if ( $this->trac_ticket->priority == 'theme update' ) {
				$this->trac->ticket_update( $ticket_id, 'Theme Update for existing Live theme - automatically reviewed & approved', array( 'action' => 'new_no_review' ), false );

				$this->trac_ticket->resolution = 'live';
			}

		}

		return $ticket_id;
	}

	/**
	 * Creates or updates a theme post.
	 *
	 * @param int $ticket_id Trac ticket ID
	 */
	public function create_or_update_theme_post( $ticket_id ) {
		$upload_date = current_time( 'mysql' );

		// If we already have a post, get its ID.
		if ( ! empty( $this->theme_post ) ) {
			$post_id = $this->theme_post->ID;
			// see wporg_themes_approve_version() for where the post is updated.

		// Otherwise create it for this new theme.
		} else {
			$post_id = wp_insert_post( array(
				'post_author'    => $this->author->ID,
				'post_title'     => $this->theme->get( 'Name' ),
				'post_name'      => $this->theme_slug,
				'post_content'   => $this->theme->get( 'Description' ),
				'post_parent'    => $this->theme->post_parent,
				'post_date'      => $upload_date,
				'post_date_gmt'  => $upload_date,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'post_type'      => 'repopackage',
				'tags_input'     => $this->theme->get( 'Tags' ),
			) );
		}

		// Finally, add post meta.
		$post_meta = array(
			'_theme_url'    => $this->theme->get( 'ThemeURI' ),
			'_author'       => $this->theme->get( 'Author' ),
			'_author_url'   => $this->theme->get( 'AuthorURI' ),
			'_requires'     => $this->sanitize_version_like_field( $this->theme->get( 'RequiresWP' ), 'requires' ),
			'_requires_php' => $this->sanitize_version_like_field( $this->theme->get( 'RequiresPHP' ) ),
			'_upload_date'  => $upload_date,
			'_ticket_id'    => $ticket_id,
			'_screenshot'   => $this->theme->screenshot,
		);

		// Store readme.txt data if present.
		foreach ( $this->readme as $field => $data ) {
			$post_meta[ "_{$field}" ] = $data;
		}

		foreach ( $post_meta as $meta_key => $meta_value ) {
			$meta_data = array_filter( (array) get_post_meta( $post_id, $meta_key, true ) );
			$meta_data[ $this->theme->get( 'Version' ) ] = $meta_value;
			update_post_meta( $post_id, $meta_key, $meta_data );
		}

		// Add an additional row with the trac ticket ID, to make it possible to find the post by this ID later.
		add_post_meta( $post_id, sanitize_key( '_trac_ticket_' . $this->theme->get( 'Version' ) ), $ticket_id );

		// Discard versions that are awaiting review, and maybe set this upload as live.
		$version_status = 'new';
		if ( ! empty( $this->trac_ticket->resolution ) && 'live' === $this->trac_ticket->resolution ) {
			$version_status = 'live';
		}
		wporg_themes_update_version_status( $post_id, $this->theme->get( 'Version' ), $version_status );
	}

	/**
	 * Add theme files to SVN.
	 * 
	 * This attempts to do a SVN copy to allow for simpler diff views, but falls back to a svn import as an error condition.
	 */
	public function add_to_svn() {
		// Either new theme upload, or we don't have the needed variables to copy it directly.
		if ( empty( $this->theme_post ) || empty( $this->theme_post->max_version ) ) {
			return $this->add_to_svn_via_svn_import();
		}

		$new_version_dir = escapeshellarg( "{$this->tmp_svn_dir}/{$this->theme->display( 'Version' )}" );

		// Keeps a copy of the output of the commands for debugging.
		$output = array();

		// Theme exists, attempt to do a copy from old version to new.
		$this->exec_with_notify( self::SVN . " co https://themes.svn.wordpress.org/{$this->theme_slug}/ {$this->tmp_svn_dir} --depth=empty", $output, $return_var );
		if ( $return_var > 0 ) {
			return $this->add_to_svn_via_svn_import();
		}

		// Try to copy the previous version over.
		$prev_version = escapeshellarg( "https://themes.svn.wordpress.org/{$this->theme_slug}/{$this->theme_post->max_version}" );
		$this->exec_with_notify( self::SVN . " cp $prev_version $new_version_dir", $output, $return_var );
		if ( $return_var > 0 ) {
			return $this->add_to_svn_via_svn_import();
		}

		// Remove the files from the old version, so that we can track file removals.
		$this->exec_with_notify( self::RM . " -rf {$new_version_dir}/*", $output );

		$theme_dir = escapeshellarg( $this->theme_dir );
		$this->exec_with_notify( self::CP . " -R {$theme_dir}/* {$new_version_dir}", $output );

		// Process file additions and removals.
		$this->exec_with_notify( self::SVN . " st {$new_version_dir} | grep '^?' | cut -c 2- | xargs -I% " . self::SVN . " add '%@'", $output );
		$this->exec_with_notify( self::SVN . " st {$new_version_dir} | grep '^!' | cut -c 2- | xargs -I% " . self::SVN . " rm '%@'", $output );

		// Commit it to SVN.
		$password = escapeshellarg( THEME_DROPBOX_PASSWORD );
		$message  = escapeshellarg( sprintf(
			'New version of %1$s - %2$s', // Intentionally not translated.
			$this->theme->display( 'Name' ),
			$this->theme->display( 'Version' )
		) );

		/* DEBUGGING
			dump the output of $output here and return false;
		*/

		$last_line = $this->exec_with_notify( self::SVN . " ci --non-interactive --username themedropbox --password {$password} -m {$message} {$new_version_dir}", $output, $return_var );
		if ( $return_var > 0 ) {
			return $this->add_to_svn_via_svn_import();
		}

		if ( preg_match( '/Committed revision (\d+)\./i', $last_line, $m ) ) {
			$this->trac_changeset = $m[1];
			return true;
		}

		return false;
	}

	/**
	 * Add the theme files to SVN via svn import.
	 */
	function add_to_svn_via_svn_import() {
		$import_msg = empty( $this->theme_post ) ?  'New theme: %1$s - %2$s' : 'New version of %1$s - %2$s'; // Intentionally not translated
		$import_msg = escapeshellarg( sprintf( $import_msg, $this->theme->display( 'Name' ), $this->theme->display( 'Version' ) ) );
		$svn_path   = escapeshellarg( "https://themes.svn.wordpress.org/{$this->theme_slug}/{$this->theme->display( 'Version' )}" );
		$theme_path = escapeshellarg( $this->theme_dir );
		$password   = escapeshellarg( THEME_DROPBOX_PASSWORD );

		$last_line = $this->exec_with_notify( self::SVN . " --non-interactive --username themedropbox --password {$password} --no-auto-props -m {$import_msg} import {$theme_path} {$svn_path}" );

		if ( preg_match( '/Committed revision (\d+)\./i', $last_line, $m ) ) {
			$this->trac_changeset = $m[1];
			return true;
		}

		return false;
	}

	/**
	 * Remove a theme version commited to SVN.
	 */
	function remove_from_svn( $reason ) {
		$svn_path = "{$this->theme_slug}/{$this->theme->display( 'Version' )}";
		if ( ! $this->theme_slug || ! $this->theme->display( 'Version' ) || strlen( $svn_path ) < 3 ) {
			return false;
		}

		$import_msg = 'Removing theme %1$s - %2$s: %3$s';
		$import_msg = escapeshellarg( sprintf( $import_msg, $this->theme->display( 'Name' ), $this->theme->display( 'Version' ), $reason ) );
		$svn_path   = escapeshellarg( "https://themes.svn.wordpress.org/{$svn_path}" );
		$password   = escapeshellarg( THEME_DROPBOX_PASSWORD );

		$last_line = $this->exec_with_notify( self::SVN . " --non-interactive --username themedropbox --password {$password} -m {$import_msg} rm {$svn_path}" );

		return (bool) preg_match( '/Committed revision (\d+)\./i', $last_line );
	}

	/**
	 * Sends out an email confirmation to the theme's author.
	 *
	 * @param int $ticket_id Trac ticket ID
	 */
	public function send_email_notification( $ticket_id ) {
		if ( ! empty( $this->theme_post ) ) {

			if (
				! empty( $this->trac_ticket->resolution ) &&
				'live' === $this->trac_ticket->resolution
			) {
				// Do nothing. The update has been set as live. No need to let them know it's been uploaded.
				// wporg_themes_approve_version() will send a "Congratulations! It's live!" email momentarily.
				return;
			}

			/* translators: 1: theme name, 2: theme version */
			$email_subject = sprintf( __( '[WordPress Themes] %1$s, new version %2$s', 'wporg-themes' ),
				$this->theme->display( 'Name' ),
				$this->theme->display( 'Version' )
			);

			/* translators: 1: theme version, 2: theme name, 3: Trac ticket URL */
			$email_content = sprintf( __( 'Thank you for uploading version %1$s of %2$s.

Feedback will be provided at %3$s

--
The WordPress.org Themes Team
https://make.wordpress.org/themes', 'wporg-themes' ),
				$this->theme->display( 'Version' ),
				$this->theme->display( 'Name' ),
				'https://themes.trac.wordpress.org/ticket/' . $ticket_id
			);
		} else {
			/* translators: %s: theme name */
			$email_subject = sprintf( __( '[WordPress Themes] New Theme - %s', 'wporg-themes' ),
				$this->theme->display( 'Name' )
			);

			/* translators: 1: theme name, 2: Trac ticket URL */
			$email_content = sprintf( __( 'Thank you for uploading %1$s to the WordPress Theme Directory. A ticket has been created for the review:
<%2$s>

** Requirements **
The theme must pass all the requirements to be included in the Theme Directory. The ticket will be closed if three or more different errors are found.
<https://make.wordpress.org/themes/handbook/review/required/>

** Review Process **
To understand the review process, read the summary in our handbook:
<https://make.wordpress.org/themes/handbook/review/>

** Accessibility Ready **
If you\'ve submitted a theme with the accessibility ready tag, it will go through a secondary review process to meet accessibility guidelines.
Please review the guidelines in our handbook:
<https://make.wordpress.org/themes/handbook/review/accessibility/>

** Theme Updates **
You can update your theme any time and it will be added to the ticket. You can do so by bumping up your theme\'s version number in your style.css and uploading a new ZIP file.
<https://wordpress.org/themes/getting-started/>

** Contribute! **
You can help speed up the process by making sure that your theme follows all of the requirements. You can also help by becoming a reviewer.
<https://make.wordpress.org/themes/handbook/get-involved/become-a-reviewer/>

** Questions? **
If you have questions you can ask the reviewer in the ticket or chat with us on Slack in the #themereview channel. <https://chat.wordpress.org/>

Subscribe to the Theme Review blog to stay up to date with the latest requirements and the ongoing work to improve the review process:
<https://make.wordpress.org/themes/>

Thank you.
The WordPress Theme Review Team', 'wporg-themes' ),
				$this->theme->display( 'Name' ),
				'https://themes.trac.wordpress.org/ticket/' . $ticket_id
			);
		}

		$emails = [
			$this->author->user_email,
		];

		// If the uploader and the author are different, email them both.
		// This only happens under special circumstances.
		if ( ! empty( $this->theme_post ) && $this->theme_post->post_author != $this->author->ID ) {
			$emails[] = get_user_by( 'id', $this->theme_post->post_author )->user_email;
		}

		wp_mail( $emails, $email_subject, $email_content, 'From: "WordPress Theme Directory" <themes@wordpress.org>' );
	}

	/**
	 * Triggers a GitHub actions run for the upload.
	 */
	public function trigger_e2e_run( $ticket_id ) {
		$api = GitHub::api(
			'/repos/' . WPORG_THEMES_E2E_REPO . '/dispatches',
			json_encode([
				'event_type'     => sprintf(
					"%s %s %s",
					$this->theme->display( 'Name' ),
					$this->theme->display( 'Version' ),
					$this->trac_ticket->priority
				),
				'client_payload' => [
					'theme_slug'       => $this->theme_slug,
					'theme_zip'        => "https://wordpress.org/themes/download/{$this->theme_slug}.{$this->theme->display( 'Version' )}.zip?nostats=1",
					'accessible_ready' => in_array( 'accessibility-ready', $this->theme->get( 'Tags' ) ),
					'trac_ticket_id'   => $ticket_id,
					'trac_priority'    => $this->trac_ticket->priority,
				],
			])
		);
	
		// Upon failure a message is returned, success returns nothing.
		return empty( $api );
	}

	// Helper

	/**
	 * Returns a sanitized version of the uploaded zip file name.
	 *
	 * @return string
	 */
	public function get_sanitized_zip_name( $name ) {
		return preg_replace( '|\W|', '', strtolower( basename( $name, '.zip') ) );
	}

	/**
	 * Returns all (usable) files of a given directory.
	 *
	 * @param string $dir Path to directory to search.
	 * @return array All files within the passed directory.
	 */
	public function get_all_files( $dir ) {
		$files        = array();
		$dir_iterator = new RecursiveDirectoryIterator( $dir );
		$iterator     = new RecursiveIteratorIterator( $dir_iterator, RecursiveIteratorIterator::SELF_FIRST );

		foreach ( $iterator as $file ) {
			// Only return files that are no directory references or Mac resource forks.
			if ( $file->isFile() && ! in_array( $file->getBasename(), array( '..', '.' ) ) && ! stristr( $file->getPathname(), '__MACOSX' ) ) {
				array_push( $files, $file->getPathname() );
			}
		}

		return $files;
	}

	/**
	 * Populates a theme post with its meta data.
	 *
	 * @param WP_Theme $theme
	 * @return WP_Theme
	 */
	public function populate_post_with_meta( $theme ) {
		foreach ( get_post_custom_keys( $theme->ID ) as $meta_key ) {
			$theme->$meta_key = get_post_meta( $theme->ID, $meta_key, true );

			if ( is_array( $theme->$meta_key ) ) {
				ksort( $theme->$meta_key, SORT_NATURAL );
			}
		}

		// Save the highest recorded version number.
		$uploaded_versions  = array_keys( $theme->_status );
		$theme->max_version = end( $uploaded_versions );

		return $theme;
	}

	/**
	 * Deletes the temporary directory.
	 */
	public function remove_files() {
		$rm    = escapeshellarg( self::RM );
		$files = escapeshellarg( $this->tmp_dir );

		$this->exec_with_notify( escapeshellcmd( "{$rm} -rf {$files}" ) );
	}

	/**
	 * Strips invalid UTF-8 characters.
	 *
	 * Non-UTF-8 characters in theme descriptions will causes blank descriptions in themes.trac.
	 *
	 * @param string $string The string to be converted.
	 * @return string The converted string.
	 */
	protected function strip_non_utf8( $string ) {
		ini_set( 'mbstring.substitute_character', 'none' );

		return mb_convert_encoding( $string, 'UTF-8', 'UTF-8' );
	}

	/**
	 * Helper function to sort strings by their length, favoring the shorter one.
	 *
	 * @param string $a
	 * @param string $b
	 * @return int
	 */
	protected function sort_by_string_length( $a, $b ) {
		return strlen( $b ) - strlen( $a );
	}

	/**
	 * Execute a shell process, same behaviour as `exec()` but with PHP Warnings/Notices generated on errors.
	 * 
	 * @param string $command    Command to execute. Escape it.
	 * @param array  $output     Array to append program output to. Passed by reference.
	 * @param int    $return_var The commands return value. Passed by reference.
	 * 
	 * @return false|string False on failure, last line of output on success, as per exec().
	 */
	public function exec_with_notify( $command, &$output = null, &$return_var = null ) {
		$proc = proc_open(
			$command,
			[
				1 => [ 'pipe', 'w' ], // STDOUT
				2 => [ 'pipe', 'w' ], // STDERR
			],
			$pipes
		);

		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$return_var = proc_close( $proc );

		// Append to $output, as `exec()` does.
		if ( ! is_array( $output ) ) {
			$output = [];
		}
		if ( $stdout ) {
			$output = array_merge( $output, explode( "\n", rtrim( $stdout, "\r\n" ) ) );
		}

		// Redact any passwords that might be in a command and included in logged errors.
		$command = str_replace( [ THEME_TRACBOT_PASSWORD, THEME_DROPBOX_PASSWORD ], '[redacted]', $command );

		if ( $return_var > 0 ) {
			trigger_error(
				"Command failed, `{$command}`\n" .
					"```\n" .
					"Return Value: {$return_var}\n" .
					"STDOUT: {$stdout}\n" .
					"STDERR: {$stderr}\n" .
					"```",
				E_USER_WARNING
			);
		} elseif ( $stderr ) {
			trigger_error(
				"Command produced errors, `{$command}`\n" .
					"```\n" .
					"Return Value: {$return_var}\n" .
					"STDOUT: {$stdout}\n" .
					"STDERR: {$stderr}\n" .
					"```",
				E_USER_NOTICE
			);
		}

		// Execution failed.
		if ( $return_var > 0 ) {
			return false;
		}

		// Successful, return the last output line.
		return $stdout ? end( $output ) : '';
	}

	/**
	 * Log a Theme Upload to the slack `#themereview-firehose` channel.
	 * 
	 * @param string $status Whether the upload was 'allowed' or 'blocked'.
	 */
	public function log_to_slack( $status = 'allowed' ) {
		global $themechecks;

		if ( ! defined( 'THEME_DIRECTORY_SLACK_WEBHOOK' ) || empty( $themechecks ) ) {
			return;
		}

		$errors = array(
			'required'    => [],
			'warning'    => [],
			'recommended' => [],
			'info'        => [],
		);
		foreach ( $themechecks as $check ) {
			if ( $check instanceof themecheck ) {
				$error = $check->getError();

				// Account for namespaces by getting the short name.
				$class = (new \ReflectionClass( $check ))->getShortName();

				// Humanize the class name.
				$class = str_replace( '_', ' ', $class ); // Theme_Check
				$class = preg_replace( '/([a-z])([A-Z][a-z])/', '$1 $2', $class ); // ThemeCheck

				foreach ( (array) $check->getError() as $e ) {
					$type = 'unknown';
					if ( preg_match( '!<span[^>]+(tc-(?P<code>info|required|recommended|warning))!i', $e, $m ) ) {
						$type = $m['code'];
					}

					// Strip the span.
					$e = preg_replace( '!<span[^>]+tc-[^<]+</span>:?\s*!i', '', $e );

					// First sentence only.
					if ( false !== ( $pos = strpos( $e, '. ', 10 ) ) ) {
						$e = substr( $e, 0, $pos + 1 );
					}

					// Strip any remaining tags.
					$e = wp_kses( $e, [ 'strong' => true, 'code' => true, 'em' => true ] );

					// Convert to markdown.
					$e = preg_replace( '!</?(strong)[^>]*>!i', '*', $e );
					$e = preg_replace( '!</?(code)[^>]*>!i', '`', $e );
					$e = preg_replace( '!</?(em)[^>]*>!i', '_', $e );

					if ( empty( $errors[ $type ][ $class ] ) ) {
						$errors[ $type ][ $class ] = [];
					}

					$errors[ $type ][ $class ][] = $e;
				}
			}
		}

		// Hide `TextDomainCheck` Info warning that only a single textdomain is in use.
		unset( $errors[ 'info' ][ 'Text Domain Check' ] );

		$blocks = [];

		// Preamble / header
		if ( $this->theme_post && 'allowed' === $status ) {
			$blocks[] = [
				'type' => 'section',
				'text' => [
					'type' => 'mrkdwn',
					'text' => "*Theme Update: <" . get_permalink( $this->theme_post ) . "|{$this->theme_post->post_title}>*"
				],
				'accessory' => [
					'type'      => 'image',
					'alt_text'  => 'Theme Screenshot',
					'image_url' => sprintf(
						// Slack doesn't like using themes.svn due to forced image download, so use ts.w.org instead.
						'https://ts.w.org/wp-content/themes/%1$s/%2$s?ver=%3$s',
						$this->theme_slug,
						$this->theme->screenshot,
						$this->theme->display( 'Version' )
					),
				],
				'fields' => array_filter( [
					[
						'type' => 'mrkdwn',
						'text' => "*Version:*\n{$this->theme->get('Version')}",
					],
					[
						'type' => 'mrkdwn',
						'text' => "*Priority:*\n{$this->trac_ticket->priority}",
					],
					$this->trac_ticket->resolution ? [
						'type' => 'mrkdwn',
						'text' => "*Resolution:*\n{$this->trac_ticket->resolution}",
					] : null,
					[
						'type' => 'mrkdwn',
						'text' => "*Trac:*\n" . 
							"<https://themes.trac.wordpress.org/ticket/{$this->trac_ticket->id}|#{$this->trac_ticket->id}>" .
							' ' .
							"<https://themes.trac.wordpress.org/changeset/{$this->trac_changeset}|[{$this->trac_changeset}]>",
					]
				] )
			];
		} elseif ( $this->theme_post ) {
			$blocks[] = [
				'type' => 'section',
				'text' => [
					'type' => 'mrkdwn',
					'text' => "*Theme update blocked for {$this->theme_post->post_title}*"
				],
			];
		} else {
			$blocks[] = [
				'type' => 'section',
				'text' => [
					'type' => 'mrkdwn',
					'text' => "*New Theme upload blocked for {$this->theme->get('Name')} {$this->theme->get('Version')}*",
				]
			];
		}

		foreach ( $errors as $type => $error_classes ) {
			if ( ! $error_classes ) {
				continue;
			}

			$blocks[] = [
				'type' => 'header',
				'text' => [
					'type' => 'plain_text',
					'text' => 'Theme Check ' . ucwords( $type ),
				]
			];

			foreach ( $error_classes as $class => $errors ) {
				$section = [
					'type' => 'section',
					'text' => [
						'type' => 'mrkdwn',
						'text' => "*{$class}:*\n" . implode( "\n", array_unique( $errors ) ),
					]
				];

				$blocks[] = $section;
			}
		}

		require_once API_WPORGPATH . 'includes/slack-config.php';
		$send = new \Dotorg\Slack\Send( THEME_DIRECTORY_SLACK_WEBHOOK );
		$send->add_attachment( [ 'blocks' => $blocks ] );

		if ( 'allowed' === $status ) {
			$send->set_username( 'Theme Upload' );
			$send->set_icon( ':themes:' );
		} else {
			$send->set_username( 'Theme Check Blocked Upload' );
			$send->set_icon( ':x:' );
		}

		$send->send( '#themereview-firehose' );
	}
}
