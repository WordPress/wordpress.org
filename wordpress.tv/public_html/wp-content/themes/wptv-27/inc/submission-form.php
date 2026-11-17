<?php
/**
 * Fixed /submit-video/ form block. The page template owns this block; the
 * page content contains only the editable instructions.
 *
 * Markup and behavior come from wptv2's anon-upload-template.php; production
 * category IDs become slug lookups. Posts to inc/submissions.php.
 *
 * @package WordPressTV
 */

namespace WordPressTV\SubmissionForm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns a category ID by slug, or 0.
 *
 * @param string $slug Category slug.
 * @return int
 */
function category_id( $slug ) {
	$term = get_term_by( 'slug', $slug, 'category' );
	return $term ? (int) $term->term_id : 0;
}

/*
 * The functions below only display the redirect codes and prefilled values
 * that inc/submissions.php sends back; nothing is saved, and every value is
 * escaped on output.
 */
// phpcs:disable WordPress.Security.NonceVerification.Recommended

/** Messages for the ?error= and ?success= redirects (wptv2 wording). */
function message() {
	// WP::parse_request() unsets $_GET['error'] for resolved permalinks.
	// The legacy template reads $_REQUEST, which retains the redirect code.
	if ( ! empty( $_REQUEST['error'] ) ) {
		$messages = array(
			1  => __( 'Error: please select a video file.', 'wptv' ),
			2  => __( 'Error: invalid file type.', 'wptv' ),
			3  => __( 'Error: unknown file type.', 'wptv' ),
			4  => __( 'Upload error: the video cannot be saved.', 'wptv' ),
			5  => __( 'Unknown error. Please try again later.', 'wptv' ),
			6  => __( 'Error: invalid submission.', 'wptv' ),
			10 => __( 'Error: please enter your name.', 'wptv' ),
			11 => __( 'Error: please enter your email address.', 'wptv' ),
			12 => __( 'Error: please enter a valid email address.', 'wptv' ),
			13 => __( "Error: please leave the first field empty. (It helps us know you're not a spammer.)", 'wptv' ),
			14 => __( 'Error: please enter a valid WordPress.org username for the producer, or leave the field empty.', 'wptv' ),
			15 => __( 'Error: form nonce was missing or invalid.', 'wptv' ),
		);
		$code     = (int) $_REQUEST['error'];
		return '<div class="wptv-submit__notice is-error" role="alert"><p>' . esc_html( $messages[ $code ] ?? '' ) . '</p></div>';
	}
	if ( ! empty( $_GET['success'] ) ) {
		return '<div class="wptv-submit__notice is-success" role="status"><p>' . esc_html__( 'Thank you for submitting a video; it was uploaded successfully.', 'wptv' ) . '</p><p>' . esc_html__( 'Submit another?', 'wptv' ) . '</p></div>';
	}
	return '';
}

/**
 * Prefilled value from the redirect query, as in wptv2.
 *
 * @param string $key Field name.
 * @return string
 */
function prefill( $key ) {
	if ( ! isset( $_GET[ $key ] ) || ! is_string( $_GET[ $key ] ) ) {
		return '';
	}
	// The description keeps its line breaks, as when it is saved.
	if ( 'wptv_video_description' === $key ) {
		return sanitize_textarea_field( wp_unslash( $_GET[ $key ] ) );
	}
	return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
}

/**
 * Category checkboxes, keeping prefilled selections.
 *
 * @param \WP_Term[] $terms    Categories.
 * @param array      $selected Selected category IDs, as keys.
 * @return string
 */
function checkboxes( $terms, $selected ) {
	$html = '<ul class="cats-checkboxes">';
	foreach ( $terms as $term ) {
		$html .= sprintf(
			'<li id="category-%1$d"><label class="selectit"><input value="%1$d" type="checkbox" name="post_category[]" id="in-category-%1$d" %2$s> %3$s</label></li>',
			(int) $term->term_id,
			isset( $selected[ $term->term_id ] ) ? 'checked="checked" ' : '',
			esc_html( $term->name )
		);
	}
	return $html . '</ul>';
}

/**
 * A labeled text input.
 *
 * @param string $id          Field ID and name.
 * @param string $label       Label text.
 * @param bool   $required    Whether to mark the field as required.
 * @param string $type        Input type.
 * @param string $placeholder Placeholder text.
 * @return string
 */
function text_field( $id, $label, $required = false, $type = 'text', $placeholder = '' ) {
	return sprintf(
		'<p class="wptv-submit__row"><label for="%1$s">%2$s%3$s</label><input type="%4$s" id="%1$s" name="%1$s" value="%5$s"%6$s /></p>',
		esc_attr( $id ),
		esc_html( $label ),
		$required ? '<span class="required"> * </span>' : '',
		esc_attr( $type ),
		esc_attr( prefill( $id ) ),
		$placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : ''
	);
}

/** Renders the submission form. */
function render() {
	if ( post_password_required() ) {
		return '<div class="pass-form">' . get_the_password_form() . '</div>';
	}
	wp_enqueue_script( 'wptv-submission-form', get_theme_file_uri( 'assets/js/submission-form.js' ), array( 'jquery' ), wp_get_theme()->get( 'Version' ), array( 'in_footer' => true ) );

	$message  = message();
	$selected = isset( $_GET['post_category'] ) ? array_flip( array_map( 'intval', (array) $_GET['post_category'] ) ) : array();
	$location = category_id( 'location' );
	$year     = category_id( 'year' );

	$html  = '<div ' . get_block_wrapper_attributes( array( 'class' => 'wptv-submit' ) ) . '>' . $message;
	$html .= '<noscript><div class="wptv-submit__notice is-error"><p>' . esc_html__( 'This form requires JavaScript. Please enable it in your browser and reload the page.', 'wptv' ) . '</p></div></noscript>';
	if ( ! $message ) {
		$html .= '<p class="wptv-submit__guidelines-link">' . sprintf(
			/* translators: %s: link to the submission guidelines on this page. */
			esc_html__( 'Please %s, then submit your video below:', 'wptv' ),
			'<a href="#guidelines">' . esc_html__( 'review the guidelines', 'wptv' ) . '</a>'
		) . '</p>';
	}
	$html .= sprintf(
		'<form method="post" action="%1$s" data-xhr-action="%2$s" id="video-upload-form" enctype="multipart/form-data" data-wordcamp="%3$d" data-world="%4$d" data-named="%5$s">',
		esc_url( admin_url( 'admin-post.php' ) ),
		esc_url( admin_url( 'admin-post.php?xhr=1' ) ),
		category_id( 'wordcamptv' ),
		category_id( 'world-wide-web' ),
		esc_attr( implode( ',', array_filter( array( category_id( 'buddycamp' ), category_id( 'wordpress-translation-day' ) ) ) ) )
	);
	$html .= wp_nonce_field( 'wptv-upload-video', 'wptvvideon', true, false );
	$html .= '<input type="hidden" name="action" value="wptv_video_upload" />';
	// Bots fill this hidden field.
	$html .= '<p id="wptv_honey_container"><label for="wptv_honey">' . esc_html__( 'Leave this empty', 'wptv' ) . '</label><input type="text" id="wptv_honey" name="wptv_honey" value="" tabindex="-1" autocomplete="off" /></p>';
	if ( ! is_user_logged_in() ) {
		$html .= text_field( 'wptv_uploaded_by', __( 'Uploaded by', 'wptv' ), true );
		$html .= text_field( 'wptv_email', __( 'Email address', 'wptv' ), true );
	}
	$html .= text_field( 'wptv_video_title', __( 'Video title', 'wptv' ) );
	$html .= text_field( 'wptv_speakers', __( 'Speakers', 'wptv' ), false, 'text', 'John Smith, Jane Doe' );
	$html .= text_field( 'wptv_language', __( 'Language', 'wptv' ) );
	$html .= text_field( 'wptv_date', __( 'Date recorded', 'wptv' ), false, 'date' );
	if ( $location ) {
		$html .= '<div class="location wptv-submit__row wptv-submit__row--large" role="group" aria-labelledby="wptv-location-label"><span class="wptv-submit__label" id="wptv-location-label">' . esc_html__( 'Location', 'wptv' ) . '</span><div class="wptv-submit__controls">' . checkboxes(
			get_categories(
				array(
					'parent'     => $location,
					'hide_empty' => false,
				)
			),
			$selected
		) . '</div></div>';
	}
	$html .= '<div class="cats wptv-submit__row wptv-submit__row--large" role="group" aria-labelledby="wptv-category-label"><span class="wptv-submit__label" id="wptv-category-label">' . esc_html__( 'Category', 'wptv' ) . '</span><div class="wptv-submit__controls">';
	$html .= '<p class="wptv-submit__check"><input type="checkbox" id="wptv_video_wordcamp" name="wptv_video_wordcamp" ' . ( prefill( 'wptv_video_wordcamp' ) ? 'checked="checked"' : '' ) . ' /> <label for="wptv_video_wordcamp">' . esc_html__( 'This is a WordCamp video', 'wptv' ) . '</label></p>';
	$html .= checkboxes(
		get_categories(
			array(
				'exclude_tree' => array_filter( array( $location, $year ) ),
				'parent'       => 0,
				'hide_empty'   => false,
			)
		),
		$selected
	) . '</div></div>';
	$html .= text_field( 'wptv_event', __( 'Event', 'wptv' ) );
	$html .= text_field( 'wptv_producer_username', __( 'Producer WordPress.org Username', 'wptv' ) );
	$html .= '<p class="wptv-submit__row wptv-submit__row--large"><label for="wptv_video_description">' . esc_html__( 'Description', 'wptv' ) . '</label><textarea name="wptv_video_description" id="wptv_video_description" rows="8" cols="40">' . esc_textarea( prefill( 'wptv_video_description' ) ) . '</textarea></p>';
	$html .= text_field( 'wptv_slides_url', __( 'Slides URL', 'wptv' ) );
	$html .= '<p class="wptv-submit__row"><label for="wptv_file">' . esc_html__( 'Video file', 'wptv' ) . '<span class="required"> * </span></label><input type="file" name="wptv_file" id="wptv_file" /></p>';
	$html .= '<p class="last"><input type="submit" id="wptv_video_upload" class="wp-element-button" hidden value="' . esc_attr__( 'Submit', 'wptv' ) . '" /></p>';
	$html .= '<p id="upload-progress" hidden><progress value="0" max="100"></progress> <span class="percent"></span><br><span class="status" aria-live="polite"></span> <a href="#" class="abort">' . esc_html__( 'Cancel', 'wptv' ) . '</a></p>';
	return $html . '</form></div>';
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

/** Registers a server-rendered block used only by the fixed page template. */
function register_block() {
	register_block_type(
		'wptv/submission-form',
		array(
			'api_version'     => 3,
			'title'           => __( 'Video submission form', 'wptv' ),
			'category'        => 'widgets',
			'supports'        => array(
				'autoRegister' => true,
				'inserter'     => false,
				'html'         => false,
				'multiple'     => false,
			),
			'render_callback' => __NAMESPACE__ . '\\render',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_block' );
