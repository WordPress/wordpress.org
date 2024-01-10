<?php
use WordPressdotorg\Theme_Directory\Lib\GitHub;
use WordPressdotorg\Theme_Directory\Lib\Exec_With_Logging;

/**
 * Class WPORG_Themes_Upload
 *
 * Processes a theme upload.
 */
class WPORG_Themes_Upload {
	use Exec_With_Logging {
		Exec_With_Logging::exec as exec_with_notify;
	}

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
	public $tmp_dir = '';

	/**
	 * Path to a temporary SVN checkout directory.
	 * 
	 * @var string
	 */
	public $tmp_svn_dir = '';

	/**
	 * Path to temporary theme folder.
	 *
	 * @var string
	 */
	public $theme_dir = '';

	/**
	 * The uploaded theme.
	 *
	 * @var WP_Theme
	 */
	public $theme;

	/**
	 * The theme slug being uploaded.
	 *
	 * @var string
	 */
	public $theme_slug = '';

	/**
	 * The theme post if it already exists in the repository.
	 *
	 * @var WP_Post
	 */
	public $theme_post;

	/**
	 * The theme author (current user).
	 *
	 * @var WP_User
	 */
	public $author;

	/**
	 * The theme readme.txt data.
	 *
	 * @var array
	 */
	public $readme = array();

	/**
	 * Trac ticket information.
	 *
	 * @var object
	 */
	public $trac_ticket;

	/**
	 * Trac changeset.
	 * 
	 * @var int
	 */
	public $trac_changeset = 0;

	/**
	 * A Trac instance to communicate with theme.trac.
	 *
	 * @var Trac
	 */
	public $trac;

	/**
	 * Theme import status, what the status of this theme version is.
	 *
	 * @var string
	 */
	protected $version_status = 'new';

	/**
	 * Where the import is triggered from. 'svn' or 'upload'.
	 * 
	 * @var string
	 */
	protected $importing_from = 'upload';

	/**
	 * The SVN commit message, if user defined.
	 * 
	 * @var string
	 */
	public $commit_msg = '';

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
	 * Reset all class properties before each import, to avoid a situation
	 * where multiple imports will use one anothers data.
	 */
	protected function reset_properties() {
		$this->author         = false;
		$this->readme         = array();
		$this->theme          = false;
		$this->theme_post     = false;
		$this->theme_slug     = '';
		$this->theme_dir      = '';
		$this->theme_name     = '';
		$this->tmp_svn_dir    = '';
		$this->trac_changeset = 0;
		$this->trac_ticket    = (object) array(
			'id'          => 0,
			'resolution'  => '',
			'summary'     => '',
			'keywords'    => [],
			'parent_link' => '',
			'priority'    => '',
			'diff_line'   => '',
			'description' => '',
		);
		$this->version_status = 'new';
		$this->importing_from = 'upload';
		$this->commit_msg     = '';

		// $this->tmp_dir = '';    // Temporary folder per each instance of this class. Doesn't need to be reset each time.
		// $this->trac    = false; // This can stay active, Trac access won't change between calls.
	}

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

		// Validate it's not too large.
		if ( $size > wp_max_upload_size() ) {
			return new WP_Error(
				'file_too_big',
				sprintf(
					__( 'Maximum allowed file size: %s', 'wporg-themes' ),
					esc_html( size_format( wp_max_upload_size() ) )
				)
			);
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
	 * Process a theme update, from files that are already in SVN.
	 *
	 * @param string $slug      The theme slug to process. Must exist.
	 * @param string $version   The theme version to process. Must exist.
	 * @param int    $changeset The SVN revision if known. Optional.
	 * @param string $author    The SVN author if known. Optional.
	 * @return true|WP_Error See ::import() for error conditions.
	 */
	public function process_update_from_svn( $slug, $version, $changeset = 0, $author = '', $commit_message = '' ) {
		$this->reset_properties();

		$this->importing_from = 'svn';
		$this->theme_slug     = $slug;
		$this->commit_msg     = $commit_message;

		// Check out from SVN.
		$this->create_tmp_dirs( $slug . '.' . $version );
		$esc_svn = escapeshellarg( "https://themes.svn.wordpress.org/{$slug}/{$version}/" );
		$this->exec_with_notify(
			self::SVN . " export {$esc_svn} {$this->theme_dir} --force", // force as we've created the directory already.
			$output,
			$return_var
		);
		if ( $return_var ) {
			return new WP_Error(
				'svn_error',
				implode( "\n", $output )
			);
		}

		// Fetch data from SVN if not known.
		if ( ! $changeset ) {
			$changeset = (int) trim( $this->exec_with_notify( self::SVN . " info --show-item=last-changed-revision {$esc_svn}" ) );
		}
		if ( ! $author ) {
			$author = trim( $this->exec_with_notify( self::SVN . " info --show-item=last-changed-author {$esc_svn}" ) );
		}

		// Get the revision.
		$this->trac_changeset = $changeset;

		// Get the author.
		if ( $author && 'themedropbox' !== $author ) {
			$this->author = get_user_by( 'login', $author );
		}

		// The version should be set live as it's from SVN.
		$this->version_status = 'live';

		return $this->import( array( // return true | WP_Error
			// Since this version is already in SVN, we shouldn't try to import it again.
			'commit_to_svn' => false,
		) );
	}

	/**
	 * Processes a theme ZIP upload.
	 *
	 * Runs various tests, creates Trac ticket, repopackage post, and saves the files to the SVN repo.
	 *
	 * @return WP_Error|string Failure or success message.
	 */
	public function process_upload( $file_upload ) {
		$this->reset_properties();

		$valid_upload = $this->validate_upload( $file_upload );
		if ( is_wp_error( $valid_upload ) ) {
			return $valid_upload;
		} elseif ( ! $valid_upload ) {
			return new WP_Error(
				'invalid_input',
				__( 'Error in file upload.', 'wporg-themes' )
			);
		}

		$this->create_tmp_dirs( $file_upload['name'], true );
		$this->unzip_package( $file_upload );

		$result = $this->import();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return sprintf(
			/* translators: 1: theme name, 2: Trac ticket URL */
			__( 'Thank you for uploading %1$s to the WordPress Theme Directory. We&rsquo;ve sent you an email verifying that we&rsquo;ve received it. Feedback will be provided at <a href="%2$s">%2$s</a>', 'wporg-themes' ),
			$this->theme->display( 'Name' ),
			esc_url( 'https://themes.trac.wordpress.org/ticket/' . $this->trac_ticket->id )
		);
	}

	/**
	 * Processes a theme import.
	 *
	 * @return WP_Error|true Error object on failure, true on success.
	 */
	protected function import( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				// Whether to commit the files to SVN.
				'commit_to_svn'       => true,
				// Whether Theme Check should maybe block the import.
				'run_themecheck'      => true,
				// Whether a failing Theme Check blocks the import.
				'block_on_themecheck' => true,
				// Whether to create a Trac ticket for this import.
				'create_trac_ticket'  => true,
			)
		);

		// When running locally, certain actions cannot be performed.
		if ( ! defined( 'THEME_TRACBOT_PASSWORD' ) || ! THEME_TRACBOT_PASSWORD ) {
			$args['create_trac_ticket'] = false;
		}
		if ( ! defined( 'THEME_DROPBOX_PASSWORD' ) || ! THEME_DROPBOX_PASSWORD ) {
			$args['commit_to_svn'] = false;
		}

		$theme_files = $this->get_all_files( $this->theme_dir );

		// First things first. Do we have something to work with?
		if ( empty( $theme_files ) ) {
			return new WP_Error(
				'empty_zip',
				__( 'The zip file was empty.', 'wporg-themes' )
			);
		}

		// Do we have a stylesheet? Life is kind of pointless without.
		$style_css = $this->get_style_css( $theme_files );
		if ( empty( $style_css ) ) {
			return new WP_Error(
				'no_style',
				sprintf(
					/* translators: %s: style.css */
					__( 'The zip file must include a file named %s.', 'wporg-themes' ),
					'<code>style.css</code>'
				)
			);
		}

		$style_errors = new WP_Error;

		// Do we have a readme.txt? Fetch extra data from there too.
		$this->readme = $this->get_readme_data( $theme_files );

		// We have a stylesheet, let's set up the theme, theme post, and author.
		$this->theme = new WP_Theme( basename( dirname( $style_css ) ), dirname( dirname( $style_css ) ) );

		// We need a screen shot. People love screen shots.
		if ( ! $this->has_screenshot( $theme_files ) ) {
			$style_errors->add(
				'no_screenshot',
				sprintf(
					/* translators: 1: screenshot.png, 2: screenshot.jpg */
					__( 'The zip file must include a file named %1$s or %2$s.', 'wporg-themes' ),
					'<code>screenshot.png</code>',
					'<code>screenshot.jpg</code>'
				)
			);
		}

		// reset the theme directory to be where the stylesheet is
		$this->theme_dir = dirname( $style_css );

		// Let's check some theme headers, shall we?
		$this->theme_name = $this->theme->get( 'Name' );

		if ( ! $this->theme_slug ) {
			// Determine the theme slug (ascii only for compatibility) based on the name of the theme in the stylesheet
			$this->theme_slug = remove_accents( $this->theme_name );
			$this->theme_slug = preg_replace( '/%[a-f0-9]{2}/i', '', $this->theme_slug );
			$this->theme_slug = sanitize_title_with_dashes( $this->theme_slug );
		}

		// Account for "twenty" themes, these themes have slugs that do not match the normal conventions.
		$this->theme_slug = str_replace(
			array( 'twenty-ten', 'twenty-eleven', 'twenty-twelve', 'twenty-thirteen', 'twenty-fourteen', 'twenty-fifteen', 'twenty-sixteen', 'twenty-seventeen', 'twenty-eighteen', 'twenty-nineteen', 'twenty-twenty-one', 'twenty-twenty-two', 'twenty-twenty-three', 'twenty-twenty-four', 'twenty-twenty-five', 'twenty-twenty-six', 'twenty-twenty-seven', 'twenty-twenty-eight', 'twenty-twenty-nine', 'twenty-thirty', 'twenty-twenty'),
			array( 'twentyten',  'twentyeleven',  'twentytwelve',  'twentythirteen',  'twentyfourteen',  'twentyfifteen',  'twentysixteen',  'twentyseventeen',  'twentyeighteen',  'twentynineteen', 'twentytwentyone', 'twentytwentytwo', 'twentytwentythree', 'twentytwentyfour',  'twentytwentyfive', 'twentytwentysix', 'twentytwentyseven', 'twentytwentyeight', 'twentytwentynine', 'twentythirty', 'twentytwenty' ),
			$this->theme_slug
		);

		if ( ! $this->theme_name || ! $this->theme_slug ) {
			$error = __( 'The theme has no name.', 'wporg-themes' ) . ' ';

			$error .= sprintf(
				/* translators: 1: comment header line, 2: style.css, 3: wporg URL */
				__( 'Add a %1$s line to your %2$s file and upload the theme again. <a href="%3$s">Theme Style Sheets</a>', 'wporg-themes' ),
				'<code>Theme Name:</code>',
				'<code>style.css</code>',
				__( 'https://developer.wordpress.org/themes/basics/main-stylesheet-style-css/', 'wporg-themes' )
			);

			$style_errors->add( 'no_name', $error );
		}

		// Do not allow themes with WordPress and Theme in the theme name.
		if ( false !== strpos( $this->theme_slug, 'wordpress' ) || preg_match( '/\btheme\b/i', $this->theme_slug ) ) {
			$style_errors->add(
				'invalid_name',
				sprintf(
					/* translators: 1: 'WordPress', 2: 'theme' */
					__( 'You cannot use %1$s or %2$s in your theme name.', 'wporg-themes' ),
					'WordPress',
					'theme'
				)
			);
		}

		// Populate the theme post.
		if ( ! $this->theme_post ) {
			$this->theme_post = $this->get_theme_post();
		}

		// Populate author.
		if ( ! $this->author ) {
			if ( is_user_logged_in() ) {
				$this->author = wp_get_current_user();
			} elseif ( $this->theme_post ) {
				$this->author = get_user_by( 'id', $this->theme_post->post_author );
			}
		}

		// Default Theme handling.
		if (
			// Reserved slugs include twenty* and other terms
			$this->has_reserved_slug() &&
			// ...so limit to twenty* only
			str_starts_with( $this->theme_slug, 'twenty' ) &&
			// The current user is a Core Committer. [ 'user_login' => 'Trac Title', ... ]
			! empty( $GLOBALS['committers'][ $this->author->user_login ] ) &&
			(
				// New theme submission
				! $this->theme_post
				||
				// OR an Update and the theme is owned by WordPress.org.
				'wordpressdotorg' === get_user_by( 'id', $this->theme_post->post_author )->user_login
			)
		) {
			// Set the author to WordPress.org
			$this->author = get_user_by( 'login', 'wordpressdotorg' );

			// WordPress.org is allowed to bypass Theme Check, see further down.
		}

		// Make sure it doesn't use a slug deemed not to be used by the public.
		if ( $this->has_reserved_slug() ) {
			$style_errors->add(
				'reserved_slug',
				sprintf(
					/* translators: 1: theme slug, 2: style.css */
					__( 'Sorry, the theme name %1$s is reserved for use by WordPress Core. Please change the name of your theme in %2$s and upload it again.', 'wporg-themes' ),
					'<code>' . $this->theme_slug . '</code>',
					'<code>style.css</code>'
				)
			);
		}

		$theme_description = $this->strip_non_utf8( (string) $this->theme->get( 'Description' ) );
		if ( empty( $theme_description ) ) {
			$error = __( 'The theme has no description.', 'wporg-themes' ) . ' ';

			$error .= sprintf(
				/* translators: 1: comment header line, 2: style.css, 3: wporg URL */
				__( 'Add a %1$s line to your %2$s file and upload the theme again. <a href="%3$s">Theme Style Sheets</a>', 'wporg-themes' ),
				'<code>Description:</code>',
				'<code>style.css</code>',
				__( 'https://developer.wordpress.org/themes/basics/main-stylesheet-style-css/', 'wporg-themes' )
			);

			$style_errors->add( 'no_description', $error );
		}

		if ( ! $this->theme->get( 'Tags' ) ) {
			$error = __( 'The theme has no tags.', 'wporg-themes' ) . ' ';

			$error .= sprintf(
				/* translators: 1: comment header line, 2: style.css, 3: wporg URL */
				__( 'Add a %1$s line to your %2$s file and upload the theme again. <a href="%3$s">Theme Style Sheets</a>', 'wporg-themes' ),
				'<code>Tags:</code>',
				'<code>style.css</code>',
				__( 'https://developer.wordpress.org/themes/basics/main-stylesheet-style-css/', 'wporg-themes' )
			);

			$style_errors->add( 'no_tags', $error );
		}

		if ( ! $this->theme->get( 'Version' ) ) {
			$error = __( 'The theme has no version.', 'wporg-themes' ) . ' ';

			$error .= sprintf(
				/* translators: 1: comment header line, 2: style.css, 3: wporg URL */
				__( 'Add a %1$s line to your %2$s file and upload the theme again. <a href="%3$s">Theme Style Sheets</a>', 'wporg-themes' ),
				'<code>Version:</code>',
				'<code>style.css</code>',
				__( 'https://developer.wordpress.org/themes/basics/main-stylesheet-style-css/', 'wporg-themes' )
			);

			$style_errors->add( 'no_version', $error );

		} else if ( preg_match( '|[^\d\.]|', $this->theme->get( 'Version' ) ) ) {
			$style_errors->add(
				'invalid_version',
				sprintf(
					/* translators: %s: style.css */
					__( 'Version strings can only contain numeric and period characters (like 1.2). Please fix your Version: line in %s and upload your theme again.', 'wporg-themes' ),
					'<code>style.css</code>'
				)
			);
		}

		// Version is greater than current version happens after authorship checks.

		// Prevent duplicate URLs.
		$themeuri = $this->theme->get( 'ThemeURI' );
		$authoruri = $this->theme->get( 'AuthorURI' );
		if (
			! empty( $themeuri ) &&
			! empty( $authoruri ) &&
			$themeuri == $authoruri
		) {
			$style_errors->add(
				'duplicate_uris',
				__( 'Duplicate theme and author URLs. A theme URL is a page/site that provides details about this specific theme. An author URL is a page/site that provides information about the author of the theme. You aren&rsquo;t required to provide both, so pick the one that best applies to your URL.', 'wporg-themes' )
			);
		}

		// Check for child theme's parent in the directory (non-buddypress only)
		if (
			$this->theme->parent() &&
			! in_array( 'buddypress', $this->theme->get( 'Tags' ) ) &&
			! $this->is_parent_available()
		) {
			$style_errors->add(
				'invalid_parent',
				sprintf(
					/* translators: %s: parent theme */
					__( 'There is no theme called %s in the directory. For child themes, you must use a parent theme that already exists in the directory.', 'wporg-themes' ),
					'<code>' . $this->theme->parent() . '</code>'
				)
			);
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
		if (
			! empty( $this->theme_post ) &&
			! empty( $this->author ) &&
			$this->theme_post->post_author != $this->author->ID
		) {
			$style_errors->add(
				'cannot_upload_theme',
				sprintf(
					/* translators: 1: theme slug, 2: style.css */
					__( 'There is already a theme called %1$s by a different author. Please change the name of your theme in %2$s and upload it again.', 'wporg-themes' ),
					'<code>' . $this->theme_slug . '</code>',
					'<code>style.css</code>'
				) . $are_you_in_the_right_place
			);
		}

		// Check if the ThemeURI is already in use by another theme by another author.
		if (
			empty( $this->theme_post ) &&
			! empty( $themeuri )
		) {
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
				$style_errors->add(
					'invalid_theme_uri',
					sprintf(
						/* translators: 1: theme name, 2: style.css */
						__( 'There is already a theme using the Theme URL %1$s by a different author. Please check the URL of your theme in %2$s and upload it again.', 'wporg-themes' ),
						'<code>' . esc_html( $themeuri ) . '</code>',
						'<code>style.css</code>'
					) . $are_you_in_the_right_place
				);
			}
		}

		// We know it's the correct author, now we can check if it's suspended.
		if (
			! empty( $this->theme_post ) &&
			'suspend' === $this->theme_post->post_status
		) {
			$style_errors->add(
				'suspended',
				sprintf(
					/* translators: %s: mailto link */
					__( 'This theme is suspended from the Theme Repository and it can&rsquo;t be updated. If you have any questions about this please contact %s.', 'wporg-themes' ),
					'<a href="mailto:themes@wordpress.org">themes@wordpress.org</a>'
				)
			);
		}

		// Make sure we have version that is higher than any previously uploaded version of this theme. This check happens last to allow the non-author blocks to kick in.
		if (
			! empty( $this->theme_post ) &&
			! version_compare( $this->theme->get( 'Version' ), $this->theme_post->max_version, '>' )
		) {
			$style_errors->add(
				'invalid_version',
				sprintf(
					/* translators: 1: theme name, 2: theme version, 3: style.css */
					__( 'You need to upload a version of %1$s higher than %2$s. Increase the theme version number in %3$s, then upload your zip file again.', 'wporg-themes' ),
					$this->theme->display( 'Name' ),
					'<code>' . $this->theme_post->max_version . '</code>',
					'<code>style.css</code>'
				)
			);
		}

		// If we had any issues with information in the style.css, exit early.
		if ( $style_errors->has_errors() ) {
			return $style_errors;
		}

		// Don't block special themes or default themes based on Theme Check.
		if ( has_category( 'special-case-theme', $this->theme_post ) ) {
			$args['block_on_themecheck'] = false;
		} elseif ( 'wordpressdotorg' === $this->author->user_login ) {
			$args['block_on_themecheck'] = false;
		}

		// Pass it through Theme Check and see how great this theme really is.
		if ( $args['run_themecheck'] ) {
			$result = $this->check_theme();

			if ( ! $result && $args['block_on_themecheck'] ) {
				// Log it to slack.
				$this->log_to_slack( 'blocked' );

				return new WP_Error(
					'failed_theme_check',
					sprintf(
						/* translators: 1: Theme Check Plugin URL, 2: make.wordpress.org/themes */
						__( 'Your theme has failed the theme check. Please correct the problems with it and upload it again. You can also use the <a href="%1$s">Theme Check Plugin</a> to test your theme before uploading. If you have any questions about this please post them to %2$s.', 'wporg-themes' ),
						'//wordpress.org/plugins/theme-check/',
						'<a href="https://make.wordpress.org/themes">https://make.wordpress.org/themes</a>'
					)
				);
			}
		}

		// Passed all tests!
		// Let's save everything and get things wrapped up.

		// Create a new version in SVN.
		if ( $args['commit_to_svn'] ) {
			$result = $this->add_to_svn();
			if ( ! $result || is_wp_error( $result ) ) {
				return new WP_Error(
					'failed_svn_commit',
					sprintf(
						/* translators: %s: mailto link */
						__( 'There was an error adding your theme to SVN. Please try again, if this error persists report the error to %s.', 'wporg-themes' ),
						'<a href="mailto:themes@wordpress.org">themes@wordpress.org</a>'
					) .
					(
						is_wp_error( $result ) && $result->get_error_message( 'pre-commit-hook' ) ?
							'<br><code>' . nl2br( esc_html( $result->get_error_message( 'pre-commit-hook' ) ) ) . '</code>' : 
							''
					)
				);
			}
		}

		// Create a Trac ticket for this theme version.
		if ( $args['create_trac_ticket'] ) {
			// Get all Trac ticket information set up.
			$this->prepare_trac_ticket();

			// Talk to Trac and let them know about our new version. Or new theme.
			$ticket_id = $this->create_or_update_trac_ticket();

			if ( ! $ticket_id  ) {
				if ( $args['commit_to_svn'] ) {
					// Since it's been added to SVN at this point, remove it from SVN to prevent future issues.
					$this->remove_from_svn( 'Trac ticket creation failed.' );
				}

				return new WP_Error(
					'failed_trac_ticket_creation',
					sprintf(
						/* translators: %s: mailto link */
						__( 'There was an error creating a Trac ticket for your theme, please report this error to %s', 'wporg-themes' ),
						'<a href="mailto:themes@wordpress.org">themes@wordpress.org</a>'
					)
				);
			}
		}

		// Add a or update the Theme Directory entry for this theme.
		$this->create_or_update_theme_post();

		// Send theme author an email for peace of mind.
		$this->send_email_notification();

		do_action( 'theme_upload', $this->theme, $this->theme_post );

		// Log it to slack.
		$this->log_to_slack( 'allowed' );

		// Initiate a GitHub actions run for the theme.
		$this->trigger_e2e_run();

		// Success!
		return true;
	}

	/**
	 * Creates a temporary directory, and the theme dir within it.
	 */
	public function create_tmp_dirs( $base_name, $create_svn_tmp = false ) {
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
		$this->tmp_svn_dir = "{$this->tmp_dir}/"; // Set it to something, just in case.
		mkdir( $this->theme_dir );
		chmod( $this->theme_dir, 0777 );
		if ( $create_svn_tmp ) {
			$this->tmp_svn_dir = "{$this->tmp_dir}/svn";
			mkdir( $this->tmp_svn_dir );
			chmod( $this->tmp_svn_dir, 0777 );
		}

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
		if ( ! in_array( $this->theme_slug, $reserved_slugs, true ) ) {
			return false;
		}

		// WordPress.org user is always allowed to upload reserved slugs.
		if ( 'wordpressdotorg' === $this->author->user_login ) {
			return false;
		}

		// Slug is reserved, user is not authorized.
		return true;
	}

	/**
	 * Sends a theme through Theme Check.
	 *
	 * @return bool Whether the theme passed the checks.
	 */
	public function check_theme() {
		// Load the theme checking code.
		if ( ! function_exists( 'run_themechecks_against_theme' ) ) {
			include_once WP_PLUGIN_DIR . '/theme-check/checkbase.php';

			// If Theme Check isn't loaded, assume it's fine.
			if ( ! function_exists( 'run_themechecks_against_theme' ) ) {
				global $themechecks;
				// Set the theme checks to an empty list to avoid notices when not available.
				$themechecks = array();

				return true;
			}
		}

		// Run the checks, using US English.
		$locale_switched = switch_to_locale( 'en_US' );
		$result = run_themechecks_against_theme( $this->theme, $this->theme_slug );
		if ( $locale_switched ) {
			restore_previous_locale();
		}

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
		$this->trac_ticket->summary = sprintf( 'THEME: %1$s – %2$s', $this->theme->display( 'Name' ), $this->theme->display( 'Version' ) );

		// Keywords
		$this->trac_ticket->keywords[] = 'theme-' . $this->theme_slug;

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
		if ( ! empty( $this->theme_post->max_version ) ) {
			$this->trac_ticket->diff_line = "\nDiff with previous version: [{$this->trac_changeset}] https://themes.trac.wordpress.org/changeset?old_path={$this->theme_slug}/{$this->theme_post->max_version}&new_path={$this->theme_slug}/{$this->theme->display( 'Version' )}\n";
		}

		// Description
		$theme_description = $this->strip_non_utf8( (string) $this->theme->display( 'Description' ) );

		// ZIP location
		$theme_zip_link = "https://downloads.wordpress.org/theme/{$this->theme_slug}.{$this->theme->display( 'Version' )}.zip?nostats=1";

		// Build the Live Preview Blueprint & URL.
		 $blueprint_parent_step = '';
		 if (
			$this->theme->parent() &&
			in_array( 'buddypress', $this->theme->get( 'Tags' ) )
		) {
			$blueprint_parent_step = <<<BLUEPRINT_PARENT_BP
			{
				"step": "installPlugin",
				"pluginZipFile": {
					"resource": "wordpress.org/plugins",
					"slug": "buddypress"
				},
				"options": {
					"activate": true
				}
			},
			BLUEPRINT_PARENT_BP;
		} elseif ( $this->theme->parent() ) {
			$blueprint_parent_step = <<<BLUEPRINT_PARENT_THEME
			{
				"step": "installTheme",
				"themeZipFile": {
					"resource": "wordpress.org/themes",
					"slug": "{$this->theme->get_template()}"
				}
			},
			BLUEPRINT_PARENT_THEME;
		}

		// NOTE: The username + password included below are only used for the local in-browser environment, and are not a secret.
		$blueprint = <<<BLUEPRINT
		{
			"preferredVersions": {
				"php": "7.4",
				"wp": "latest"
			},
			"steps": [
				{
					"step": "login",
					"username": "admin",
					"password": "password"
				},
				{
					"step": "defineWpConfigConsts",
					"consts": {
						"WP_DEBUG": true
					}
				},
				{
					"step": "importFile",
					"file": {
						"resource": "url",
						"url": "https://raw.githubusercontent.com/WordPress/theme-test-data/master/themeunittestdata.wordpress.xml",
						"caption": "Downloading theme testing content"
					},
					"progress": {
						"caption": "Installing theme testing content"
					}
				},
				{
					"step": "installPlugin",
					"pluginZipFile": {
						"resource": "wordpress.org/plugins",
						"slug": "theme-check"
					},
					"options": {
						"activate": true
					}
				},
				{$blueprint_parent_step}
				{
					"step": "installTheme",
					"themeZipFile": {
						"resource": "url",
						"url": "{$theme_zip_link}",
						"caption": "Downloading the theme"
					}
				}
			]
		}
		BLUEPRINT;

		// NOTE: The json_encode( json_decode() ) is to remove the whitespaces used above for readability.
		$live_preview_link = 'https://playground.wordpress.net/#' . json_encode( json_decode( $blueprint ) );

		// Hacky way to prevent a problem with xml-rpc.
		$this->trac_ticket->description = <<<TICKET
{$this->theme->display( 'Name' )} - {$this->theme->display( 'Version' )}

{$theme_description}

Theme URL - {$this->theme->display( 'ThemeURI' )}
Author URL - {$this->theme->display( 'AuthorURI' )}

Trac Browser - https://themes.trac.wordpress.org/browser/{$this->theme_slug}/{$this->theme->display( 'Version' )}
WordPress.org - https://wordpress.org/themes/{$this->theme_slug}/

SVN - https://themes.svn.wordpress.org/{$this->theme_slug}/{$this->theme->display( 'Version' )}
ZIP - {$theme_zip_link}
Live preview – [[{$live_preview_link}|https://playground.wordpress.net/#…]]

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
			if ( ! defined( 'THEME_TRACBOT_PASSWORD' ) ) {
				return false;
			}

			if ( ! class_exists( 'Trac' ) ) {
				require_once ABSPATH . WPINC . '/class-IXR.php';
				require_once ABSPATH . WPINC . '/class-wp-http-ixr-client.php';
				require_once __DIR__ . '/lib/class-trac.php';
			}

			$this->trac = new Trac( 'themetracbot', THEME_TRACBOT_PASSWORD, 'https://themes.trac.wordpress.org/login/xmlrpc' );
		}

		/*
		 * Trac reporter is always the authenticated user, unless it's not-auth'd in which case it's the Theme Author.
		 *
		 * This allows for Committers to upload Default themes under 'WordPress.org' but it still be to noted on Trac who uploaded it.
		 * This also allows for SVN imports where the current user is not set.
		 */
		$trac_ticket_reporter = wp_get_current_user()->user_login ?? $this->author->user_login;

		// If there's a previous version and the most current version's status is `new`, we update.
		if (
			! empty( $this->theme_post->max_version ) &&
			'new' == $this->theme_post->_status[ $this->theme_post->max_version ]
		) {
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
					'reporter'  => $trac_ticket_reporter,
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
				'reporter'  => $trac_ticket_reporter,
				'cc'        => $this->author->user_email,
				'priority'  => $this->trac_ticket->priority,
				'owner'     => '',
			) );

			// Themes team auto-approves theme-updates, so mark the theme as live immediately.
			// Note that this only applies to new ticket creation, so it won't happen on themes with existing outstanding tickets.
			if ( $this->trac_ticket->priority == 'theme update' ) {
				$this->trac->ticket_update( $ticket_id, 'Theme Update for existing Live theme - automatically approved', array( 'action' => 'new_no_review' ), false );

				$this->trac_ticket->resolution = 'live';
				$this->version_status          = 'live';
			}

		}

		$this->trac_ticket->id = $ticket_id;

		return $ticket_id;
	}

	/**
	 * Creates or updates a theme post.
	 */
	public function create_or_update_theme_post() {
		$upload_date = current_time( 'mysql' );

		// If we already have a post, get its ID.
		if ( ! empty( $this->theme_post ) ) {
			$post_id = $this->theme_post->ID;
			// see wporg_themes_approve_version() for where the post is updated.

		// Otherwise create it for this new theme.
		} else {

			// Filter the tags to those that exist on the site already.
			$tags = array_intersect(
				$this->theme->get( 'Tags' ),
				get_terms( array(
					'hide_empty' => false,
					'taxonomy' => 'post_tag',
					'fields' => 'slugs'
				) )
			);

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
				'tags_input'     => $tags,
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
			'_ticket_id'    => $this->trac_ticket->id,
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
		if ( $post_meta['_ticket_id'] ) {
			add_post_meta( $post_id, sanitize_key( '_trac_ticket_' . $this->theme->get( 'Version' ) ), $post_meta['_ticket_id'] );
		}

		// Discard versions that are awaiting review, and maybe set this upload as live.
		wporg_themes_update_version_status( $post_id, $this->theme->get( 'Version' ), $this->version_status );

		// refresh the post to avoid stale data.
		if ( $post_id ) {
			$this->theme_post = $this->get_theme_post();
		}
	}

	/**
	 * Add theme files to SVN.
	 * 
	 * This attempts to do a SVN copy to allow for simpler diff views, but falls back to a svn import as an error condition.
	 */
	public function add_to_svn() {
		if ( ! defined( 'THEME_DROPBOX_PASSWORD' ) || ! THEME_DROPBOX_PASSWORD ) {
			return false;
		}

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
		$this->exec_with_notify( "find {$new_version_dir}/ -mindepth 1 -delete" );

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
	public function add_to_svn_via_svn_import() {
		if ( ! defined( 'THEME_DROPBOX_PASSWORD' ) || ! THEME_DROPBOX_PASSWORD ) {
			return false;
		}

		$import_msg = empty( $this->theme_post ) ?  'New theme: %1$s - %2$s' : 'New version of %1$s - %2$s'; // Intentionally not translated
		$import_msg = escapeshellarg( sprintf( $import_msg, $this->theme->display( 'Name' ), $this->theme->display( 'Version' ) ) );
		$svn_path   = escapeshellarg( "https://themes.svn.wordpress.org/{$this->theme_slug}/{$this->theme->display( 'Version' )}" );
		$theme_path = escapeshellarg( $this->theme_dir );
		$password   = escapeshellarg( THEME_DROPBOX_PASSWORD );

		$last_line = $this->exec_with_notify( self::SVN . " --non-interactive --username themedropbox --password {$password} --no-auto-props -m {$import_msg} import {$theme_path} {$svn_path}", $output, $return_var, $stderr );

		// Pass through any error output from the SVN error handler.
		if (
			! empty( $stderr ) &&
			false !== stripos( $stderr[0], 'Commit blocked by pre-commit hook' ) &&
			preg_match( '!([*]{12,})(.*?)\\1!s', implode( "\n", $stderr ) . "\n", $m )
		) {
			return new WP_Error( 'pre-commit-hook', trim( $m[0] ) );
		}

		if ( preg_match( '/Committed revision (\d+)\./i', $last_line, $m ) ) {
			$this->trac_changeset = $m[1];
			return true;
		}

		return false;
	}

	/**
	 * Remove a theme version commited to SVN.
	 */
	public function remove_from_svn( $reason ) {
		if ( ! defined( 'THEME_DROPBOX_PASSWORD' ) || ! THEME_DROPBOX_PASSWORD ) {
			return false;
		}

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
	 * Sends out an email confirmation to the theme's author that an upload has taken place.
	 */
	public function send_email_notification() {
		/*
		 * Skip sending an email when..
		 *  - The theme is to be made live immediately.
		 *    `wporg_themes_approve_version()` will send a "Congratulations! It's live!" shortly.
		 *  - No Trac ticket was created, so there's nothing to reference about where feedback is.
		 */
		if (
			'live' === $this->version_status ||
			! $this->trac_ticket->id
		) {
			return;
		}

		if ( ! empty( $this->theme_post ) ) {
			$email_subject = sprintf(
				/* translators: 1: theme name, 2: theme version */
				__( '[WordPress Themes] %1$s, new version %2$s', 'wporg-themes' ),
				$this->theme->display( 'Name' ),
				$this->theme->display( 'Version' )
			);

			$email_content = sprintf(
				/* translators: 1: theme version, 2: theme name, 3: Trac ticket URL */
				__( 'Thank you for uploading version %1$s of %2$s.

Feedback will be provided at %3$s

--
The WordPress.org Themes Team
https://make.wordpress.org/themes', 'wporg-themes' ),
				$this->theme->display( 'Version' ),
				$this->theme->display( 'Name' ),
				'https://themes.trac.wordpress.org/ticket/' . $this->trac_ticket->id
			);
		} else {
			$email_subject = sprintf(
				/* translators: %s: theme name */
				__( '[WordPress Themes] New Theme - %s', 'wporg-themes' ),
				$this->theme->display( 'Name' )
			);

			$email_content = sprintf(
				/* translators: 1: theme name, 2: Trac ticket URL */
				__( 'Thank you for uploading %1$s to the WordPress Theme Directory. A ticket has been created for the review:
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
If you have questions you can ask the reviewer in the ticket or chat with us on Slack in the #themereview channel.
<https://chat.wordpress.org/>

Subscribe to the Themes Team blog to stay up to date with the latest requirements and the ongoing work to improve the review process:
<https://make.wordpress.org/themes/>

Thank you.
The WordPress Themes Team', 'wporg-themes' ),
				$this->theme->display( 'Name' ),
				'https://themes.trac.wordpress.org/ticket/' . $this->trac_ticket->id
			);
		}

		// Email the Theme Author(s). The uploader & theme author may differ in special cases (default themes).
		$emails = array_filter( array_unique( [
			// The theme author
			$this->author->user_email,
			// The theme Author (usually the same)
			get_user_by( 'id', $this->theme_post->post_author )->user_email ?? false,
			// The current user (also, usually the same)
			wp_get_current_user()->user_email
		] ) );

		wp_mail( $emails, $email_subject, $email_content, 'From: "WordPress Theme Directory" <themes@wordpress.org>' );
	}

	/**
	 * Triggers a GitHub actions run for the upload.
	 */
	public function trigger_e2e_run() {
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
					'trac_ticket_id'   => $this->trac_ticket->id,
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
	 * Log a Theme Upload to the slack `#themereview-firehose` channel.
	 * 
	 * @param string $status Whether the upload was 'allowed' or 'blocked'.
	 */
	public function log_to_slack( $status = 'allowed' ) {
		global $themechecks;

		if ( ! defined( 'THEME_DIRECTORY_SLACK_WEBHOOK' ) ) {
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
			// Build the fields to include with the post.
			$fields = [
				[
					'type' => 'mrkdwn',
					'text' => "*Version:*\n{$this->theme->get('Version')}",
				]
			];

			if ( $this->trac_ticket->priority ) {
				$fields[] = [
					'type' => 'mrkdwn',
					'text' => "*Priority:*\n{$this->trac_ticket->priority}",
				];
			}

			if ( $this->trac_ticket->resolution ) {
				$fields[] = [
					'type' => 'mrkdwn',
					'text' => "*Resolution:*\n{$this->trac_ticket->resolution}",
				];
			}

			if ( $this->trac_ticket->id || $this->trac_changeset ) {
				$fields[] = [
					'type' => 'mrkdwn',
					'text' => "*Trac:*\n" .
						(
							$this->trac_ticket->id ?
							"<https://themes.trac.wordpress.org/ticket/{$this->trac_ticket->id}|#{$this->trac_ticket->id}> " :
							''
						) .
						(
							$this->trac_changeset ?
							"<https://themes.trac.wordpress.org/changeset/{$this->trac_changeset}|[{$this->trac_changeset}]> " :
							''
						) . 
						(
							// When importing from SVN, include a 'Compare' link as the Changeset likely won't show a Diff unless the author did a `svn cp`.
							'svn' === $this->importing_from && ! empty( $this->theme_post->max_version ) ?
							"<https://themes.trac.wordpress.org/changeset?old_path={$this->theme_slug}/{$this->theme_post->_last_live_version}&new_path={$this->theme_slug}/{$this->theme->display( 'Version' )}|Compare>" :
							''
						),
				];
			}

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
				'fields' => $fields
			];

			if ( $this->commit_msg ) {
				$blocks[] = [
					'type' => 'section',
					'text' => [
						'type' => 'mrkdwn',
						'text' => "*Commit Message:* {$this->commit_msg}",
					],
				];
			}

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
