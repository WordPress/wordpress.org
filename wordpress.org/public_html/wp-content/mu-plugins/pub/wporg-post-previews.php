<?php

namespace WordPressDotOrg\Post_Previews;

add_filter( 'ppp_nonce_life', __NAMESPACE__ . '\extend_preview_window' );

/**
 * Extend the post preview window to allow review across timezones, weekends, volunteer schedules, etc.
 *
 * The default is 2 days, which often results in people trying to use expired links.
 *
 * @return int
 */
function extend_preview_window() {
	return DAY_IN_SECONDS * 10;
}
