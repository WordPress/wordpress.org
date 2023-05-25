<?php
namespace WordPressdotorg\Trac\Watcher;

add_action( 'admin_post_svn_save', function() {
	global $wpdb;

	if ( ! current_user_can( 'publish_posts' ) ) {
		die( '-1' );
	}
	check_admin_referer( 'edit_svn_prop' );

	$svns = SVN\get_svns();
	$svn = $svns[ $_REQUEST['svn'] ] ?? false;
	$rev = $_REQUEST['revision'] ?? false;

	if ( empty( $svn ) ) {
		die( -1 );
	}

	$action = $_REQUEST['what'] ?? false;
	if ( ! in_array( $action, [ 'add', 'edit', 'delete' ] ) ) {
		die( -1 );
	}

	$user = Props\find_user_id( wp_unslash( $_REQUEST['user_id'] ?? '' ) ) ?: null;

	// Operation save. Step one, find the prop.
	$props = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$svn['props_table']} WHERE revision = %d", $rev ) );

	$the_prop = false;
	if ( ! empty( $_REQUEST['prop_name_orig'] ) ) {
		$the_prop = wp_list_filter( $props, [ 'prop_name' => wp_unslash( $_REQUEST['prop_name_orig'] ) ] );
		$the_prop = $the_prop ? array_shift( $the_prop ) : false;
	}

	if ( 'delete' == $action && $the_prop ) {
		$wpdb->delete(
			$svn['props_table'],
			[
				'id'       => $the_prop->id,
				'revision' => $rev,
			]
		);
	} elseif ( 'edit' === $action && $the_prop ) {
		if ( ! isset( $_REQUEST['prop_name'] ) ) {
			die( -1 );
		}

		$prop_name = wp_unslash( $_REQUEST['prop_name'] );
		if ( ! $user && $prop_name != $the_prop->prop_name ) {
			$user = Props\find_user_id( $prop_name );
		}

		// Updating one.
		$wpdb->update(
			$svn['props_table'],
			[
				'prop_name' => $prop_name,
				'user_id'   => $user ?: null,
			],
			[
				'id' => $the_prop->id,
			]
		);

		// Maybe update other occurences of this typo.
		if ( $prop_name === $the_prop->prop_name && ! $the_prop->user_id && $user ) {
			$wpdb->update(
				$svn['props_table'],
				[
					'user_id'   => $user,
				],
				[
					'prop_name' => $the_prop->prop_name,
					'user_id'   => null
				]
			);
		}

		// If editing, and cleared the prop, Delete instead.
		if ( empty( $prop_name ) ) {
			$wpdb->delete(
				$svn['props_table'],
				[
					'id'        => $the_prop->id,
					'revision'  => $rev,
				]
			);
		}

	} elseif ( 'add' === $action ) {
		// Adding one?

		// Use the 'prop name' field first, otherwise fall back to the user_id field if the former is blank
		$prop_name = wp_unslash( $_REQUEST['prop_name'] ?? '' );
		if ( ! $prop_name ) {
			$prop_name = wp_unslash( $_REQUEST['user_id'] ?? '' );
		};

		if ( ! $prop_name ) {
			die( -1 );
		}

		if ( ! $user ) {
			$user = Props\find_user_id( $prop_name );
		}

		$wpdb->insert(
			$svn['props_table'],
			[
				'revision'  => $rev,
				'prop_name' => $prop_name,
				'user_id'   => $user,
			]
		);

		// Update any other occurrences of this typo.
		if ( $user ) {
			$wpdb->update(
				$svn['props_table'],
				[
					'user_id'   => $user,
				],
				[
					'prop_name' => $prop_name,
					'user_id'   => null
				]
			);
		}
	}

	// Output the replacement `<td>`'s
	$table = new Commits_List_Table( $svn );
	$table->prepare_items( [ 'revision' => $rev ] );
	$table->single_row_columns( $table->items[0] );

} );

add_action( 'admin_post_svn_reparse', function() {
	global $wpdb;

	if ( ! current_user_can( 'publish_posts' ) ) {
		die( '-1' );
	}
	check_admin_referer( 'reparse_svn' );

	$svns = SVN\get_svns();
	$svn = $svns[ $_REQUEST['svn'] ] ?? false;
	$rev = $_REQUEST['revision'] ?? false;

	if ( empty( $svn ) || empty( $rev ) ) {
		die( -1 );
	}

	// Get the commit details.
	$details = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$svn['rev_table']} WHERE id = %d", $rev ) );
	if ( ! $details ) {
		die( -1 );
	}

	// Reparse
	$include_old = strtotime( $details->date ) < strtotime( '2020-01-01' );
	$raw_props   = Props\from_log( $details->message, $include_old );

	// Fetch all the user_id's
	$props = [];
	foreach ( $raw_props as $prop ) {
		$props[ $prop ] = Props\find_user_id( $prop, $svn );
	}

	// Remove the props for the commit, after reparsing. This is so that any usernames only corrected within that commit get picked up.
	$wpdb->delete(
		$svn['props_table'],
		[ 'revision' => $rev ]
	);

	// Reinsert
	foreach ( $props as $prop => $user_id ) {
		$data = [
			'revision'  => $rev,
			'prop_name' => $prop,
		];

		if ( $user_id ) {
			$data['user_id'] = $user_id;
		}

		$wpdb->insert( $svn['props_table'], $data );
	}

	// Output the replacement `<td>`'s
	$table = new Commits_List_Table( $svn );
	$table->prepare_items( [ 'revision' => $rev ] );
	$table->single_row_columns( $table->items[0] );
} );