<?php

include __DIR__  . '/class-user-registrations-list-table.php';

add_action( 'admin_init', function() {
	global $wpdb;
	if ( 'local' !== wp_get_environment_type() ) {
		return;
	}

	require ABSPATH . 'wp-admin/includes/upgrade.php';

	// Check to see if the table exists.
	$table_sql = "CREATE TABLE `{$wpdb->base_prefix}user_pending_registrations` (
		`pending_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		`user_login` varchar(60) NOT NULL DEFAULT '',
		`user_email` varchar(100) NOT NULL DEFAULT '',
		`user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		`user_activation_key` varchar(60) DEFAULT NULL,
		`user_profile_key` varchar(60) DEFAULT NULL,
		`scores` text DEFAULT NULL,
		`meta` text DEFAULT NULL,
		`cleared` tinyint(1) unsigned NOT NULL DEFAULT 1,
		`created` tinyint(1) unsigned NOT NULL DEFAULT 0,
		`created_date` datetime NOT NULL,
		PRIMARY KEY (`pending_id`),
		UNIQUE KEY `user_login` (`user_login`),
		UNIQUE KEY `user_email` (`user_email`)
	) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=latin1;";

	dbDelta( $table_sql );
} );

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

	add_submenu_page(
		'user-registrations',
		'Settings',
		'Settings',
		'promote_users',
		'user-registration-settings',
		'wporg_login_admin_settings_page'
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
			if ( url.indexOf( 'block_account' ) !== -1 ) {
				if ( ! $('#block_reason').val() ) {
					$('#block_reason').val( prompt( 'Reason for blocking?' ) );
				}
				url += '&block_reason=' + encodeURIComponent( $('#block_reason').val() );
			}

			$.get( url, function( data ) {
				$tr.find('td:last').text( data );
			} );
		});
	} );
	</script>
	<style>
		table.wp-list-table td > a,
		table.wp-list-table td.column-meta > div > a {
			color: inherit;
		}
		table.wp-list-table td > a:hover,
		table.wp-list-table td.column-meta > div > a:hover {
			text-decoration: underline;
		}
		table.wp-list-table .delete-red {
			color: #b32d2e;
		}
		td.column-meta > div {
			max-height: 200px;
			overflow: scroll;
		}
		.wp-list-table.toplevel_page_user-registrations > tbody > tr.created {
			background-color: rgba(74, 202, 12, 0.06);
		}
		.wp-list-table.toplevel_page_user-registrations > tbody > tr.manually-approved {
			background-color: rgba(74, 202, 12, 0.12);
		}
		.wp-list-table.toplevel_page_user-registrations > tbody > tr.cleared {
			background-color: rgba(202, 181, 12, 0.06)
		}
		.wp-list-table.toplevel_page_user-registrations > tbody > tr.failed {
			background-color: rgba(202, 12, 12, 0.06)
		}
		.wp-list-table.toplevel_page_user-registrations > tbody > tr.blocked {
			background-color: rgba(202, 12, 12, 0.12)
		}
		.wp-list-table.toplevel_page_user-registrations > tbody td {
			border-bottom: 1px solid #cecece;
		}
		.wp-list-table.toplevel_page_user-registrations > tbody hr {
			opacity: 0.5;
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

function wporg_login_admin_settings_page() {
	if ( $_POST && check_admin_referer( 'update_login_settings' ) ) {
		$recaptcha_v3_threshold = wp_unslash( $_POST['recaptcha_v3_threshold'] ?? '' );
		if ( $recaptcha_v3_threshold ) {
			$recaptcha_v3_threshold = sprintf( "%.1f", $recaptcha_v3_threshold );
			update_option( 'recaptcha_v3_threshold', $recaptcha_v3_threshold );
		}

		$block_words = wp_unslash( $_POST['registration_block_words'] ?? '' );
		if ( $block_words ) {
			$block_words = str_replace( "\r", '', $block_words ); // We're not trimming the lines (So spaces before/after can be included to match full words only), but need to remove the 'arrrs.
			$block_words = explode( "\n", $block_words );
			$block_words = array_values( array_unique( array_filter( $block_words ) ) );

			// Sanity; Don't let it change more than 20%.
			if ( count( $block_words ) < count( get_option( 'registration_block_words' ) ) * 0.8 ) {
				wp_die( "Are you sure you wanted to do that? You attempted to change registration_block_words to less than 80% of the previous value." );
			}

			update_option( 'registration_block_words', $block_words );
		}

		$banned_email_domains = wp_unslash( $_POST['banned_email_domains'] ?? '' );
		if ( $banned_email_domains ) {
			$banned_email_domains = explode( "\n", $banned_email_domains );
			$banned_email_domains = array_values( array_unique( array_filter( array_map( 'trim', $banned_email_domains ) ) ) );

			// Sanity; Don't let it change more than 20%.
			if ( count( $banned_email_domains ) < count( get_site_option( 'banned_email_domains' ) ) * 0.8 ) {
				wp_die( "Are you sure you wanted to do that? You attempted to change banned_email_domains to less than 80% of the previous value." );
			}

			// Network-wide option.
			update_site_option( 'banned_email_domains', $banned_email_domains );
		}

		$ip_block = wp_unslash( $_POST['ip_block'] ?? '' );
		$ip_allow = wp_unslash( $_POST['ip_allow'] ?? '' );
		if ( $ip_block || $ip_allow ) {
			wp_cache_add_global_groups( array( 'registration-limit' ) );

			$expand_to_range = function( $ip ) {
				$ip  = trim( $ip );
				$ips = [ $ip ];
				if ( str_ends_with( $ip, '.*' ) ) {
					$ips = [];
					$ip  = substr( $ip, 0, -2 );
					foreach ( range( 0, 255 ) as $i ) {
						$ips[] = $ip . '.' . $i;
					}
				}

				return $ips;
			};

			if ( $ip_allow ) {
				$time_to_allow = wp_unslash( $_POST['ip_allow_time'] ?? DAY_IN_SECONDS );
				$ip_allow      = $expand_to_range( $ip_allow );
				foreach ( $ip_allow as $ip ) {
					wp_cache_set( $ip, 'whitelist', 'registration-limit', $time_to_allow );
				}

				printf( '<div class="notice notice-success"><p>%d IPs added to the allow list.</p></div>', count( $ip_allow ) );
			}
			if ( $ip_block ) {
				$time_to_block = wp_unslash( $_POST['ip_block_time'] ?? DAY_IN_SECONDS );
				$ip_block      = $expand_to_range( $ip_block );
				foreach ( $ip_block as $ip ) {
					wp_cache_set( $ip, 999, 'registration-limit', $time_to_block );
				}

				printf( '<div class="notice notice-success"><p>%d IPs blocked from registration.</p></div>', count( $ip_block ) );
			}
		}

		echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
	}

	echo '<div class="wrap">';
	echo '<h1 class="wp-heading-inline">Registration &amp; Login Settings</h1>';
	echo '<hr class="wp-header-end">';
	echo '<form method="POST">';
	wp_nonce_field( 'update_login_settings' );
	echo '<table class="form-table">';

	printf(
		'<tr>
			<th>reCaptcha v3 low-score threshold for Registration</th>
			<td>
				<input name="recaptcha_v3_threshold" type="number" min="0.0" max="1.0" step="0.1" name="" value="%s">
				<p><em>Any reCaptcha v3 score lower than this threshold is considered to have failed the reCaptcha and will be put into manual review.</em></p>
			</td>
		</tr>',
		esc_attr( get_option( 'recaptcha_v3_threshold', 0.2 ) )
	);

	printf(
		'<tr>
			<th>Block words for registration</th>
			<td>
				<textarea name="registration_block_words" rows="10" cols="80">%s</textarea>
				<p>
					<em>Any registrations with any of these phrases within their username, email address, or profile fields will be put into manual review.</em><br>
					<em>Multiple words allowed to form a phrase. Leading/trailing whitespace is not removed. One phrase per line.</em>
				</p>
			</td>
		</tr>',
		esc_textarea( implode( "\n", get_option( 'registration_block_words', [] ) ) )
	);

	printf(
		'<tr>
			<th>Banned Email Domains</th>
			<td>
				<textarea name="banned_email_domains" rows="10" cols="80">%s</textarea>
				<p id="banned-email-domains-desc"><em>These email domains are WordPress.org-wide. No emails will be sent to them. No users can set their email address to it.<br>One email domain per line. This is the same list as <a href="https://wordpress.org/wp-admin/network/settings.php#banned_email_domains">https://wordpress.org/wp-admin/network/settings.php#banned_email_domains</a>.</em></p>
			</td>
		</tr>',
		esc_textarea( implode( "\n", get_site_option( 'banned_email_domains', [] ) ) ),
	);

	echo '<tr>
		<th>IP Block</th>
		<td>
			<input class="regular-text" type="text" name="ip_block" minlength="7" maxlength="15" size="15" pattern="^((\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(\d{1,2}|1\d\d|2[0-4]\d|25[0-5]|[*])$" placeholder="xxx.xxx.xxx.xxx">
			<select name="ip_block_time">
				<option value="86400">24hrs</option>
				<option value="604800">7 days</option>
				<option value="2592000">30 days</option>
			</select>
			<p><em>Single IP, or range specified as <code>1.2.3.*</code>. IP will be blocked from registrations for the selected time period. </em></p>
		</td>
	</tr>';

	echo '<tr>
		<th>IP Allow</th>
		<td>
			<input class="regular-text" type="text" name="ip_allow" minlength="7" maxlength="15" size="15" pattern="^((\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(\d{1,2}|1\d\d|2[0-4]\d|25[0-5]|[*])$" placeholder="xxx.xxx.xxx.xxx">
			<select name="ip_allow_time">
				<option value="86400">24hrs</option>
				<option value="259200">3 days</option>
				<option value="604800">7 days</option>
			</select>
			<p><em>Single IP, or range specified as <code>1.2.3.*</code>. IP will bypass per-IP limits on registrations for the selected time period. Will also bypass Jetpack Protect login limiter.</em></p>
		</td>
	</tr>';

	echo '</table>';
	echo '<p class="submit">
		<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
	</p>';
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

	wporg_login_block_registration( $email );

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

function wporg_login_block_registration( $user ) {
	$user = wporg_get_pending_user( $user );
	if ( $user ) {
		$user['cleared']             = 0;
		$user['user_activation_key'] = '';
		$user['user_profile_key']    = '';

		wporg_update_pending_user( $user );

		return true;
	}

	return false;
}

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

	$user   = $_REQUEST['user'] ?? '';
	$reason = $_REQUEST['block_reason'] ?? '';
	if ( empty( $user ) ) {
		die();
	}

	$pending_user = wporg_get_pending_user( $user );
	if ( ! $user ) {
		die();
	}

	$user = get_user_by( 'slug', $pending_user['user_login'] );

	check_admin_referer( 'block_account_' . $user->ID );

	$result = wporg_login_block_account( $pending_user, $reason );
	if ( ! $result ) {
		die();
	}

	if ( isset( $_GET['ajax'] ) ) {
		die( wporg_login_admin_action_text( 'blocked_account' ) );
	}

	wp_safe_redirect( add_query_arg(
		's',
		urlencode( $user->user_email ),
		'https://login.wordpress.org/wp-admin/index.php?page=user-registrations&action=blocked_account'
	) );
	exit;
} );

function wporg_login_block_account( $user, $reason = '' ) {
	$pending_user = wporg_get_pending_user( $user );
	if ( ! $pending_user || ! $pending_user['created'] ) {
		return false;
	}

	$user = get_user_by( 'slug', $pending_user['user_login'] );
	if ( ! $user ) {
		return false;
	}

	$table = new User_Registrations_List_Table();

	ob_start();
	$pending_as_object       = (object) $pending_user;
	$pending_as_object->meta = (object) $pending_as_object->meta;
	$pending_as_object->user = $user;

	unset( $pending_as_object->meta->registration_ip, $pending_as_object->meta->confirmed_ip );

	$table->column_meta( $pending_as_object );
	$meta_column = ob_get_clean();
	$meta_column = wp_strip_all_tags( str_replace( '<br>', "\n", $meta_column ), false );

	if ( $user && defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {

		// Switch first so that bbPress loads with the correct context.
		// This also ensures that the bbp_participant code doesn't kick in.
		switch_to_blog( WPORG_SUPPORT_FORUMS_BLOGID );

		// Load the support forums.. 
		include_once WP_PLUGIN_DIR . '/bbpress/bbpress.php';
		include_once WP_PLUGIN_DIR . '/support-forums/support-forums.php';

		// bbPress roles still aren't quite right, need to switch away and back..
		// This is hacky, but otherwise the bbp_set_user_role() call below will appear to succeed, but no role alteration will actually happen.
		restore_current_blog();
		switch_to_blog( WPORG_SUPPORT_FORUMS_BLOGID );

		// Load the Support Forums, for logging and whatnot.
		WordPressdotorg\Forums\Plugin::get_instance();

		$callback = function( $text ) use ( $reason, $meta_column ) {
			return trim( "{$reason}\n{$meta_column}\n\n{$text}" );
		};
		add_filter( 'wporg_bbp_forum_role_changed_note_text', $callback );

		// Set the user to blocked. Support forum hooks will take care of the rest.
		bbp_set_user_role( $user->ID, bbp_get_blocked_role() );

		remove_filter( 'wporg_bbp_forum_role_changed_note_text', $callback );

		restore_current_blog();
	}

	return true;
}

add_action( 'load-toplevel_page_user-registrations', function() {
	// Perform bulk actions.
	$action = $_REQUEST['action'] ?? ( $_REQUEST['action2'] ?? '' );
	if (
		empty( $_REQUEST['pending_ids'] ) ||
		'reg_block' !== $action ||
		! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-toplevel_page_user-registrations' )
	) {
		return;
	}

	$reason = $_REQUEST['block_reason'] ?? '';
	foreach ( (array) $_REQUEST['pending_ids'] as $pending_id ) {
		$pending_user = wporg_get_pending_user( $pending_id );
		if ( ! $pending_user ) {
			continue;
		}

		if ( $pending_user['created'] ) {
			wporg_login_block_account( $pending_user, $reason );
		} else {
			wporg_login_block_registration( $pending_user );
		}
	}

	$url = remove_query_arg( array( 'pending_ids', 'action', 'action2', '_wpnonce', '_wp_http_referer' ) );
	$url = add_query_arg( 'action', 'blocked_account', $url );
	wp_safe_redirect( $url );
	exit;
} );