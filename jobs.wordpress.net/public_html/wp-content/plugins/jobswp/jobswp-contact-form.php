<?php
/**
 * Front-end feedback / contact form (shortcode-based).
 *
 * @package JobsWP
 */

defined( 'ABSPATH' ) or die();

/**
 * Handles submission and rendering of the jobs site contact form.
 */
class Jobs_Dot_WP_Contact_Form {

	/**
	 * Nonce action key.
	 * @var string
	 */
	const NONCE_ACTION = 'jobswp_contact_form';

	/**
	 * Transient prefix.
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'jobswp_cf_';

	/**
	 * Maximum number of submissions per rate limit window.
	 * @var int
	 */
	const RATE_LIMIT_MAX = 2;

	/**
	 * Rate limit window size in seconds.
	 * @var int
	 */
	const RATE_LIMIT_WINDOW = 600;

	/**
	 * Initializes plugin.
	 */
	public static function init() {
		add_shortcode( 'jobswp-contact-form', [ self::class, 'shortcode' ] );
		add_action( 'init', [ self::class, 'maybe_handle_submission' ], 20 );
		add_action( 'jobswp_contact_form_fields', [ Jobs_Dot_WP_Captcha::class, 'output_recaptcha_field' ] );
	}

	/**
	 * Processes POST submissions before output is sent.
	 */
	public static function maybe_handle_submission() {
		if ( ! isset( $_POST['jobswp_contact_submit'] ) ) {
			return;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		$page_id = isset( $_POST['jobswp_contact_page_id'] ) ? absint( $_POST['jobswp_contact_page_id'] ) : 0;
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( ! $page || 'publish' !== $page->post_status || ! self::post_has_contact_shortcode( $page ) ) {
			return;
		}

		$permalink = get_permalink( $page_id );
		$fields    = self::get_submitted_field_values();

		// Honeypot (expected to be empty).
		if ( ! empty( $_POST['jobswp_contact_website'] ) ) {
			wp_safe_redirect( $permalink );
			exit;
		}

		if ( self::is_rate_limited() ) {
			self::redirect_with_flash(
				$permalink,
				__( 'Too many messages have been sent from your address recently. Please wait a few minutes and try again.', 'jobswp' ),
				$fields
			);
		}

		$captcha_error = Jobs_Dot_WP_Captcha::verify_recaptcha_token();
		if ( $captcha_error ) {
			self::redirect_with_flash( $permalink, $captcha_error, $fields );
		}

		$name    = $fields['name'];
		$email   = $fields['email'];
		$subject = $fields['subject'];
		$message = $fields['message'];

		$error = self::validate( $name, $email, $subject, $message );
		if ( $error ) {
			self::redirect_with_flash( $permalink, $error, $fields );
		}

		$mail_error = self::send_mail( $name, $email, $subject, $message );
		if ( $mail_error ) {
			self::redirect_with_flash( $permalink, $mail_error, $fields );
		}

		self::bump_rate_limit();

		wp_safe_redirect( add_query_arg( 'jobswp_contact', 'sent', $permalink ) );
		exit;
	}

	/**
	 * Checks if the post has a contact form shortcode.
	 *
	 * @param WP_Post $post Post object.
	 * @return bool True if the post has a contact form shortcode, false otherwise.
	 */
	protected static function post_has_contact_shortcode( $post ) {
		return is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'jobswp-contact-form' );
	}

	/**
	 * Checks if the rate limit has been exceeded.
	 *
	 * @return bool True if the rate limit has been exceeded, false otherwise.
	 */
	protected static function is_rate_limited() {
		$key   = self::rate_limit_key();
		$count = (int) get_transient( $key );

		return $count >= self::RATE_LIMIT_MAX;
	}

	/**
	 * Bumps the rate limit.
	 */
	protected static function bump_rate_limit() {
		$key   = self::rate_limit_key();
		$count = (int) get_transient( $key );
		$count++;
		set_transient( $key, $count, self::RATE_LIMIT_WINDOW );
	}

	/**
	 * Generates a rate limit key.
	 *
	 * @return string Rate limit key.
	 */
	protected static function rate_limit_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';

		return self::TRANSIENT_PREFIX . 'rl_' . md5( $ip );
	}

	/**
	 * Gets the sanitized submitted field values.
	 *
	 * @return array{name: string, email: string, subject: string, message: string} Submitted field values.
	 */
	protected static function get_submitted_field_values() {
		return [
			'name'    => isset( $_POST['jobswp_contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['jobswp_contact_name'] ) ) : '',
			'email'   => isset( $_POST['jobswp_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['jobswp_contact_email'] ) ) : '',
			'subject' => isset( $_POST['jobswp_contact_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['jobswp_contact_subject'] ) ) : '',
			'message' => isset( $_POST['jobswp_contact_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['jobswp_contact_message'] ) ) : '',
		];
	}

	/**
	 * Redirects with a flash message.
	 *
	 * @param string $redirect_url Full URL to the contact page.
	 * @param string $error_message Error message for the user.
	 * @param array  $fields Field snapshot for repopulating the form.
	 */
	protected static function redirect_with_flash( $redirect_url, $error_message, $fields ) {
		$token = wp_generate_password( 32, false, false );
		set_transient(
			self::TRANSIENT_PREFIX . $token,
			[
				'error'  => $error_message,
				'fields' => $fields,
			],
			5 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect( add_query_arg( 'jobswp_cf', rawurlencode( $token ), $redirect_url ) );
		exit;
	}

	/**
	 * Gets the flash payload.
	 *
	 * @return array{error?: string, fields?: array}|null
	 */
	protected static function get_flash_payload() {
		if ( ! isset( $_GET['jobswp_cf'] ) || ! is_string( $_GET['jobswp_cf'] ) ) {
			return null;
		}

		$token = preg_replace( '/[^a-zA-Z0-9]/', '', wp_unslash( $_GET['jobswp_cf'] ) );
		if ( ! $token ) {
			return null;
		}

		$data = get_transient( self::TRANSIENT_PREFIX . $token );
		delete_transient( self::TRANSIENT_PREFIX . $token );

		if ( ! is_array( $data ) ) {
			return null;
		}

		return $data;
	}

	/**
	 * Validates the submitted field values.
	 *
	 * @param string $name    Sanitized name.
	 * @param string $email   Sanitized email.
	 * @param string $subject Sanitized subject.
	 * @param string $message Sanitized message.
	 * @return string|false Error message or false if valid.
	 */
	protected static function validate( $name, $email, $subject, $message ) {
		if ( '' === $name ) {
			return __( 'Please enter your name.', 'jobswp' );
		}
		if ( '' === $email || ! is_email( $email ) ) {
			return __( 'Please enter a valid email address.', 'jobswp' );
		}
		if ( '' === $subject ) {
			return __( 'Please enter a subject.', 'jobswp' );
		}
		if ( strlen( $subject ) > 200 ) {
			return __( 'The subject is too long.', 'jobswp' );
		}
		if ( '' === $message ) {
			return __( 'Please enter a message.', 'jobswp' );
		}
		if ( strlen( $message ) > 15000 ) {
			return __( 'The message is too long.', 'jobswp' );
		}

		return false;
	}

	/**
	 * Sends the email.
	 *
	 * @param string $name    Sender name.
	 * @param string $email   Sender email.
	 * @param string $subject Message subject line from user.
	 * @param string $message Message body.
	 * @return string|false Error message on failure, false on success.
	 */
	protected static function send_mail( $name, $email, $subject, $message ) {
		/**
		 * Filters the recipient address for feedback form submissions.
		 *
		 * @param string $email Default administrator email.
		 */
		$to = apply_filters( 'jobswp_contact_form_recipient', get_option( 'admin_email' ) );

		if ( ! is_email( $to ) ) {
			return __( 'The site is not configured to accept feedback at this time.', 'jobswp' );
		}

		$mail_subject = sprintf(
			/* translators: 1: Site name, 2: User-supplied subject */
			__( '[%1$s Feedback] %2$s', 'jobswp' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$subject
		);

		$footer = sprintf(
			__( "Sent from (IP address): %1\$s\nDate/Time: %2\$s\nReferer: %3\$s\nUser Agent: %4\$s\n", 'jobswp' ),
			isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ) ),
			isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''
		);

		$body = sprintf(
			/* translators: 1: Sender name, 2: Sender email, 3: Message body, 4: Footer */
			__( "Name: %1\$s\nEmail: %2\$s\n\nMessage:\n%3\$s\n\n\n\n%4\$s", 'jobswp' ),
			$name,
			$email,
			$message,
			$footer
		);

		$headers = [
			'From: jobs.wordpress.net <jobs@wordpress.net>',
			'Reply-To: ' . $email,
			'Content-Type: text/plain; charset=UTF-8',
		];

		$sent = wp_mail( $to, $mail_subject, $body, $headers );

		if ( ! $sent ) {
			return __( 'The message could not be sent. Please try again later.', 'jobswp' );
		}

		return false;
	}

	/**
	 * Shortcode callback.
	 *
	 * @return string HTML output for the contact form.
	 */
	public static function shortcode() {
		if ( ! is_singular() ) {
			return '';
		}

		global $post;
		if ( ! $post || ! self::post_has_contact_shortcode( $post ) ) {
			return '';
		}

		$page_id = (int) $post->ID;

		$contact_status = isset( $_GET['jobswp_contact'] ) ? sanitize_key( wp_unslash( $_GET['jobswp_contact'] ) ) : '';
		if ( 'sent' === $contact_status ) {
			return '<div class="notice notice-success"><p>' . esc_html__( 'Thank you. Your message has been sent.', 'jobswp' ) . '</p></div>';
		}

		$flash  = self::get_flash_payload();
		$fields = isset( $flash['fields'] ) && is_array( $flash['fields'] ) ? $flash['fields'] : [];
		$error  = isset( $flash['error'] ) ? (string) $flash['error'] : '';

		$name    = isset( $fields['name'] ) ? $fields['name'] : '';
		$email   = isset( $fields['email'] ) ? $fields['email'] : '';
		$subject = isset( $fields['subject'] ) ? $fields['subject'] : '';
		$message = isset( $fields['message'] ) ? $fields['message'] : '';

		ob_start();

		if ( $error ) {
			echo '<div class="notice notice-error"><p>';
			echo esc_html( sprintf( __( 'Error: %s', 'jobswp' ), $error ) );
			echo '</p></div>';
		}

		?>
		<div class="jobswp-contact-form">
			<p class="items-required">* <?php esc_html_e( 'indicates required field', 'jobswp' ); ?></p>
			<form class="jobswp-contact-form__form" method="post" action="<?php echo esc_url( get_permalink( $page_id ) ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="jobswp_contact_page_id" value="<?php echo esc_attr( (string) $page_id ); ?>" />

				<p class="jobswp-contact-form__field jobswp-contact-form__field--honeypot" aria-hidden="true">
					<label for="jobswp_contact_website"><?php esc_html_e( 'Website', 'jobswp' ); ?></label>
					<input type="text" name="jobswp_contact_website" id="jobswp_contact_website" value="" tabindex="-1" autocomplete="off" />
				</p>

				<p class="jobswp-contact-form__field">
					<label for="jobswp_contact_name"><?php esc_html_e( 'Name', 'jobswp' ); ?> <span class="required">*</span></label>
					<input type="text" name="jobswp_contact_name" id="jobswp_contact_name" class="input" value="<?php echo esc_attr( $name ); ?>" maxlength="200" required />
				</p>

				<p class="jobswp-contact-form__field">
					<label for="jobswp_contact_email"><?php esc_html_e( 'Email', 'jobswp' ); ?> <span class="required">*</span></label>
					<input type="email" name="jobswp_contact_email" id="jobswp_contact_email" class="input" value="<?php echo esc_attr( $email ); ?>" maxlength="200" required />
				</p>

				<p class="jobswp-contact-form__field">
					<label for="jobswp_contact_subject"><?php esc_html_e( 'Subject', 'jobswp' ); ?> <span class="required">*</span></label>
					<input type="text" name="jobswp_contact_subject" id="jobswp_contact_subject" class="input" value="<?php echo esc_attr( $subject ); ?>" maxlength="200" required />
				</p>

				<p class="jobswp-contact-form__field">
					<label for="jobswp_contact_message"><?php esc_html_e( 'Message', 'jobswp' ); ?> <span class="required">*</span></label>
					<textarea name="jobswp_contact_message" id="jobswp_contact_message" class="input" rows="10" cols="60" maxlength="15000" required><?php echo esc_textarea( $message ); ?></textarea>
				</p>

				<?php do_action( 'jobswp_contact_form_fields' ); ?>

				<p class="jobswp-contact-form__submit">
					<input type="submit" name="jobswp_contact_submit" value="<?php esc_attr_e( 'Send message', 'jobswp' ); ?>" />
				</p>
			</form>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}

Jobs_Dot_WP_Contact_Form::init();
