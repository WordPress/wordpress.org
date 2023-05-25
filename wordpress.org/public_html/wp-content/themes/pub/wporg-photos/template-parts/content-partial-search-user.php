<?php

if ( $user = \WordPressdotorg\Photo_Directory\Search::query_matches_username( get_search_query( false ) ) ) :
?>
	<section class="search-username">
		<p><?php 
		$username = get_the_author_meta( 'display_name', $user->ID );
		if ( $user !== $user->user_nicename ) {
			$username .= " (@{$user->user_nicename})";
		}

		printf(
			__( 'Might you be looking for photos from %s?', 'wporg-photos' ),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( get_author_posts_url( $user->ID ) ),
				esc_html( $username )
			)
		); ?></p>
	</section>
<?php endif; ?>
