<?php
/**
 * Plugin Name: Add Existing Users via Username
 * Description: Allows non-super admins to add existing users via email address or username.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 *
 * @package WordPressdotorg\AddViaUsername
 */

namespace WordPressdotorg\AddViaUsername;

/**
 * Outputs inline scripts in the footer to replace labels and descriptions.
 */
function replace_email_label() {
	if ( is_super_admin() ) {
		return;
	}
	?>
	<script>
		( function( $ ) {
			$( 'label[for="adduser-email"]' ).html( '<?php echo esc_js( __( 'Email or Username' ) ); ?>' );
			$( '#adduser-email' ).attr( 'type', 'text' );
			$( '#add-new-user' ).find( '+ div + p' ).html( "<?php echo esc_js( __( 'Enter the email address or username of an existing user on this network to invite them to this site. That person will be sent an email asking them to confirm the invite.' ) ); ?>" );
		} )( jQuery );
	</script>
	<?php
}
add_action( 'admin_print_footer_scripts-user-new.php', __NAMESPACE__ . '\replace_email_label' );

/**
 * Replaces functionality at a single site level for adding an existing network user to a site.
 *
 * The majority of this code is from wp-admin/user-new.php.
 */
function add_existing_user_to_site() {
	if ( is_super_admin() ) {
		return;
	}

	check_admin_referer( 'add-user', '_wpnonce_add-user' );

	$user_details = null;
	$user_email   = wp_unslash( $_REQUEST['email'] );
	if ( false !== strpos( $user_email, '@' ) ) {
		$user_details = get_user_by( 'email', $user_email );
	} else {
		$user_details = get_user_by( 'login', $user_email );
	}

	if ( ! $user_details ) {
		wp_safe_redirect( add_query_arg( array( 'update' => 'does_not_exist' ), 'user-new.php' ) );
		exit;
	}

	if ( ! current_user_can( 'promote_user', $user_details->ID ) ) {
		wp_die( esc_html__( 'Cheatin&#8217; uh?' ), 403 );
	}

	// Adding an existing user to this blog.
	$blog_id        = get_current_blog_id();
	$new_user_email = $user_details->user_email;
	$username       = $user_details->user_login;
	$user_id        = $user_details->ID;

	if ( ( null !== $username && ! is_super_admin( $user_id ) ) && array_key_exists( $blog_id, get_blogs_of_user( $user_id ) ) ) {
		$redirect = add_query_arg( array( 'update' => 'addexisting' ), 'user-new.php' );
	} else {
		$new_user_key = substr( md5( $user_id ), 0, 5 );
		add_option( 'new_user_' . $new_user_key, array(
			'user_id' => $user_id,
			'email'   => $user_details->user_email,
			'role'    => $_REQUEST['role'],
		) );

		$roles = get_editable_roles();
		$role  = $roles[ $_REQUEST['role'] ];
		/* translators: 1: Site name, 2: site URL, 3: role, 4: activation URL */
		$message = wp_specialchars_decode( esc_html__(
			'Hi,

You\'ve been invited to join \'%1$s\' at
%2$s with the role of %3$s.

Please click the following link to confirm the invite:
%4$s'
		), ENT_QUOTES | ENT_HTML5 );

		wp_mail(
			$new_user_email, sprintf(
				/* translators: Blog name. */
				esc_html__( '[%s] Joining confirmation' ),
				wp_specialchars_decode( get_option( 'blogname' ) )
			),
			sprintf(
				$message,
				get_option( 'blogname' ),
				home_url(),
				wp_specialchars_decode( translate_user_role( $role['name'] ) ),
				home_url( "/newbloguser/$new_user_key/" )
			)
		);
		$redirect = add_query_arg( array( 'update' => 'add' ), 'user-new.php' );
	}

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_action_adduser', __NAMESPACE__ . '\add_existing_user_to_site' );
