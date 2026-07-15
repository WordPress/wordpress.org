<?php

/**
 * Search
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( bbp_allow_search() ) : ?>

	<div class="bbp-search-form">
		<form role="search" method="get" id="bbp-search-form">
			<div>
				<label class="screen-reader-text hidden" for="bbp_search"><?php esc_html_e( 'Search for:', 'bborg' ); ?></label>
				<input type="hidden" name="action" value="bbp-search-request" />
				<input type="text" value="<?php bbp_search_terms(); ?>" name="bbp_search" id="bbp_search" placeholder="<?php esc_attr_e( 'Search', 'bborg' ); ?>" />
			</div>
		</form>
	</div>

<?php endif;
