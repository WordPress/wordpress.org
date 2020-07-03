<?php

include __DIR__  . '/class-user-registrations-list-table.php';

add_action( 'admin_menu', function() {
	add_submenu_page(
		'index.php',
		'Pending User Registrations', 'Pending User Registrations',
		'manage_users',
		'user-registrations',
		'wporg_login_admin_page'
	);
});

function wporg_login_admin_page() {
	$wp_list_table = new User_Registrations_List_Table();
	$wp_list_table->prepare_items();

	echo '<style>
		table.dashboard_page_user-registrations td > a {
			color: inherit;
		}
		table.dashboard_page_user-registrations td > a:hover {
			text-decoration: underline;
		}
	</style>';

	echo '<div class="wrap">';
	echo '<h1 class="wp-heading-inline">Pending User Registrations</h1>';
	echo '<hr class="wp-header-end">';

	if ( isset( $_REQUEST['resent-email'] ) ) {
		echo '<div class="updated notice"><p>The registration email has been resent.</p></div>';
	}

	echo '<form>';
	printf( '<input type="hidden" name="page" value="%s">', esc_attr( $_GET['page'] ) );

	//$wp_list_table->views();
	$wp_list_table->search_box( 'Search', 's' );
	$wp_list_table->display();

	echo '</form>';
	echo '</div>';
}

add_action( 'admin_post_login_resend_email', function() { 
	if ( ! current_user_can( 'manage_users' ) ) {
		wp_die();
	}

	$email = $_REQUEST['email'] ?? '';

	check_admin_referer( 'resend_' . $email );

	if ( $email ) {
		wporg_send_confirmation_email( $email );
	}

	wp_safe_redirect( add_query_arg(
		's',
		urlencode( $email ),
		'https://login.wordpress.org/wp-admin/index.php?page=user-registrations&resent-email=true'
	) );
	exit;
});