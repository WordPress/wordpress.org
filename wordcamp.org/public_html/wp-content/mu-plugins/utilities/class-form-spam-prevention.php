<?php

namespace WordCamp\Utilities;
defined( 'WPINC' ) || die();

/**
 * Class Form_Spam_Prevention
 *
 * A tool for preventing spam/bot submissions to public forms.
 *
 * Usage:
 * - Instantiate the class to a variable. Change the default configuration by passing an array of option values
 *   during instantiation.
 * - Add spam prevention fields to a form with `render_form_fields()`.
 * - Add styles for the fields by adding `render_form_field_styles()` to an appropriate action hook.
 * - Call `validate_form_submission()` during normal form validation to test the spam prevention fields and determine
 *   a throttle score.
 * - Before rendering your form, you can check if an IP address is already throttled with `is_ip_address_throttled()`.
 *
 * @package WordCamp\Utilities
 */
class Form_Spam_Prevention {
	/**
	 * The score at which an IP address will get throttled.
	 *
	 * @var int
	 */
	const SCORE_THRESHOLD = 4;

	/**
	 * The number of seconds that a throttle will last.
	 *
	 * @var int
	 */
	const THROTTLE_DURATION = HOUR_IN_SECONDS;

	/**
	 * Configuration options for the class instance.
	 *
	 * @var array
	 */
	protected $config = array();

	/**
	 * Form_Spam_Prevention constructor.
	 *
	 * @param array $config {
	 *     Optional. Modify the default configuration values.
	 *
	 *     @type string $prefix            The prefix to use for form input name and id attributes.
	 *     @type string $honeypot_name     The name/id attribute of the honeypot field (without the prefix).
	 *     @type string $timestamp_name    The name/id attribute of the timestamp field (without the prefix).
	 *     @type bool   $individual_styles True to render style blocks next to individual fields instead of as a
	 *                                     separate, collective block. Default false.
	 * }
	 */
	public function __construct( array $config = [] ) {
		$defaults = [
			'prefix'            => 'fsp-',
			'honeypot_name'     => 'tos-required',
			'timestamp_name'    => 'dob-required',
			'individual_styles' => false,
		];

		$this->config = wp_parse_args( $config, $defaults );
	}

	/**
	 * Render styles for all spam prevention form fields in one block.
	 *
	 * This should be hooked to `wp_print_styles` or `wp_print_footer_scripts` if the `individual_styles` config
	 * is set to `false`.
	 *
	 * @return void
	 */
	public function render_form_field_styles() {
		if ( $this->config['individual_styles'] ) {
			return;
		}
		?>
		<style type="text/css">
			label[for="<?php echo esc_attr( $this->config['prefix'] . $this->config['honeypot_name'] ); ?>"],
			label[for="<?php echo esc_attr( $this->config['prefix'] . $this->config['timestamp_name'] ); ?>"] {
				display: none;
			}
		</style>
		<?php
	}

	/**
	 * Render HTML for all the spam prevention form fields.
	 *
	 * @return void
	 */
	public function render_form_fields() {
		$this->render_field_honeypot();
		$this->render_field_timestamp();
	}

	/**
	 * Validate all of the spam prevention form fields and determine if the submission is spam or not.
	 *
	 * @param int $input_type
	 *
	 * @return bool True if the submission "passes", and is not spam.
	 */
	public function validate_form_submission( $input_type = INPUT_POST ) {
		$tests = [
			'honeypot'  => $this->validate_field_honeypot( $input_type ),
			'timestamp' => $this->validate_field_timestamp( $input_type ),
		];

		$score = $this->add_score_to_ip_address( $tests );

		$pass = ! in_array( 'fail', $tests ) && $score < self::SCORE_THRESHOLD;

		/**
		 * Action: Fires after the spam prevention fields are validated.
		 *
		 * @param bool  $pass  True if the submission "passes", and is not spam.
		 * @param array $tests The results of the validations of the individual fields.
		 * @param float $score The current throttle score for the submitter's IP address.
		 */
		do_action( 'form_spam_prevention_validation', $pass, $tests, $score );

		return $pass;
	}

	/**
	 * Render HTML for the honeypot field.
	 *
	 * The honeypot is a checkbox field that is hidden visually and from screen readers, so humans won't see it, but
	 * bots might. If the box gets checked, the test fails.
	 *
	 * @return void
	 */
	public function render_field_honeypot() {
		$name = $this->config['prefix'] . $this->config['honeypot_name'];
		?>
			<label for="<?php echo esc_attr( $name ); ?>">
				<input
					type="checkbox"
					id="<?php echo esc_attr( $name ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					tabindex="-1"
					autocomplete="off"
				/>
				Yes
			</label>
			<?php if ( $this->config['individual_styles'] ) : ?>
				<style type="text/css">
					label[for="<?php echo esc_attr( $name ); ?>"] { display: none; }
				</style>
			<?php endif; ?>
		<?php
	}

	/**
	 * Validate the honeypot field.
	 *
	 * If a value is submitted for it, the test fails.
	 *
	 * @param int $input_type The type value to use with `filter_input`.
	 *
	 * @return string 'pass' or 'fail'.
	 */
	public function validate_field_honeypot( $input_type = INPUT_POST ) {
		$name  = $this->config['prefix'] . $this->config['honeypot_name'];
		$value = filter_input( $input_type, $name, FILTER_VALIDATE_BOOLEAN, [
			'flags' => FILTER_NULL_ON_FAILURE,
		] );

		$pass = is_null( $value ) || false === $value;

		return ( $pass ) ? 'pass' : 'fail';
	}

	/**
	 * Render HTML for the timestamp field.
	 *
	 * The timestamp field contains the Unix timestamp for the moment the form is rendered. It is hidden visually and
	 * from screen readers, so humans won't see it, but bots might. If the field value gets altered or the form is
	 * submitted too soon after it was rendered, the test fails.
	 *
	 * @return void
	 */
	public function render_field_timestamp() {
		$name = $this->config['prefix'] . $this->config['timestamp_name'];
		?>
		<label for="<?php echo esc_attr( $name ); ?>">
			<input
				type="text"
				id="<?php echo esc_attr( $name ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo strtotime( 'now' ); ?>"
				tabindex="-1"
				autocomplete="off"
			/>
			Date
		</label>
		<?php if ( $this->config['individual_styles'] ) : ?>
			<style type="text/css">
				label[for="<?php echo esc_attr( $name ); ?>"] { display: none; }
			</style>
		<?php endif; ?>
		<?php
	}

	/**
	 * Validate the timestamp field.
	 *
	 * If the value is not a Unix timestamp within a certain window of time, the test fails.
	 *
	 * @param int $input_type The type value to use with `filter_input`.
	 *
	 * @return string
	 */
	public function validate_field_timestamp( $input_type = INPUT_POST ) {
		$name  = $this->config['prefix'] . $this->config['timestamp_name'];
		$value = filter_input( $input_type, $name, FILTER_VALIDATE_INT, [
			'options' => [
				'min_range' => strtotime( '- 15 minutes' ),
				'max_range' => strtotime( '- 5 seconds' ),
			],
		] );

		$pass = is_int( $value ) && 0 !== $value;

		return ( $pass ) ? 'pass' : 'fail';
	}

	/**
	 * Analyze validation tests and assign a throttle score for a given IP address.
	 *
	 * The throttle score is cumulative, so if an IP address already has a score, additional points from the current
	 * validation tests will be added to the existing number. The throttle duration will also be reset each time the
	 * score is updated.
	 *
	 * To add/subtract an arbitrary amount to the score for a specific IP address, pass an array containing the desired
	 * numeric amount.
	 *
	 * @param array  $tests      Validation tests to analyze and derive a score from.
	 * @param string $ip_address An IP address.
	 *
	 * @return float|int
	 */
	public function add_score_to_ip_address( array $tests = [], $ip_address = '' ) {
		if ( ! $ip_address ) {
			$ip_address = $this->get_ip_address();
		}

		$score = $this->get_score_for_ip_address( $ip_address );

		if ( empty( $tests ) ) {
			$score += 1;
		} else {
			foreach ( $tests as $test ) {
				if ( is_float( $test ) || is_int( $test ) ) {
					$score += $test;
				} else {
					switch ( $test ) {
						case 'pass':
						default:
							$score += 0.5;
							break;
						case 'fail':
							$score += 2;
							break;
					}
				}
			}
		}

		set_transient( $this->generate_score_key( $ip_address ), $score, self::THROTTLE_DURATION );

		return $score;
	}

	/**
	 * Checks the current throttle score for a given IP address.
	 *
	 * @param string $ip_address An IP address.
	 *
	 * @return bool True if the score meets or exceeds the throttle threshold.
	 */
	public function is_ip_address_throttled( $ip_address = '' ) {
		if ( ! $ip_address ) {
			$ip_address = $this->get_ip_address();
		}

		$score = $this->get_score_for_ip_address( $ip_address );

		return $score >= self::SCORE_THRESHOLD;
	}

	/**
	 * A shortcut method for getting the user's IP address.
	 *
	 * This could be expanded in the future for more sophisticated IP address detection, since `$_SERVER['REMOTE_ADDR']`
	 * can be spoofed.
	 *
	 * @return string An IP address.
	 */
	protected function get_ip_address() {
		return $_SERVER['REMOTE_ADDR'];
	}

	/**
	 * Generate a pseudo-anonymous cache key for storing/retrieving the throttle score for an IP address.
	 *
	 * @param string $ip_address An IP address.
	 *
	 * @return string
	 */
	protected function generate_score_key( $ip_address ) {
		return 'form-spam-prevention-' . $this->config['prefix'] . md5( $ip_address );
	}

	/**
	 * Retrieve the throttle score for a given IP address.
	 *
	 * @param string $ip_address An IP address.
	 *
	 * @return float
	 */
	protected function get_score_for_ip_address( $ip_address = '' ) {
		if ( ! $ip_address ) {
			$ip_address = $this->get_ip_address();
		}

		$score = floatval( get_transient( $this->generate_score_key( $ip_address ) ) );

		return $score ?: (float) 0;
	}
}
