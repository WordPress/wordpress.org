<?php
namespace WordPressdotorg\Plugin_Directory\Email;

abstract class HTML_Base extends Base {

	/**
	 * The HTML content for the email template
	 */
	abstract public function html();

	/**
	 * The plain-text content for the email template
	 *
	 * This might be offered by the Email template, but if not, we'll convert the HTML.
	 *
	 * @return string
	 */
	public function body() {
		$html = $this->html();

		// Perform some basic HTML conversions
		$html = str_replace( '<br>', "\n", $html );
		$html = str_replace( '<p>', "\n", $html );

		// Use some markdown style syntax.
		$html = preg_replace( '!<a[^>]*href=(.).+\\1[^>]*>([^<>]*)</a>!', '[$2]($1)', $html );
		$html = str_replace( '<li>', "\n * ", $html );
		$html = preg_replace_callback( '!<h(?P<level>[0-9])>(?P<text>[^<>]*)</h\\1>!', function( $m ) {
			return str_repeat( '#', $m['level'] ) . ' ' . $m['text'];
		}, $html );
		$html = str_replace( array( '<strong>', '</strong>', '<b>', '</b>' ), ' ** ', $html );
		$html = str_replace( array( '<em>', '</em>', '<i>', '</i>' ), ' __ ', $html );

		// Remove anything else left.
		$html = wp_strip_all_tags( $html );

		return $html;
	}

	/**
	 * Send an individual email.
	 */
	protected function _send() {
		global $phpmailer;
		if ( ! $this->should_send() ) {
			return false;
		}

		$subject = sprintf(
			/* translators: Email subject prefix. 1: The email subject. */
			__( '[WordPress Plugin Directory] %1$s', 'wporg-plugins' ),
			$this->subject()
		);

		$email = $this->user->user_email;
		$text  = $this->body();
		$text .= "\n\n" . $this->get_email_signature();
		$html  = $this->html();
		$html .= "<p>" . $this->get_email_signature( 'html' ) . "</p>";

		// Configure the mailer to send HTML, the PHPMailer is likely not yet setup, so we wait for the init action.
		$configure_multipart = function( $phpmailer ) use( $text ) {
			$phpmailer->AltBody = $text;
			$phpmailer->IsHTML( true );
		};
		add_action( 'phpmailer_init', $configure_multipart );

		$result = wp_mail(
			$email,
			$subject,
			$html,
			sprintf(
				'From: "%s" <%s>',
				'WordPress Plugin Directory',
				PLUGIN_TEAM_EMAIL
			)
		);

		// Reset the mailer.
		remove_action( 'phpmailer_init', $configure_multipart );
		$phpmailer->AltBody = '';
		$phpmailer->IsHTML( false );

		return $result;
	}

	/**
	 * A common email signature to attach to the bottom of all emails.
	 *
	 * @param string $mode 'text' or 'html'
	 * @return string
	 */
	public function get_email_signature( $mode = 'text' ) {
		$template = parent::get_email_signature();

		// Make it a bit more HTML friendly.
		if ( 'html' === $mode ) {
			$template = str_replace( "--\n", '<hr>', $template );
			$template = nl2br( $template );
			$template = make_clickable( $template );
		}

		return $template;
	}
}