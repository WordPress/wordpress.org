<?php
/**
 * Plugin Name: JobsWP reCaptcha
 * Version:     1.1
 * Plugin URI:  http://jobs.wordpress.net
 * Author:      Scott Reilly
 * Description: Adds reCaptcha field to job posting form for jobs.wordpress.net.
 *
 * Site must define JOBSWP_RECAPTCHA_SITE_KEY and JOBSWP_RECAPTCHA_SECRET_KEY as
 * obtained from Google reCaptcha.
 */

defined( 'ABSPATH' ) or die();

class Jobs_Dot_WP_Captcha {

	/**
	 * Initializes plugin.
	 */
	public static function init() {
		add_filter( 'jobswb_save_job_errors',   array( __CLASS__, 'check_captcha' ) );
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
	 * Performs setup reCaptcha use, namely enqueuing JS.
	 *
	 * @access protected
	 */
	protected static function setup() {
		wp_enqueue_script( 'recaptcha-api', 'https://www.google.com/recaptcha/api.js', array(), '1' );
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
			self::setup();
			echo '<div class="g-recaptcha" data-sitekey="' . self::get_site_key() . '"></div>' . "\n";
		}
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
			if ( empty( $_POST['g-recaptcha-response'] ) ) {
				$errors = __( 'The captcha needs to be provided.', 'jobswp' );
			} else {
				self::setup();

				$verify = array(
					'secret'    => self::get_secret_key(),
					'remoteip'	=> $_SERVER['REMOTE_ADDR'], 
					'response'  => $_POST['g-recaptcha-response'],
				);

				$response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array( 'body' => $verify ) );

				if ( ! is_wp_error( $response ) && 200 == wp_remote_retrieve_response_code( $response ) ) {
					$result = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( ! $result['success'] ) {
						$errors = __( 'The captcha was incorrect.', 'jobswp' );
					}
				} else {
					$errors = __( 'Unable to verify captcha at this time. Try again in a few minutes.', 'jobswp' );
				}
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
