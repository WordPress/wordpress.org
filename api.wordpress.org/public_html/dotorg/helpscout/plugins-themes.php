<?php
// Simple sidebar to list plugin/theme details.

// $request is the validated HelpScout request.
$request = include __DIR__ . '/common.php';

// default empty output
$html = '';

// look up profile url by email
$email = get_user_email_for_email( $request );
$user  = get_user_by( 'email', $email );

foreach ( [
	/* site_id => [ textual singular, post_type ] */
	WPORG_PLUGIN_DIRECTORY_BLOGID => [ 'plugin', 'plugin' ],
	WPORG_THEME_DIRECTORY_BLOGID  => [ 'theme', 'repopackage' ]
] as $site_id => $details ) {
	list( $type, $post_type ) = $details;

	$is_this_inbox = ( "{$type}s@wordpress.org" === $request->mailbox->email );

	switch_to_blog( $site_id );

	$slugs = [];

	if ( 'plugin' === $type && $user ) {
		// Committer to a plugin.
		$committer_plugins = $wpdb->get_col( $wpdb->prepare( 'SELECT path FROM `' . PLUGINS_TABLE_PREFIX . 'svn_access' . '` WHERE user = %s', $user->user_login ) );
		array_map(
			function( $slug ) use( $slugs ) {
				$slug = ltrim( $slug, '/' );
				if ( $slug ) {
					$slugs[] = $slug;
				}
			},
			$committer_plugins
		);

		// TODO: Would be nice to pull for support reps too, but that's less common.
	}

	// Reported themes, shortcut, assume the slug is the title.. since it is..
	if (
		'theme' === $type &&
		str_starts_with( $request->ticket->subject ?? '', 'Reported Theme:' )
	) {
		$slugs[] = sanitize_title_with_dashes( trim( explode( ':', $request->ticket->subject )[1] ) );
	}

	$lookup_by_user = $user ? $user->ID : false;
	$counts         = false;
	$sources        = [
		'by_email_author'
	];
	if ( $is_this_inbox ) {
		$sources[] = 'check_email';
	}
	foreach ( $sources as $source ) {
		if ( 'check_email' === $source ) {
			// Check the email for a plugin/theme name.
			$mentioned = get_plugin_or_theme_from_email( $request );
			if ( empty( $mentioned[ "{$type}s"] ) ) {
				break;
			}

			$lookup_by_user = false;
			$slugs          = $mentioned[ "{$type}s" ];
		}

		$slugs       = $slugs          ? '"' . implode( '", "', array_map( 'esc_sql', array_unique( $slugs ) ) ) . '"' : '';
		$or_slugs    = $slugs          ? "post_name IN( {$slugs} )" : '';
		$post_author = $lookup_by_user ? $wpdb->prepare( "post_author = %s", $lookup_by_user ) : '';

		$where = implode( ' OR ', array_filter( [ $post_author, $or_slugs ] ) );

		if ( ! $where ) {
			continue;
		}

		$counts = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_status, COUNT(*) as count, group_concat( ID ORDER BY post_title ) as ids, group_concat( post_title ORDER BY post_title SEPARATOR ', ' ) as titles
			FROM $wpdb->posts
			WHERE post_type = %s AND ( {$where} )
			GROUP BY post_status
			ORDER BY FIELD( post_status, 'new', 'pending', 'publish', 'disabled', 'delisted', 'closed', 'approved', 'suspended', 'rejected', 'draft' )",
			$post_type,
		) );

		if ( $counts && 'check_email' === $source ) {
			$html .= '<p><strong>Note: ' . ucwords( $type ) . ' may not be authored the email author.</strong><p>';
		}

		if ( $counts ) {
			break;
		}
	}

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
			add_query_arg( [ 'post_type' => $post_type, 'author' => $user->ID ?? '' ], admin_url( 'edit.php' ) ),
			esc_attr( $post_statii ),
			ucwords( _n( "$total $type", "{$total} {$type}s", $total ) ) // Real bad internationalisation where internationalisation will never be used.
		);

		// plugins@ and themes@ - expand and provide direct links.
		if ( $is_this_inbox ) {
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
