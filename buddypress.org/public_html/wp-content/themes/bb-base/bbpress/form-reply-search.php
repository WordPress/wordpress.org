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
		<form role="search" method="get" id="bbp-reply-search-form">
			<div>
				<label class="screen-reader-text hidden" for="rs"><?php esc_html_e( 'Search replies:', 'bborg' ); ?></label>
				<input type="text" value="<?php bbp_search_terms(); ?>" name="rs" id="rs" placeholder="<?php esc_attr_e( 'Search', 'bborg' ); ?>" />
			</div>
		</form>
	</div>

<?php endif;
