<?php

namespace CampTix\Badge_Generator\InDesign;
use \CampTix\Badge_Generator;
use \CampTix\Badge_Generator\HTML;
use \WordCamp\Logger;

defined( 'WPINC' ) or die();

add_action( 'camptix_menu_tools_indesign_badges', __NAMESPACE__ . '\render_indesign_page' );

/**
 * Render the Indesign Badges page
 */
function render_indesign_page() {
	if ( ! current_user_can( Badge_Generator\REQUIRED_CAPABILITY ) ) {
		return;
	}

	$html_customizer_url = HTML\get_customizer_section_url();

	require_once( dirname( __DIR__ ) . '/views/indesign-badges/page-indesign-badges.php' );
}

/**
 * Build the badge assets for InDesign
 */
function build_assets() {
	try {
		// Security: assets are intentionally saved to a folder outside the web root. See serve_zip_file() for details.
		$assets_folder    = sprintf( '%scamptix-badges-%d-%d', get_temp_dir(), get_current_blog_id(), time() );
		$gravatar_folder  = $assets_folder . '/gravatars';
		$csv_filename     = $assets_folder . '/attendees.csv';
		$zip_filename     = get_zip_filename( $assets_folder );
		$zip_local_folder = pathinfo( $zip_filename, PATHINFO_FILENAME );
		$attendees        = Badge_Generator\get_attendees();

		wp_mkdir_p( $gravatar_folder );
		download_gravatars( $attendees, $gravatar_folder );
		generate_csv( $csv_filename, $zip_local_folder, $attendees, $gravatar_folder );
		create_zip_file( $zip_filename, $zip_local_folder, $csv_filename, $gravatar_folder );
	} finally {
		// todo Delete contents of $assets_folder, then rmdir( $assets_folder );
	}
}

/**
 * Download each attendee's Gravatar
 *
 * @todo Remove set_time_limit() if end up running via a cron job
 *
 * @param array  $attendees
 * @param string $gravatar_folder
 *
 * @throws \Exception
 */
function download_gravatars( $attendees, $gravatar_folder ) {
	set_time_limit( 0 );

	foreach ( $attendees as $attendee ) {
		if ( ! is_email( $attendee->tix_email ) ) {
			continue;
		}

		$request_url = str_replace( '=blank', '=404', $attendee->avatar_url );
		$response    = wp_remote_get( $request_url );
		$image       = wp_remote_retrieve_body( $response );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 404 == $status_code ) {
			continue;
		}

		if ( ! $image || 200 != $status_code ) {
			Logger\log( 'request_failed', compact( 'attendee', 'request_url', 'response' ) );
			throw new \Exception( __( "Couldn't download all Gravatars.", 'wordcamporg' ) );
		}

		$filename      = get_gravatar_filename( $attendee );
		$gravatar_file = fopen( $gravatar_folder . '/' . $filename, 'w' );

		fwrite( $gravatar_file, $image );
		fclose( $gravatar_file );
	}
}

/**
 * Get the filename of the saved Gravatar for the given attendee
 *
 * @todo Returned value is false for input symbols like ♥, and maybe also for emoji
 *
 * @param \WP_Post $attendee
 *
 * @return string
 */
function get_gravatar_filename( $attendee ) {
	return sanitize_file_name( strtolower( sprintf(
		'%d-%s-%s.jpg',
		$attendee->ID,
		remove_accents( $attendee->tix_first_name ),
		remove_accents( $attendee->tix_last_name )
	) ) );
}

/**
 * Get the filename for the Zip file
 *
 * @param $assets_folder
 *
 * @return string
 */
function get_zip_filename( $assets_folder ) {
	return $zip_filename = sprintf(
		'%s/%s-badges.zip',
		$assets_folder,
		sanitize_file_name( sanitize_title( get_wordcamp_name() ) )
	);
}

/**
 * Generate the CSV that InDesign will merge
 *
 * @todo Accept $destination_directory, $empty_twitter, and arbitrary tix_question fields from form input
 *
 * @todo Twitter username gets prefixed with ' by wcorg_esc_csv. Spreadsheet programs will ignore that, but
 * InDesign might not. If it doesn't, need to do something else to prevent the user having to manually remove
 * them.
 *
 * @param string $csv_filename
 * @param string $zip_local_folder
 * @param array  $attendees
 * @param string $gravatar_folder
 *
 * @throws \Exception
 */
function generate_csv( $csv_filename, $zip_local_folder, $attendees, $gravatar_folder ) {
	$csv_handle            = fopen( $csv_filename, 'w' );
	$destination_directory = "Macintosh HD:Users:your_username:Desktop:$zip_local_folder:gravatars:";
	$empty_twitter         = 'replace';

	$header_row = array(
		'First Name', 'Last Name', 'Email Address', 'Ticket', 'Coupon', 'Twitter',
		'@Gravatar' // Prefixed with an @ to let InDesign know that it contains an image
	);

	if ( ! $csv_handle ) {
		Logger\log( 'open_csv_failed' );
		throw new \Exception( __( "Couldn't open CSV file.", 'wordcamporg' ) );
	}

	/*
	 * Intentionally not escaping the header, because we need to preserve the `@` for InDesign. The values are all
	 * hardcoded strings, so they're safe.
	 */
	fputcsv( $csv_handle, $header_row );

	foreach ( $attendees as $attendee ) {
		$row = get_attendee_csv_row( $attendee, $gravatar_folder, $destination_directory, $empty_twitter );

		if ( empty( $row ) ) {
			continue;
		}

		fputcsv( $csv_handle, wcorg_esc_csv( $row ) );
	}

	fclose( $csv_handle );
}

/**
 * Get the CSV row for the given attendee
 *
 * @param \WP_Post $attendee
 * @param string   $gravatar_folder
 * @param string   $destination_directory
 * @param string   $empty_twitter
 *
 * @return array
 */
function get_attendee_csv_row( $attendee, $gravatar_folder, $destination_directory, $empty_twitter ) {
	$row = array();

	if ( 'unknown.attendee@example.org' == $attendee->tix_email ) {
		return $row;
	}

	$gravatar_path     = '';
	$first_name        = ucwords( $attendee->tix_first_name );
	$gravatar_filename = get_gravatar_filename( $attendee );

	if ( file_exists( $gravatar_folder .'/'. $gravatar_filename ) ) {
		$gravatar_path = $destination_directory . $gravatar_filename;
	}

	$row = array(
		'first-name'       => $first_name,
		'last-name'        => ucwords( $attendee->tix_last_name ),
		'email-address'    => $attendee->tix_email,
		'ticket-name'      => $attendee->ticket,
		'coupon-name'      => $attendee->coupon,
		'twitter-username' => format_twitter_username( get_twitter_username( $attendee ), $first_name, $empty_twitter ),
		'gravatar-path'    => $gravatar_path,
	);

	return $row;
}

/**
 * Get an attendee's Twitter username
 *
 * @todo For DRY-ness, make this a public static method in CampTix_Addon_Twitter_Field and refactor
 * attendees_shortcode_item() to use it.
 *
 * @param \WP_Post $attendee
 *
 * @return string
 */
function get_twitter_username( $attendee ) {
	/** @global \CampTix_Plugin $camptix */
	global $camptix;

	$username = '';

	foreach ( $camptix->get_all_questions() as $question ) {
		if ( 'twitter' !== $question->tix_type ) {
			continue;
		}

		if ( ! isset( $attendee->tix_questions[ $question->ID ] ) ) {
			continue;
		}

		$username = trim( $attendee->tix_questions[ $question->ID ] );
		break;
	}

	return $username;
}

/**
 * Format a Twitter username
 *
 * @param string $username
 * @param string $first_name
 * @param string $empty_mode 'replace' to replace empty usernames with first names
 *
 * @return string
 */
function format_twitter_username( $username, $first_name, $empty_mode = 'replace' ) {
	if ( empty ( $username ) ) {
		if ( 'replace' === $empty_mode ) {
			$username = $first_name;
		}
	} else {
		// Strip out everything but the username, and prefix a @
		$username = '@' . preg_replace(
			'/
				(https?:\/\/)?
				(twitter\.com\/)?
				(@)?
			/ix',
			'',
			$username
		);
	}

	return $username;
}

/**
 * Create a Zip file with all of the assets
 *
 * @param string $zip_filename
 * @param string $zip_local_folder
 * @param string $csv_filename
 * @param string $gravatar_folder
 *
 * @throws \Exception
 */
function create_zip_file( $zip_filename, $zip_local_folder, $csv_filename, $gravatar_folder ) {
	if ( ! class_exists( 'ZipArchive') ) {
		Logger\log( 'zip_ext_not_installed' );
		throw new \Exception( __( 'The Zip extension for PHP is not installed.', 'wordcamporg' ) );
	}

	$zip_file    = new \ZipArchive();
	$open_status = $zip_file->open( $zip_filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );

	if ( true !== $open_status ) {
		Logger\log( 'zip_open_failed', compact( 'zip_filename', 'open_status' ) );
		throw new \Exception( __( 'The Zip file could not be created.', 'wordcamporg' ) );
	}

	$zip_file->addFile(
		$csv_filename,
		trailingslashit( $zip_local_folder ) . basename( $csv_filename )
	);

	$zip_file->addGlob(
		$gravatar_folder . '/*',
		0,
		array(
			'add_path'        => $zip_local_folder . '/gravatars/',
			'remove_all_path' => true
		)
	);

	$zip_file->close();
}

/**
 * Serve the Zip file for downloading
 *
 * Security: This is intentionally served through PHP instead of making it accessible directly through wp-content,
 * because the CSV file contains email addresses that we don't want to risk exposing to anyone scraping public
 * folders.
 *
 * @todo If run into problems, maybe look into disabling gzip and/or adding support for range requests.
 * See http://www.media-division.com/the-right-way-to-handle-file-downloads-in-php/
 *
 * @param string $filename
 *
 * @throws \Exception
 */
function serve_zip_file( $filename ) {
	if ( ! current_user_can( Badge_Generator\REQUIRED_CAPABILITY ) ) {
		Logger\log( 'access_denied' );
		throw new \Exception( __( "You don't have authorization to perform this action.", 'wordcamporg' ) );
	}

	set_time_limit( 0 );

	$headers = array(
		'Content-Type'        => 'application/octet-stream',
		'Content-Length'      => filesize( $filename ),
		'Content-Disposition' => sprintf( 'attachment; filename="%s"', basename( $filename ) ),
	);

	foreach ( $headers as $header => $value ) {
		header( sprintf( '%s: %s', $header, $value ) );
	}

	ob_clean();
	flush();
	readfile( $filename );
	die();
}
