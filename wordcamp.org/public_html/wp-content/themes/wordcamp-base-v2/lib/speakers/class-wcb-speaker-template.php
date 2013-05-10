<?php

/**
 * wcb_speaker_query()
 *
 * Creates and fetches the speaker query.
 *
 * @return WP_Query The speaker query.
 */
function wcb_speaker_query( $args = '' ) {
	global $wcb_speaker_query;

	if ( isset( $wcb_speaker_query ) )
		return $wcb_speaker_query;


	$defaults = array(
		'post_type'         => WCB_SPEAKER_POST_TYPE,
		'orderby'           => 'title',
		'order'             => 'DESC',
		'posts_per_page'    => -1,
	);
	$args = wp_parse_args( $args, $defaults );

	$wcb_speaker_query = new WP_Query( $args );

	// Sort posts by last name
	// (by last word in the title, really)
	$sorter = array();
	foreach ( $wcb_speaker_query->posts as $speaker ) {
		// Place the "last name" first.
		$name_parts     = explode( " ", trim( $speaker->post_title ) );
		$inverted_name  = array_pop( $name_parts ) . " " . implode( " ", $name_parts );

		// Ensure our name is unique.
		$sorted_name    = $inverted_name;
		$index          = 0;
		while ( isset( $sorter[ $sorted_name ] ) ) {
			$index++;
			$sorted_name = $inverted_name . $index;
		}

		$sorter[ $sorted_name ] = $speaker;
	}
	ksort( $sorter );
	$wcb_speaker_query->posts = array_values( $sorter );

	return $wcb_speaker_query;
}

/**
 * wcb_have_speakers()
 *
 * Whether there are more speakers available in the loop.
 *
 * @return object WordCamp information
 */
function wcb_have_speakers() {
	$query = wcb_speaker_query();
	return $query->have_posts();
}

/**
 * wcb_rewind_speakers()
 *
 * Rewind the speakers loop.
 */
function wcb_rewind_speakers() {
	$query = wcb_speaker_query();
	return $query->rewind_posts();
}

/**
 * wcb_the_speaker()
 *
 * Loads up the current speaker in the loop.
 *
 * @return object WordCamp information
 */
function wcb_the_speaker() {
	$query = wcb_speaker_query();
	return $query->the_post();
}

/**
 * wcb_get_speaker_gravatar()
 *
 * Gets the gravatar of the current speaker.
 *
 * @return object WordCamp information
 */
function wcb_get_speaker_gravatar( $size=96 ) {
	$speakers = wcb_get('speakers');
	return get_avatar( $speakers->meta_manager->get( get_the_ID(), 'email' ), $size );
}

/**
 * wcb_get_speaker_slug()
 *
 * Gets the slug for the current speaker.
 *
 * @return object WordCamp information
 */
function wcb_get_speaker_slug() {
	global $post;
	return $post->post_name;
}

?>