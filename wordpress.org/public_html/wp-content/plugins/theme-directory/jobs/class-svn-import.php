<?php
/**
 * Watch SVN for new revisions and import those into the directory.
 *
 * @package WordPressdotorg\Theme_Directory\Jobs
 */

namespace WordPressdotorg\Theme_Directory\Jobs;
use WordPressdotorg\Theme_Directory\Lib\Exec_With_Logging;
use Exception;
use SimpleXMLElement;
use WPORG_Themes_Upload;

/**
 * Class SVN_Import
 *
 * @package WordPressdotorg\Theme_Directory\Jobs
 */
class SVN_Import {
	use Exec_With_Logging;

	/**
	 * Check for new SVN revisions on the target repo, and queue an import job for each matching.
	 */
	public static function watcher_trigger() {
		$last_revision    = (int) get_option( 'svn_import_last_revision', 0 );
		$current_revision = (int) trim( self::exec(
			'svn info --show-item=revision https://themes.svn.wordpress.org/'
		) );

		if ( ! $last_revision ) {
			trigger_error( "Theme Importing aborting, no starting revision known. Set 'svn_import_last_revision' option. Latest Revision: [{$latest_revision}]", E_USER_WARNING );
			return;
		}

		// Don't need to parse anything if the revisions match.
		if ( $last_revision >= $current_revision ) {
			return;
		}

		// We don't need to include the details of the last revision next time we check for changes.
		$last_revision++;

		// Get the changes since then..
		$svn_output = self::shell_exec( sprintf(
			'svn log https://themes.svn.wordpress.org/ -v --xml -r %d:%d',
			$last_revision,
			$current_revision
		) );

		$xml = simplexml_load_string( $svn_output );
		if ( ! $xml ) {
			return false;
		}

		$theme_changes = array_filter( array_map( function( $element ) { 

			// Get slug/version.
			$slug = '';
			$version = '';
			foreach ( $element->xpath( 'paths/path[@kind="dir"]') as $path ) {
				if ( preg_match( '!^/(?P<slug>[^/]+)/(?P<version>[^/]+)(/.+)?$!', (string) $path, $m ) ) {
					$slug    = $m['slug'];
					$version = $m['version'];
					break;
				}
			}
			if ( ! $slug || ! $version ) {
				return false;
			}

			$changeset = (int) $element->xpath( '@revision' )[0];
			$author    = trim( (string) $element->xpath( 'author' )[0] );
			$msg       = trim( (string) $element->xpath( 'msg' )[0] );

			$info = compact( 'slug', 'version', 'changeset', 'author', 'msg' );

			// Allow for including/skipping revisions based on external conditionals.
			$should_import = 'themedropbox' !== $author;
			$should_import = apply_filters( 'themes_svn_should_import', $should_import, $info );

			// DEBUG visible in cron log.
			echo "[{$changeset}] $slug: $version by $author " . ( $should_import ? 'Importing from SVN.' : 'Skipping.' ) . "\n";

			if ( ! $should_import ) {
				return false;
			}

			return $info;
		}, $xml->xpath('/log/logentry') ) );

		$theme_changes = array_unique( $theme_changes, SORT_REGULAR );

		foreach ( $theme_changes as $change ) {
			wp_schedule_single_event( time(), 'theme_directory_svn_import', array( $change ) );
		}

		update_option( 'svn_import_last_revision', $current_revision );
	}

	/**
	 * Import a Theme from SVN into the directory on a cron task.
	 */
	public static function import_trigger( $args ) {
		include_once dirname( __DIR__ ) . '/class-wporg-themes-upload.php';

		if ( empty( $args['slug'] ) || empty( $args['version'] ) ) {
			trigger_error( 'Theme Import aborted, invalid input provided: ' . json_encode( $args ), E_USER_WARNING );
			return;
		}

		$uploader = new WPORG_Themes_Upload;

		$return = $uploader->process_update_from_svn(
			$args['slug'],
			$args['version'],
			$args['changeset'],
			$args['author'],
			$args['msg']
		);

		if ( is_wp_error( $return ) ) {

			// Retry once in the event of a WordPress.org issue exporting from themes.svn.wordpress.org.
			if ( 'svn_error' === $return->get_error_code() ) {
				if ( empty( $args['retry'] ) ) {
					$args['retry'] = $return;
					wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'theme_directory_svn_import', $args );
					return;
				} else {
					throw new Exception( 'Theme Import Failure: ' . $return->get_error_code() . ' ' . $return->get_error_message() );
				}
			}

			/*
			 * Otherwise email the author about this problem.
			 * NOTE: This email has a HTML content type, as Theme Check output is HTML.
			 * TRANSLATION: These strings are not marked for translated, as they will always be English at present.
			 */
			wp_mail(
				$uploader->author->user_email,
				sprintf(
					'Theme Import Failure: %s [%d] %s',
					$uploader->theme_post->post_title,
					$args['changeset'],
					$args['msg']
				),
				sprintf(
					nl2br(
						// Intentionally not translated. See above.
						"Hi %s,\n\n" .
						"Your theme update for %s has failed requirements.\n" .
						"Please see the below error and commit an updated version, this may not be the only error present.\n\n%s\n\n" .
						"Please review the latest <a href='%s'>Theme Guidelines</a>.\n" .
						"----\nWordPress Theme Directory",
					),
					esc_html( $uploader->author->display_name ?: $uploader->author->user_login ),
					$uploader->theme->display('Name') . ' ' . $uploader->theme->display('Version'),
					'<div style="margin-left: 30px">' . $return->get_error_message() . '</div>',
					'https://make.wordpress.org/themes/handbook/review/required/'
				),
				[
					'From: "WordPress Theme Directory" <themes@wordpress.org>',
					'Content-Type: text/html; charset=UTF-8'
				]
			);
		}
	}
}
