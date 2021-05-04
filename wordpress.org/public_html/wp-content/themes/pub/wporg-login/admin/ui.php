<?php

include __DIR__  . '/class-user-registrations-list-table.php';

add_action( 'admin_menu', function() {
	add_menu_page(
		'Pending User Registrations',
		'Pending User Registrations',
		'promote_users',
		'user-registrations',
		'wporg_login_admin_page',
		'dashicons-admin-users',
		1
	);
});

function wporg_login_admin_action_text( $action ) {
	switch ( $action ) {
		case 'resent-email':
			return 'The registration email has been resent.';
		case 'approved':
			return 'The registration has been approved, and a confirmation email has been sent.';
		case 'deleted':
			return 'The registration record has been removed.';
		case 'blocked':
			return 'The registration has been blocked.';
		case 'blocked_account':
			return 'Account blocked.';
		default:
			return 'Action performed.';
	}
}

function wporg_login_admin_page() {
	$wp_list_table = new User_Registrations_List_Table();
	$wp_list_table->prepare_items();

	?><script>
	jQuery( document ).ready( function($) {
		$( 'table .row-actions a' ).click( function( e ) {
			e.preventDefault();

			var $this = $(this),
				$tr   = $this.parents('tr'),
				$tds  = $tr.find( 'td:not(:first)' );

			$tds.remove();
			$tr.find( '.row-actions' ).remove();
			$tr.append( "<td colspan=" + $tds.length + ">...</td>" );

			var url = $this.prop('href') + '&ajax=1';

			$.get( url, function( data ) {
				$tr.find('td:last').text( data );
			} );
		});
	} );
	</script>
	<style>
		table.wp-list-table td > a {
			color: inherit;
		}
		table.wp-list-table td > a:hover {
			text-decoration: underline;
		}
		table.wp-list-table .delete-red {
			color: #b32d2e;
		}
	</style>
	<?php

	echo '<div class="wrap">';
	echo '<h1 class="wp-heading-inline">Pending User Registrations</h1>';
	echo '<hr class="wp-header-end">';

	if ( isset( $_GET['action'] ) ) {
		echo '<div class="updated notice"><p>';
		echo wporg_login_admin_action_text( $_GET['action'] );
		echo '</p></div>';
	}

	echo '<form>';
	printf( '<input type="hidden" name="page" value="%s">', esc_attr( $_GET['page'] ) );

	$wp_list_table->views();
	$wp_list_table->search_box( 'Search', 's' );
	$wp_list_table->display();

	echo '</form>';
	echo '</div>';
}

add_action( 'admin_post_login_resend_email', function() { 
	if ( ! current_user_can( 'promote_users' ) ) {
		wp_die();
	}

	$email = $_REQUEST['email'] ?? '';

	check_admin_referer( 'resend_' . $email );

	if ( $email ) {
		wporg_login_send_confirmation_email( $email );
	}

	if ( isset( $_GET['ajax'] ) ) {
		die( wporg_login_admin_action_text( 'resent-email' ) );
	}

	wp_safe_redirect( add_query_arg(
		's',
		urlencode( $email ),
		'https://login.wordpress.org/wp-admin/index.php?page=user-registrations&action=resent-email'
	) );
	exit;
} );

add_action( 'admin_post_login_mark_as_cleared', function() { 
	if ( ! current_user_can( 'promote_users' ) ) {
		wp_die();
	}

	$email = $_REQUEST['email'] ?? '';

	check_admin_referer( 'clear_' . $email );

	$user = wporg_get_pending_user( $email );
	if ( $user ) {
		$user['cleared'] = 2;
		wporg_update_pending_user( $user );

		wporg_login_send_confirmation_email( $user['user_email'] );
	}

	if ( isset( $_GET['ajax'] ) ) {
		die( wporg_login_admin_action_text( 'approved' ) );
	}

	wp_safe_redirect( add_query_arg(
		's',
		urlencode( $email ),
		'https://login.wordpress.org/wp-admin/index.php?page=user-registrations&action=approved'
	) );
	exit;
} );

add_action( 'admin_post_login_block', function() { 
	if ( ! current_user_can( 'promote_users' ) ) {
		wp_die();
	}

	$email = $_REQUEST['email'] ?? '';

	check_admin_referer( 'block_' . $email );

	$user = wporg_get_pending_user( $email );
	if ( $user ) {
		$user['cleared']             = 0;
		$user['user_activation_key'] = '';
		$user['user_profile_key']    = '';

		wporg_update_pending_user( $user );
	}

	if ( isset( $_GET['ajax'] ) ) {
		die( wporg_login_admin_action_text( 'blocked' ) );
	}

	wp_safe_redirect( add_query_arg(
		's',
		urlencode( $email ),
		'https://login.wordpress.org/wp-admin/index.php?page=user-registrations&action=blocked'
	) );
	exit;
} );

add_action( 'admin_post_login_delete', function() { 
	if ( ! current_user_can( 'promote_users' ) ) {
		wp_die();
	}

	$email = $_REQUEST['email'] ?? '';

	check_admin_referer( 'delete_' . $email );

	$user = wporg_get_pending_user( $email );
	if ( $user ) {
		wporg_delete_pending_user( $user );
	}

	if ( isset( $_GET['ajax'] ) ) {
		die( wporg_login_admin_action_text( 'deleted' ) );
	}

	wp_safe_redirect( add_query_arg(
		's',
		urlencode( $email ),
		'https://login.wordpress.org/wp-admin/index.php?page=user-registrations&action=deleted'
	) );
	exit;
} );

add_action( 'admin_post_login_block_account', function() { 
	if ( ! current_user_can( 'promote_users' ) ) {
		wp_die();
	}

	if ( empty( $_REQUEST['user_id'] ) ) {
		die();
	}

	$user_id = (int) $_REQUEST['user_id'];

	check_admin_referer( 'block_account_' . $user_id );

	if ( $user_id && defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {

		// Switch first so that bbPress loads with the correct context.
		// This also ensures that the bbp_participant code doesn't kick in.
		switch_to_blog( WPORG_SUPPORT_FORUMS_BLOGID );

		// Load the support forums.. 
		include_once WP_PLUGIN_DIR . '/bbpress/bbpress.php';
		include_once WP_PLUGIN_DIR . '/support-forums/support-forums.php';

		// Set the user to blocked. Support forum hooks will take care of the rest.
		bbp_set_user_role( $user_id, bbp_get_blocked_role() );

		restore_current_blog();
	}

	if ( isset( $_GET['ajax'] ) ) {
		die( wporg_login_admin_action_text( 'blocked_account' ) );
	}

	wp_safe_redirect( add_query_arg(
		's',
		urlencode( $email ),
		'https://login.wordpress.org/wp-admin/index.php?page=user-registrations&action=blocked_account'
	) );
	exit;
} );

