<?php
/**
 * Site must define JOBSWP_RECAPTCHA_SITE_KEY and JOBSWP_RECAPTCHA_SECRET_KEY as
 * obtained from Google reCaptcha.
 */

defined( 'ABSPATH' ) or die();

class Jobs_Dot_WP_Captcha {

	/**
	 * Initializes plugin.
	 */
	public static function init() {
		add_filter( 'jobswp_save_job_errors',   array( __CLASS__, 'check_captcha' ) );
		add_filter( 'jobswp_remove_job_errors', array( __CLASS__, 'check_captcha' ) );
		add_action( 'jobswp_post_job_form',     array( __CLASS__, 'recaptcha_field' ) );
		add_action( 'jobswp_remove_job_form',   array( __CLASS__, 'recaptcha_field' ) );
		add_action( 'jobswp_notice',            array( __CLASS__, 'add_notice' ) );
	}

	/**
	 * Gets site key for use with reCaptcha API.
	 *
	 * @return string
	 */
	public static function get_site_key() {
		return defined( 'JOBSWP_RECAPTCHA_SITE_KEY' ) ? JOBSWP_RECAPTCHA_SITE_KEY : '';
	}

	/**
	 * Gets secret key for use with reCaptcha API.
	 *
	 * @return string
	 */
	public static function get_secret_key() {
		return defined( 'JOBSWP_RECAPTCHA_SECRET_KEY' ) ? JOBSWP_RECAPTCHA_SECRET_KEY : '';
	}

	/**
	 * Performs setup for reCaptcha, namely enqueuing JS.
	 *
	 * @access protected
	 */
	protected static function setup() {
		wp_enqueue_script( 'recaptcha-api', 'https://www.google.com/recaptcha/api.js', array(), '1' );
	}

	/**
	 * Determines if reCaptcha is configured.
	 *
	 * Captcha is configured if the site key and secret key are defined.
	 *
	 * @access protected
	 *
	 * @return bool True if reCaptcha is configured, false otherwise.
	 */
	protected static function is_configured() {
		return self::get_site_key() && self::get_secret_key();
	}

	/**
	 * Determines if reCaptcha is required.
	 *
	 * @access protected
	 *
	 * @return bool True if reCaptcha is required, false otherwise.
	 */
	protected static function is_required() {
		/**
		 * Filters whether reCaptcha is required for form submissions that would otherwise require it.
		 *
		 * @param bool True if reCaptcha is required, false otherwise. Default is true if the environment is production.
		 */
		return (bool) apply_filters( 'jobswp_require_captcha', wp_get_environment_type() === 'production' );
	}

	/**
	 * Determines if the current page load is for the job submission verification
	 * step.
	 *
	 * @access protected
	 *
	 * @return bool
	 */
	protected static function is_verification_step() {
		return ( isset( $_POST['verify'] ) && 1 == $_POST['verify'] );
	}

	/**
	 * Determines if the current page load is for the remove job form.
	 *
	 * @access protected
	 *
	 * @return bool
	 */
	protected static function is_remove_job() {
		return is_page( 'remove-a-job' );
	}

	/**
	 * Determines if the captcha should be shown.
	 *
	 * @access protected
	 *
	 * @return bool
	 */
	protected static function do_captcha() {
		return ( self::is_verification_step() || self::is_remove_job() );
	}

	/**
	 * Outputs the captcha field if appropriate.
	 */
	public static function recaptcha_field() {
		// Only inject the captcha field on the verification page.
		if ( self::do_captcha() ) {
			self::output_recaptcha_field();
		}
	}

	/**
	 * Outputs reCAPTCHA field unless it is not required.
	 */
	public static function output_recaptcha_field() {
		if ( ! self::is_required() ) {
			return;
		}
		self::setup();
		echo '<div class="g-recaptcha" data-sitekey="' . esc_attr( self::get_site_key() ) . '"></div>' . "\n";
	}

	/**
	 * Verifies a submitted reCAPTCHA response with Google's API.
	 *
	 * If reCAPTCHA is not configured (missing keys), verification will fail.
	 *
	 * @return string|false Error message, or false if verification succeeded or was skipped.
	 */
	public static function verify_recaptcha_token() {
		if ( ! self::is_required() ) {
			return false;
		}

		if ( empty( $_POST['g-recaptcha-response'] ) ) {
			return __( 'The captcha needs to be provided.', 'jobswp' );
		}

		$verify = array(
			'secret'   => self::get_secret_key(),
			'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'response' => sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ),
		);

		$response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array( 'body' => $verify ) );

		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$result = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $result['success'] ) ) {
				return __( 'The captcha was incorrect.', 'jobswp' );
			}
			return false;
		}

		return __( 'Unable to verify captcha at this time. Try again in a few minutes.', 'jobswp' );
	}

	/**
	 * Verifies a proper captcha submission if appropriate.
	 *
	 * @param  string $errors Errors.
	 * @return string
	 */
	public static function check_captcha( $errors ) {
		// Only proceed if no error was already thrown.
		if ( self::do_captcha() && ! $errors && $_POST ) {
			$captcha_error = self::verify_recaptcha_token();
			if ( $captcha_error ) {
				$errors = $captcha_error;
			}
		}

		return $errors;
	}

	/**
	 * Amends notice on job verfication page to point out the captcha.
	 *
	 * @param string $type The type of notice being displayed.
	 */
	public static function add_notice( $type ) {
		if ( 'verify' == $type ) {
			echo ' <strong><em>' . __( 'Note that you must also fill out the captcha field.', 'jobswp' ) . '</em></strong>';
		}
	}
}

Jobs_Dot_WP_Captcha::init();
