<?php
/**
 * Plugin Name:        WordCamp.org Login Message
 * Plugin Description: Displays a "Login with your WordPress.org account." in wp-login.php
 */

add_action( 'login_head',         'wcorg_login_css' );
add_action( 'admin_print_styles', 'wcorg_login_css' );
function wcorg_login_css() {
	?>

	<style type="text/css">
		#wp-auth-check-wrap #wp-auth-check {
			width: 500px !important;
		}

		body.login #login {
			width: 420px;
		}

			div.message p {
				margin-bottom: 1em;
			}

			#not-your-personal-site {
				font-style: italic;
			}

			.login form,
			.interim-login.login form {
				width: 272px;   /* 320px minus 24px padding on each side */
				margin-left: auto;
				margin-right: auto;
			}
	</style>

	<?php
}

/**
 * Override the default login message.
 *
 * We share user tables with WordPress.org, so users need to know to use that account.
 *
 * @param string        $message
 * @param string | bool $redirect_to
 *
 * @return string
 */
function wcorg_login_message( $message, $redirect_to = false ) {
	$locale           = get_locale();
	$registration_url = wcorg_get_wporg_forums_url( $locale, 'register' );

	if ( ! $redirect_to && ! empty( $_REQUEST['redirect_to'] ) ) {
		$redirect_to = $_REQUEST['redirect_to'];
	}

	if ( $redirect_to && wp_validate_redirect( $redirect_to ) != $redirect_to ) {
		$redirect_to = false;
	}

	/*
	 * $redirect_to gets urlencode()'d once by wp_login_url() and then all of $login_url gets encoded directly,
	 * because $registration_url will end up with two redirect_to parameters nested inside it.
	 * e.g., https://wordpress.org/support/register.php?redirect_to=https%3A%2F%2Fwordcamp.org%2Fwp-login.php%3Fredirect_to%3Dhttp%253A%252F%252Ftesting.wordcamp.org%252Ftickets%252F
	 *
	 * The second redirect_to parameter is intentionally double-encoded so that it ends up single-encoded after the
	 * redirection back to wp-login.php.
	 */
	$login_url        = urlencode( wp_login_url( $redirect_to ) );
	$registration_url = add_query_arg( 'redirect_to', $login_url, $registration_url );

	ob_start();
	?>

	<div id="wcorg-login-message" class="message">
		<p><?php echo __( 'Please use your <strong>WordPress.org</strong>* account to log in.', 'wordcamporg' ); ?></p> <?php // todo make this a constant and use it in wordcamp-forms-to-drafts and camptix-extras, since any changes to it will break them unless they're also updated ?>

		<p>
			<?php printf(
				__( 'If you don\'t have an account, <a href="%s">please create one</a>.', 'wordcamporg' ),
				esc_url( $registration_url )
			); ?>
		</p>

		<p id="not-your-personal-site">
			<?php printf( __( '* This is your account for <a href="%s">the official WordPress.org website</a>, rather than your personal WordPress site.', 'wordcamporg' ), wcorg_get_wporg_forums_url( $locale ) ); ?>
		</p>
	</div>

	<?php
	$message = ob_get_clean();
	return $message;
}
add_filter( 'login_message', 'wcorg_login_message' );

/**
 * Determine the correct WordPress.org forums URL for the given locale
 *
 * @todo Add more sites as they're created
 *
 * @param string $locale
 * @param string $path 'root' | 'register'
 *
 * @return string
 */
function wcorg_get_wporg_forums_url( $locale, $path = 'root' ) {
	switch ( $locale ) {
		case 'bg_BG':
		case 'bs_BA':
		case 'ca':
		case 'es_AR':
		case 'es_CL':
		case 'es_CO':
		case 'es_MX':
		case 'es_PE':
		case 'es_PR':
		case 'es_ES':
		case 'es_VE':
		case 'fi':
		case 'he_IL':
		case 'hr':
		case 'id_ID':
		case 'ja':
		case 'mk_MK':
		case 'pl_PL':
		case 'pt_PT':
		case 'ru_RU':
		case 'sr_RS':
		case 'th':
		case 'ug_CN':
			$url = sprintf( 'http://%s.forums.wordpress.org/', substr( $locale, 0, 2 ) );
			break;

		case 'pt_BR':
			$url = 'http://br.forums.wordpress.org/';
			break;

		case 'my_MM':
			$url = 'http://mya.forums.wordpress.org/';
			break;

		case 'en_US':
		default:
			$url = 'https://wordpress.org/';
			break;
	}

	if ( 'register' == $path ) {
		if ( 'https://wordpress.org/' == $url ) {
			$url .= 'support/register.php';
		} else {
			$url .= 'register.php';
		}
	}

	return $url;
}
