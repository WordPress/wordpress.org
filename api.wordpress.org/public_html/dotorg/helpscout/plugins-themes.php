<?php
// Simple sidebar to list plugin/theme details.

// $request is the validated HelpScout request.
$request = include __DIR__ . '/common.php';

// default empty output
$html = '';

// look up profile url by email
$email = get_user_email_for_email( $request );
$user  = get_user_by( 'email', $email );

if ( ! $user ) {
	echo json_encode( array( 'html' => $html ) );
	die();
}

foreach ( [
	/* site_id => [ textual singular, post_type ] */
	WPORG_PLUGIN_DIRECTORY_BLOGID => [ 'plugin', 'plugin' ],
	WPORG_THEME_DIRECTORY_BLOGID  => [ 'theme', 'repopackage' ]
] as $site_id => $details ) {
	list( $type, $post_type ) = $details;

	switch_to_blog( $site_id );

	$slugs = [];
	if ( 'plugin' === $type ) {
		// Committer to a plugin.
		$slugs = $wpdb->get_col( $wpdb->prepare( 'SELECT path FROM `' . PLUGINS_TABLE_PREFIX . 'svn_access' . '` WHERE user = %s', $user->user_login ) );
		$slugs = array_filter( array_map( function( $slug ) {
			return ltrim( $slug, '/' ) ?: false;
		}, $slugs ) );

		// TODO: Would be nice to pull for support reps too, but that's less common.
	}

	$slugs    = $slugs ? '"' . implode( '", "', array_map( 'esc_sql', $slugs ) ) . '"' : '';
	$or_slugs = $slugs ? "OR post_name IN( {$slugs} )" : '';

	$counts = $wpdb->get_results( $wpdb->prepare(
		"SELECT post_status, COUNT(*) as count, group_concat( ID ORDER BY post_title ) as ids, group_concat( post_title ORDER BY post_title SEPARATOR ', ' ) as titles
		FROM $wpdb->posts
		WHERE post_type = %s AND ( post_author = %s {$or_slugs} )
		GROUP BY post_status
		ORDER BY FIELD( post_status, 'new', 'pending', 'publish', 'disabled', 'delisted', 'closed', 'approved', 'suspended', 'rejected', 'draft' )",
		$post_type,
		$user->ID
	) );

	if ( $counts ) {
		$total       = array_sum( wp_list_pluck( $counts, 'count' ) );
		$ids         = wp_parse_id_list( implode( ',', wp_list_pluck( $counts, 'ids' ) ) );
		$post_statii = implode(
			', ',
			array_map(
				function( $i ) {
					return sprintf( "%s: %s (%s)", $i->post_status, $i->count, $i->titles );
				},
				$counts
			)
		);

		$html .= sprintf(
			'<p><a href="%s" title="%s">%s</a></p>',
			add_query_arg( [ 'post_type' => $post_type, 'author' => $user->ID ], admin_url( 'edit.php' ) ),
			esc_attr( $post_statii ),
			ucwords( _n( "$total $type", "{$total} {$type}s", $total ) ) // Real bad internationalisation where internationalisation will never be used.
		);

		// plugins@ and themes@ - expand and provide direct links.
		if ( "{$type}s@wordpress.org" === $request->mailbox->email ) {
			$html .= '<ul>';
			foreach ( $ids as $post_id ) {
				$post        = get_post( $post_id );
				$post_status = '';

				switch ( $post->post_status ) {
					// Plugins
					case 'rejected':
						$post_status = '(Rejected)';
						break;
					case 'closed':
					case 'disabled':
						$post_status = '(Closed)';
						break;
					case 'pending':
					case 'new':
						$post_status = '(In Review)';
						break;
					case 'approved':
						$post_status = '(Approved)';
						break;

					// Themes
					case 'draft':
						$post_status = '(In Review or Rejected)';
						break;
					case 'suspended':
						$post_status = '(Suspended)';
						break;
					case 'delisted':
						$post_status = '(Delisted)';
						break;
				}

				$html .= sprintf(
					'<li><a href="%s">%s</a> <a href="%s">#</a> %s</li>',
					/* get_edit_post_link( $post ), // Won't work as post type not registered */
					esc_url( add_query_arg( [ 'action' => 'edit', 'post' => $post_id ], admin_url( 'post.php' ) ) ),
					esc_html( $post->post_title ),
					get_permalink( $post ),
					esc_html( $post_status )
				);
			}
			$html .= '</ul>';
		}

	}
	restore_current_blog();
}

// response to HS is just HTML to display in the sidebar
echo json_encode( array( 'html' => $html ) );
