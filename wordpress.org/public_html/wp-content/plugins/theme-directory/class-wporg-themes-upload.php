<?php

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
	private $tmp_dir;

	/**
	 * Path to temporary theme folder.
	 *
	 * @var string
	 */
	private $theme_dir;

	/**
	 * The uploaded theme.
	 *
	 * @var WP_Theme
	 */
	private $theme;

	/**
	 * The theme post if it already exists in the repository.
	 *
	 * @var WP_Post
	 */
	private $theme_post;

	/**
	 * The theme author (current user).
	 *
	 * @var WP_User
	 */
	private $author;

	/**
	 * Trac ticket information.
	 *
	 * @var object
	 */
	private $trac_ticket;

	/**
	 * A Trac instance to communicate with theme.trac.
	 *
	 * @var Trac
	 */
	private $trac;

	/**
	 * Get set up to run tests on the uploaded theme.
	 */
	public function __construct() {
		$this->create_tmp_dirs();
		$this->unwrap_package();
	}

	/**
	 * Processes the theme upload.
	 *
	 * Runs various tests, creates Trac ticket, repopackage post, and saves the files to the SVN repo.
	 *
	 * @return string Failure or success message.
	 */
	public function process_upload() {
		$theme_files = $this->get_all_files( $this->theme_dir );

		// First things first. Do we have something to work with?
		if ( empty( $theme_files ) ) {
			return __( 'The zip file was empty.', 'wporg-themes' );
		}

		// Do we have a stylesheet? Life is kind of pointless without.
		$style_css = $this->get_style_css( $theme_files );
		if ( empty( $style_css ) ) {
			return sprintf( __( 'The zip file must include a file named %s.', 'wporg-themes' ), '<code>style.css</code>' );
		}

		// We have a stylesheet, let's set up the theme, theme post, and author.
		$this->theme      = new WP_Theme( basename( dirname( $style_css ) ), dirname( dirname( $style_css ) ) );
		$this->theme_post = $this->get_theme_post();
		$this->author     = wp_get_current_user();

		// We need a screen shot. People love screen shots.
		if ( ! $this->has_screenshot( $theme_files ) ) {
			return sprintf( __( 'The zip file must include a file named %1$s or %2$s.', 'wporg-themes' ), '<code>screenshot.png</code>', '<code>screenshot.jpg</code>' );
		}

		// Let's check some theme headers, shall we?

		if ( ! $this->theme->get( 'Name' ) ) {
			return __( "The theme has no name. Add it to style.css and upload the theme again. <a href='https://codex.wordpress.org/Theme_Development#Theme_Style_Sheet'>Theme Style Sheets</a>", 'wporg-themes' );
		}

		$theme_description = $this->strip_non_utf8( (string) $this->theme->get( 'Description' ) );
		if ( empty( $theme_description ) ) {
			return sprintf( __( 'The theme has no description. Add it to <code>style.css</code> and upload the theme again. <a href="%s">Theme Style Sheets</a>', 'wporg-themes' ), 'https://codex.wordpress.org/Theme_Development#Theme_Style_Sheet' );
		}

		if ( ! $this->theme->get( 'Tags' ) ) {
			return __( 'The stylesheet has no tags. Add a Tags: line to your <code>style.css</code> file and upload the zip file again.', 'wporg-themes' );
		}

		if ( ! $this->theme->get( 'Version' ) ) {
			return __( 'The stylesheet has no version. Add a Version: line to your <code>style.css</code> file and upload the zip file again.', 'wporg-themes' );
		}

		if ( preg_match( '|[^\d\.]|', $this->theme->get( 'Version' ) ) ) {
			return __( 'Version strings can only contain numeric and period characters (like 1.2). Please fix your Version: line in <code>style.css</code> and upload your theme again.', 'wporg-themes' );
		}

		// Make sure we have version that is higher than any previously uploaded version of this theme.
		if ( ! empty( $this->theme_post ) && ! version_compare( $this->theme->get( 'Version' ), $this->theme_post->max_version, '>' ) ) {
			return sprintf( __( 'You need to upload a version of %1$s higher than %2$s. Increase the theme version number in <code>style.css</code>, then upload your zip file again.', 'wporg-themes' ), $this->theme->display( 'Name' ), '<code>' . $this->theme->display( 'Version' ) . '</code>' );
		}

		// Prevent duplicate URLs.
		if ( ! $this->theme->get( 'ThemeURI' ) && ! $this->theme->get( 'AuthorURI' ) && $this->theme->get( 'ThemeURI' ) == $this->theme->get( 'AuthorURI' ) ) {
			return __( 'Duplicate theme and author URLs. A theme URL is a page/site the provides details about this specific theme. An author URL is a page/site that provides information about the author of the theme. You aren&rsquo;t required to provide both, so pick the one that best applies to your URL.', 'wporg-themes' );
		}

		// Check for child theme's parent in the directory (non-buddypress only)
		if ( $this->theme->parent() && ! in_array( 'buddypress', $this->theme->get( 'Tags' ) ) && ! $this->is_parent_available() ) {
			return sprintf( __( 'There is no theme called %s in the directory. For child themes, you must use a parent theme that already exists in the directory.', 'wporg-themes' ), '<code>' . $this->theme->parent() . '</code>' );
		}

		// Make sure it doesn't use a slug deemed not to be used by the public.
		if ( $this->has_reserved_slug() ) {
			return sprintf( __( 'Sorry, the theme name %s is reserved for use by WordPress Core. Please change the name of your theme in <code>style.css</code> and upload it again.', 'wporg-themes' ), '<code>' . $this->theme->get_stylesheet() . '</code>' );
		}

		// Is there already a theme with the name name by a different author?
		if ( ! empty( $this->theme_post ) && $this->theme_post->post_author != $this->author->ID ) {
			return sprintf( __( 'There is already a theme called %s by a different author. Please change the name of your theme in <code>style.css</code> and upload it again.', 'wporg-themes' ), '<code>' . $this->theme->get_stylesheet() . '</code>' );
		}

		// We know it's the correct author, now we can check if it's suspended.
		if ( ! empty( $this->theme_post ) && 'trash' === $this->theme_post->post_status ) {
			return sprintf( __( 'This theme is suspended from the Theme Repository and it can&rsquo;t be updated. If you have any questions about this please contact %s.', 'wporg-themes' ), '<a href="mailto:themes@wordpress.org">themes@wordpress.org</a>' );
		}

		// Don't send special themes through Theme Check.
		if ( ! has_category( 'special-case-theme', $this->theme_post ) ) {
			// Pass it through Theme Check and see how great this theme really is.
			$result = $this->check_theme( $theme_files );

			if ( ! $result ) {
				return sprintf( __( 'Your theme has failed the theme check. Please correct the problems with it and upload it again. You can also use the <a href="%1$s">Theme Check Plugin</a> to test your theme before uploading. If you have any questions about this please post them to %2$s.', 'wporg-themes' ), '//wordpress.org/plugins/theme-check/', '<a href="https://make.wordpress.org/themes">https://make.wordpress.org/themes</a>' );
			}
		}

		// Passed all tests!
		// Let's save everything and get things wrapped up.

		// Get all Trac ticket information set up.
		$this->prepare_trac_ticket();

		// Talk to Trac and let them know about our new version. Or new theme.
		$ticket_id = $this->create_or_update_trac_ticket();

		if ( ! $ticket_id  ) {
			return sprintf( __( 'There was an error creating a Trac ticket for your theme, please report this error to %s', 'wporg-themes' ), '<a href="mailto:themes@wordpress.org">themes@wordpress.org</a>' );
		}

		// Add a or update the Theme Directory entry for this theme.
		$this->create_or_update_theme_post( $ticket_id );

		// Create a new version in SVN.
		$this->add_to_svn();

		// Send theme author an email for peace of mind.
		$this->send_email_notification( $ticket_id );

		do_action( 'theme_upload', $this->theme );

		// Success!
		return sprintf( __( 'Thank you for uploading %1$s to the WordPress Theme Directory. We&rsquo;ve sent you an email verifying that we&rsquo;ve received it. Feedback will be provided at <a href="%2$s">%2$s</a>', 'wporg-themes' ), $this->theme->display( 'Name' ), esc_url( 'https://themes.trac.wordpress.org/ticket/' . $ticket_id ) );
	}

	/**
	 * Creates a temporary directory, and the theme dir within it.
	 */
	public function create_tmp_dirs() {
		// Create a temporary directory if it doesn't exist yet.
		$tmp = '/tmp/wporg-theme-upload';
		if ( ! is_dir( $tmp ) ) {
			mkdir( $tmp, 0777 );
		}

		// Create file with unique file name.
		$this->tmp_dir = tempnam( $tmp, 'WPORG_THEME_' );

		// Remove that file.
		unlink( $this->tmp_dir );

		// Create a directory with that unique name.
		mkdir( $this->tmp_dir, 0777 );

		// Get a sanitized name for that theme and create a directory for it.
		$base_name       = $this->get_sanitized_zip_name();
		$this->theme_dir = "{$this->tmp_dir}/{$base_name}";
		mkdir( $this->theme_dir, 0777 );

		// Make sure we clean up after ourselves.
		add_action( 'shutdown', array( $this, 'remove_files' ) );
	}

	/**
	 * Unzips the uploaded theme and saves it in the temporary theme dir.
	 */
	public function unwrap_package() {
		$base_name = $this->get_sanitized_zip_name();
		$zip_file  = "{$this->tmp_dir}/{$base_name}.zip";

		// Move the uploaded zip in the temporary directory.
		move_uploaded_file( $_FILES['zip_file']['tmp_name'], $zip_file );

		$unzip     = escapeshellarg( self::UNZIP );
		$zip_file  = escapeshellarg( $zip_file );
		$tmp_dir   = escapeshellarg( $this->tmp_dir );

		// Unzip it into the theme directory.
		exec( escapeshellcmd( "{$unzip} {$zip_file} -d {$tmp_dir}/{$base_name}" ) );
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
	 * Returns the the theme post if it already exists in the repository.
	 *
	 * @return WP_Post|null
	 */
	public function get_theme_post() {
		$themes = get_posts( array(
			'name'             => $this->theme->get_stylesheet(),
			'posts_per_page'   => 1,
			'post_type'        => 'repopackage',
			'orderby'          => 'ID',

			/*
			 * Specify post stati so this query returns a result for draft themes, even
			 * if the uploading user doesn't have have the permission to view drafts.
			 */
			'post_status'      => array( 'publish', 'pending', 'draft', 'future' ),
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
			array( 'twenty-ten', 'twenty-eleven', 'twenty-twelve', 'twenty-thirteen', 'twenty-fourteen', 'twenty-fifteen', 'twenty-sixteen', 'twenty-seventeen', 'twenty-eighteen', 'twenty-nineteen', 'twenty-twenty' ),
			array( 'twentyten',  'twentyeleven',  'twentytwelve',  'twentythirteen',  'twentyfourteen',  'twentyfifteen',  'twentysixteen',  'twentyseventeen',  'twentyeighteen',  'twentynineteen',  'twentytwenty'  ),
			$this->theme->get_stylesheet()
		);

		$reserved_slugs = array(
			// Reserve "twenty" names for wordpressdotorg.
			'twentyten', 'twentyeleven', 'twentytwelve','twentythirteen', 'twentyfourteen', 'twentyfifteen',
			'twentysixteen', 'twentyseventeen','twentyeighteen', 'twentynineteen', 'twentytwenty',

			// Theme Showcase URL parameters.
			'browse', 'tag', 'search', 'filter', 'upload', 'commercial',
			'featured', 'popular', 'new', 'updated',
		);

		return in_array( $slug, $reserved_slugs ) && 'wordpressdotorg' !== $this->author->user_login;
	}

	/**
	 * Sends a theme through Theme Check.
	 *
	 * @param array $files All theme files to check.
	 * @return bool Whether the theme passed the checks.
	 */
	public function check_theme( $files ) {
		// Load the theme checking code.
		if ( ! function_exists( 'run_themechecks' ) ) {
			include_once WP_PLUGIN_DIR . '/theme-check/checkbase.php';
		}

		list( $php_files, $css_files, $other_files ) = $this->separate_files( $files );

		// Run the checks.
		$result = run_themechecks( $php_files, $css_files, $other_files );

		// Display the errors.
		$verdict = $result ? array( 'tc-pass', __( 'Pass', 'wporg-themes' ) ) : array( 'tc-fail', __( 'Fail', 'wporg-themes' ) );
		echo '<h4>' . sprintf( __( 'Results of Automated Theme Scanning: %s', 'wporg-themes' ), vsprintf( '<span class="%1$s">%2$s</span>', $verdict ) ) . '</h4>';
		echo '<ul class="tc-result">' . display_themechecks() . '</ul>';
		echo '<div class="notice notice-info"><p>' . __( 'Note: While the automated theme scan is based on the Theme Review Guidelines, it is not a complete review. A successful result from the scan does not guarantee that the theme will pass review. All submitted themes are reviewed manually before approval.', 'wporg-themes' ) . '</p></div>';

		// Override some of the upload checks for child themes.
		if ( !! $this->theme->parent() ) {
			$result = true;
		}

		return $result;
	}

	/**
	 * Sets up all Trac ticket information that we need later.
	 */
	public function prepare_trac_ticket() {
		$this->trac_ticket = new StdClass;

		// todo - check trac xml-rpc, maybe data needs to be escaped before sending it there.
		$this->trac_ticket->summary = sprintf( 'THEME: %1$s – %2$s', $this->theme->display( 'Name' ), $this->theme->display( 'Version' ) );

		// Keywords
		$this->trac_ticket->keywords = array(
			'theme-' . $this->theme->get_stylesheet(),
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
			$this->trac_ticket->diff_line = "\n" . sprintf( __( 'Diff with previous version: %s', 'wporg-themes' ), "https://themes.trac.wordpress.org/changeset?old_path={$this->theme->get_stylesheet()}/{$this->theme_post->max_version}&new_path={$this->theme->get_stylesheet()}/{$this->theme->display( 'Version' )}\n" );
		}

		// Hacky way to prevent a problem with xml-rpc.
		$this->trac_ticket->description = <<<TICKET
{$this->theme->display( 'Name' )} - {$this->theme->display( 'Version' )}

{$this->theme->display( 'Description' )}

Theme URL - {$this->theme->display( 'ThemeURI' )}
Author URL - {$this->theme->display( 'AuthorURI' )}

SVN - https://themes.svn.wordpress.org/{$this->theme->get_stylesheet()}/{$this->theme->display( 'Version' )}
ZIP - https://wordpress.org/themes/download/{$this->theme->get_stylesheet()}.{$this->theme->display( 'Version' )}.zip?nostats=1
{$this->trac_ticket->parent_link}
{$this->trac_ticket->diff_line}
History:
[[TicketQuery(format=table, keywords=~theme-{$this->theme->get_stylesheet()}, col=id|summary|status|resolution|owner)]]

[[Image(https://themes.svn.wordpress.org/{$this->theme->get_stylesheet()}/{$this->theme->display( 'Version' )}/{$this->theme->screenshot}, width=640)]]
TICKET;
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
				require_once WPORGPATH . 'bb-theme/themes/lib/class-trac.php';
			}

			$this->trac = new Trac( 'themetracbot', THEME_TRACBOT_PASSWORD, 'https://themes.trac.wordpress.org/login/xmlrpc' );
		}

		// If there's a previous version and the most current version's status is `new`, we update.
		if ( ! empty( $this->theme_post->max_version ) && 'new' == $this->theme_post->_status[ $this->theme_post->max_version ] ) {
			$ticket_id = (int) $this->theme_post->_ticket_id[ $this->theme_post->max_version ];
			$ticket    = $this->trac->ticket_get( $ticket_id );

			// Make sure the ticket has no resolution and is not approved (3 = ticket attributes).
			if ( empty( $ticket[3]['resolution'] ) && 'approved' !== $ticket[3]['status'] ) {
				$result    = $this->trac->ticket_update( $ticket_id, $this->trac_ticket->description, array( 'summary' => $this->trac_ticket->summary ), true /* Trigger email notifications */ );
				$ticket_id = $result ? $ticket_id : false;
			}

			// In all other cases we create a new ticket.
		} else {
			$ticket_id = $this->trac->ticket_create( $this->trac_ticket->summary, $this->trac_ticket->description, array(
				'type'      => 'theme',
				'keywords'  => implode( ', ', $this->trac_ticket->keywords ),
				'reporter'  => $this->author->user_login,
				'cc'        => $this->author->user_email,
				'priority'  => $this->trac_ticket->priority,
			) );
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
		$post_args   = array(
			'post_author'    => $this->author->ID,
			'post_title'     => $this->theme->get( 'Name' ),
			'post_name'      => $this->theme->get_stylesheet(),
			'post_content'   => $this->theme->get( 'Description' ),
			'post_parent'    => $this->theme->post_parent,
			'post_date'      => $upload_date,
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'post_type'      => 'repopackage',
			'tags_input'     => $this->theme->get( 'Tags' ),
		);

		// If we already have a post, update it.
		if ( ! empty( $this->theme_post ) ) {
			$post_args['ID'] = $this->theme_post->ID;

			$post_id = wp_update_post( $post_args );

			// Otherwise create it for this new theme.
		} else {
			$post_id = wp_insert_post( $post_args );
		}

		// Finally, add post meta.
		$post_meta   = array(
			'_theme_url'   => $this->theme->get( 'ThemeURI' ),
			'_author_url'  => $this->theme->get( 'AuthorURI' ),
			'_status'      => 'new',
			'_upload_date' => $upload_date,
			'_ticket_id'   => $ticket_id,
			'_screenshot'  => $this->theme->screenshot,
		);

		foreach ( $post_meta as $meta_key => $meta_value ) {
			$meta_data = array_filter( (array) get_post_meta( $post_id, $meta_key, true ) );
			$meta_data[ $this->theme->get( 'Version' ) ] = $meta_value;
			update_post_meta( $post_id, $meta_key, $meta_data );
		}

		// Discard versions that are awaiting review.
		wporg_themes_update_version_status( $post_id, $this->theme->get( 'Version' ), 'new' );

		// Add an additional row with the trac ticket ID, to make it possible to find the post by this ID later.
		add_post_meta( $post_id, sanitize_key( '_trac_ticket_' . $this->theme->get( 'Version' ) ), $ticket_id );
	}

	/**
	 * Add theme files to SVN.
	 */
	public function add_to_svn() {
		$import_msg = empty( $this->theme_post ) ?  __( 'New theme: %1$s - %2$s', 'wporg-themes' ) : __( 'New version of %1$s - %2$s', 'wporg-themes' );
		$import_msg = escapeshellarg( sprintf( $import_msg, $this->theme->display( 'Name' ), $this->theme->display( 'Version' ) ) );
		$svn_path   = escapeshellarg( "https://themes.svn.wordpress.org/{$this->theme->get_stylesheet()}/{$this->theme->display( 'Version' )}" );
		$theme_path = escapeshellarg( $this->theme_dir );
		$svn        = escapeshellarg( self::SVN );
		$password   = escapeshellarg( THEME_DROPBOX_PASSWORD );

		exec( escapeshellcmd( "{$svn} --non-interactive --username themedropbox --password {$password} --no-auto-props -m {$import_msg} import {$theme_path} {$svn_path}" ) );
	}

	/**
	 * Sends out an email confirmation to the theme's author.
	 *
	 * @param int $ticket_id Trac ticket ID
	 */
	public function send_email_notification( $ticket_id ) {
		if ( ! empty( $this->theme_post ) ) {
			$email_subject = sprintf( __( '[WordPress Themes] %1$s, new version %2$s', 'wporg-themes' ), $this->theme->display( 'Name' ), $this->theme->display( 'Version' ) );
			$email_content = sprintf( __( "Thank you for uploading version %s of %s.\n\nFeedback will be provided at %s\n\n--\nThe WordPress.org Themes Team\nhttps://make.wordpress.org/themes", 'wporg-themes' ), $this->theme->display( 'Version' ), $this->theme->display( 'Name' ), 'https://themes.trac.wordpress.org/ticket/' . $ticket_id );
		} else {
			$email_subject = sprintf( __( '[WordPress Themes] New Theme - %s', 'wporg-themes' ), $this->theme->display( 'Name' ) );
			$email_content = sprintf( __( "Thank you for uploading %s to the WordPress Theme Directory. If your theme is selected to be part of the directory we'll send a follow up email.\n\nFeedback will be provided at %s\n\n--\nThe WordPress.org Themes Team\nhttps://make.wordpress.org/themes", 'wporg-themes' ), $this->theme->display( 'Name' ), 'https://themes.trac.wordpress.org/ticket/' . $ticket_id );
		}

		wp_mail( $this->author->user_email, $email_subject, $email_content, 'From: themes@wordpress.org' );
	}

	// Helper

	/**
	 * Returns a sanitized version of the uploaded zip file name.
	 *
	 * @return string
	 */
	public function get_sanitized_zip_name() {
		return preg_replace( '|\W|', '', strtolower( basename( $_FILES['zip_file']['name'], '.zip') ) );
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
	 * Separates files in three buckets, PHP files, CSS files, and others.
	 *
	 * Most likely used in preparation for the Theme Check plugin.
	 *
	 * @param array $files Files to separate.
	 * @return array
	 */
	public function separate_files( $files ) {
		$php_files = $css_files = $other_files = array();

		foreach ( $files as $file ) {
			// PHP files.
			if ( true === fnmatch( "*.php", $file ) ) {
				$php_files[ $file ] = php_strip_whitespace( $file );

				// CSS files.
			} else if ( true === fnmatch( "*.css", $file ) ) {
				$css_files[ $file ] = file_get_contents( $file );

				// All the rest.
			} else {
				$other_files[ $file ] = file_get_contents( $file );
			}
		}

		return array( $php_files, $css_files, $other_files );
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

		exec( escapeshellcmd( "{$rm} -rf {$files}" ) );
	}

	/**
	 * Strips invalid UTF-8 characters.
	 *
	 * Non-UTF-8 characters in theme descriptions will causes blank descriptions in themes.trac.
	 *
	 * @param string $string The string to be converted.
	 * @return string The converted string.
	 */
	private function strip_non_utf8( $string ) {
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
	private function sort_by_string_length( $a, $b ) {
		return strlen( $b ) - strlen( $a );
	}
}
