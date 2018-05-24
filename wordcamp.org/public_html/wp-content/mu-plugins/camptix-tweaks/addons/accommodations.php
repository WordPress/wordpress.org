<?php

namespace WordCamp\CampTix_Tweaks;
defined( 'WPINC' ) or die();

use CampTix_Plugin, CampTix_Addon;
use WP_Post;
use PHPMailer;

/**
 * Class Accommodations_Field.
 *
 * Add a non-optional attendee field indicating if they require special accommodations.
 *
 * Note that the user-facing wording has been changed to "accessibility needs" to avoid confusion for attendees and translators.
 *
 * @package WordCamp\CampTix_Tweaks
 */
class Accommodations_Field extends CampTix_Addon {
	const SLUG = 'accommodations';

	public $question = '';

	public $options = array();

	/**
	 * Hook into WordPress and Camptix.
	 */
	public function camptix_init() {
		$this->question = __( 'Do you have any accessibility needs, such as a sign language interpreter or wheelchair access, to participate in WordCamp?', 'wordcamporg' );

		$this->options = array(
			'yes' => _x( 'Yes (we will contact you)', 'ticket registration option', 'wordcamporg' ),
			'no'  => _x( 'No', 'ticket registration option', 'wordcamporg' ),
		);

		// Registration field
		add_action( 'camptix_attendee_form_after_questions', array( $this, 'render_registration_field' ), 12, 2 );
		add_filter( 'camptix_checkout_attendee_info', array( $this, 'validate_registration_field' ) );
		add_filter( 'camptix_form_register_complete_attendee_object', array( $this, 'populate_attendee_object' ), 10, 2 );
		add_action( 'camptix_checkout_update_post_meta', array( $this, 'save_registration_field' ), 10, 2 );
		add_action( 'camptix_ticket_emailed', array( $this, 'after_email_receipt' ) );

		// Edit info field
		add_filter( 'camptix_form_edit_attendee_ticket_info', array( $this, 'populate_ticket_info_array' ), 10, 2 );
		add_action( 'camptix_form_edit_attendee_update_post_meta', array( $this, 'validate_save_ticket_info_field' ), 10, 2 );
		add_action( 'camptix_form_edit_attendee_after_questions', array( $this, 'render_ticket_info_field' ), 12 );

		// Metabox
		add_filter( 'camptix_metabox_attendee_info_additional_rows', array( $this, 'add_metabox_row' ), 12, 2 );

		// Privacy
		add_filter( 'camptix_privacy_attendee_props_to_export', array( $this, 'attendee_props_to_export' ) );
		add_filter( 'camptix_privacy_export_attendee_prop', array( $this, 'export_attendee_prop' ), 10, 4 );
		add_filter( 'camptix_privacy_attendee_props_to_erase', array( $this, 'attendee_props_to_erase' ) );
		add_action( 'camptix_privacy_erase_attendee_prop', array( $this, 'erase_attendee_prop' ), 10, 3 );
	}

	/**
	 * Render the new field for the registration form during checkout.
	 *
	 * @param array $form_data
	 * @param int   $i
	 */
	public function render_registration_field( $form_data, $i ) {
		$current_data = ( isset( $form_data['tix_attendee_info'][ $i ] ) ) ?: array();

		$current_data = wp_parse_args( $current_data, array(
			self::SLUG => '',
		) );

		?>

		<tr class="tix-row-<?php echo esc_attr( self::SLUG ); ?>">
			<td class="tix-required tix-left">
				<?php echo esc_html( $this->question ); ?>
				<span class="tix-required-star">*</span>
			</td>

			<td class="tix-right">
				<label><input name="tix_attendee_info[<?php echo esc_attr( $i ); ?>][<?php echo esc_attr( self::SLUG ); ?>]" type="radio" value="yes" <?php checked( 'yes', $current_data[ self::SLUG ] ); ?> /> <?php esc_html( _ex( 'Yes (we will contact you)', 'ticket registration option', 'wordcamporg' ) ); ?></label>
				<br />
				<label><input name="tix_attendee_info[<?php echo esc_attr( $i ); ?>][<?php echo esc_attr( self::SLUG ); ?>]" type="radio" value="no" <?php checked( 'no', $current_data[ self::SLUG ] ); ?> /> <?php esc_html( _ex( 'No', 'ticket registration option', 'wordcamporg' ) ); ?></label>
			</td>
		</tr>

		<?php
	}

	/**
	 * Validate the value of the new field submitted to the registration form during checkout.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function validate_registration_field( $data ) {
		/* @var CampTix_Plugin $camptix */
		global $camptix;

		if ( ! isset( $data[ self::SLUG ] ) || empty( $data[ self::SLUG ] ) ) {
			$camptix->error_flags['required_fields'] = true;
		} else {
			$data[ self::SLUG ] = ( 'yes' === $data[ self::SLUG ] ) ? 'yes' : 'no';
		}

		return $data;
	}

	/**
	 * Add the value of the new field to the attendee object during checkout processing.
	 *
	 * @param WP_Post $attendee
	 * @param array    $data
	 *
	 * @return WP_Post
	 */
	public function populate_attendee_object( $attendee, $data ) {
		$attendee->{ self::SLUG } = $data[ self::SLUG ];

		return $attendee;
	}

	/**
	 * Save the value of the new field to the attendee post upon completion of checkout.
	 *
	 * @param int     $post_id
	 * @param WP_Post $attendee
	 *
	 * @return bool|int
	 */
	public function save_registration_field( $post_id, $attendee ) {
		return update_post_meta( $post_id, 'tix_' . self::SLUG, $attendee->{ self::SLUG } );
	}

	/**
	 * Initialize email notifications after the ticket receipt email has been sent.
	 *
	 * @param int $attendee_id
	 */
	public function after_email_receipt( $attendee_id ) {
		$attendee = get_post( $attendee_id );
		$value    = get_post_meta( $attendee_id, 'tix_' . self::SLUG, true );

		if ( $attendee instanceof WP_Post && 'tix_attendee' === $attendee->post_type ) {
			$this->maybe_send_notification_email( $value, $attendee );
		}
	}

	/**
	 * Retrieve the stored value of the new field for use on the Edit Info form.
	 *
	 * @param array   $ticket_info
	 * @param WP_Post $attendee
	 *
	 * @return array
	 */
	public function populate_ticket_info_array( $ticket_info, $attendee ) {
		$ticket_info[ self::SLUG ] = get_post_meta( $attendee->ID, 'tix_' . self::SLUG, true );

		return $ticket_info;
	}

	/**
	 * Update the stored value of the new field if it was changed in the Edit Info form.
	 *
	 * @param array   $data
	 * @param WP_Post $attendee
	 *
	 * @return bool|int
	 */
	public function validate_save_ticket_info_field( $data, $attendee ) {
		$value = ( 'yes' === $data[ self::SLUG ] ) ? 'yes' : 'no';

		$this->maybe_send_notification_email( $value, $attendee );

		return update_post_meta( $attendee->ID, 'tix_' . self::SLUG, $value );
	}

	/**
	 * Render the new field for the Edit Info form.
	 *
	 * @param array $ticket_info
	 */
	public function render_ticket_info_field( $ticket_info ) {
		$current_data = wp_parse_args( $ticket_info, array(
			self::SLUG => 'no',
		) );

		?>

		<tr class="tix-row-<?php echo esc_attr( self::SLUG ); ?>">
			<td class="tix-required tix-left">
				<?php echo esc_html( $this->question ); ?>
				<span class="tix-required-star">*</span>
			</td>

			<td class="tix-right">
				<label><input name="tix_ticket_info[<?php echo esc_attr( self::SLUG ); ?>]" type="radio" value="yes" <?php checked( 'yes', $current_data[ self::SLUG ] ); ?> /> <?php esc_html( _ex( 'Yes (we will contact you)', 'ticket registration option', 'wordcamporg' ) ); ?></label>
				<br />
				<label><input name="tix_ticket_info[<?php echo esc_attr( self::SLUG ); ?>]" type="radio" value="no" <?php checked( 'no', $current_data[ self::SLUG ] ); ?> /> <?php esc_html( _ex( 'No', 'ticket registration option', 'wordcamporg' ) ); ?></label>
			</td>
		</tr>

		<?php
	}

	/**
	 * Add a row to the Attendee Info metabox table for the new field and value.
	 *
	 * @param array   $rows
	 * @param WP_Post $post
	 *
	 * @return mixed
	 */
	public function add_metabox_row( $rows, $post ) {
		$value = get_post_meta( $post->ID, 'tix_' . self::SLUG, true ) ?: '';
		if ( $value && isset( $this->options[ $value ] ) ) {
			$value = $this->options[ $value ];
		}
		$new_row = array( $this->question, esc_html( $value ) );

		add_filter( 'locale', array( $this, 'set_locale_to_en_US' ) );

		$ticket_row = array_filter( $rows, function( $row ) {
			if ( 'Ticket' === $row[0] ) {
				return true;
			}

			return false;
		} );

		remove_filter( 'locale', array( $this, 'set_locale_to_en_US' ) );

		if ( ! empty( $ticket_row ) ) {
			$ticket_row_key = key( $ticket_row );
			$row_indexes    = array_keys( $rows );
			$position       = array_search( $ticket_row_key, $row_indexes );

			$slice = array_slice( $rows, $position );

			array_unshift( $slice, $new_row );
			array_splice( $rows, $position, count( $rows ), $slice );
		} else {
			$rows[] = $new_row;
		}

		return $rows;
	}

	/**
	 * Send a notification if it hasn't been sent already.
	 *
	 * @param string  $value
	 * @param WP_Post $attendee
	 */
	protected function maybe_send_notification_email( $value, $attendee ) {
		// Only send notifications for 'yes' answers.
		if ( 'yes' !== $value ) {
			return;
		}

		$already_sent = get_post_meta( $attendee->ID, '_tix_notify_' . self::SLUG, true );

		// Only send the notification once.
		if ( $already_sent ) {
			return;
		}

		global $phpmailer;
		if ( $phpmailer instanceof PHPMailer ) {
			// Clear out any lingering content from a previously sent message.
			$phpmailer = new PHPMailer( true );
		}

		$current_wordcamp = get_wordcamp_post();
		$wordcamp_name    = get_wordcamp_name( get_wordcamp_site_id( $current_wordcamp ) );
		$post_type_object = get_post_type_object( $attendee->post_type );
		$attendee_link    = add_query_arg( 'action', 'edit', admin_url( sprintf( $post_type_object->_edit_link, $attendee->ID ) ) );
		$handbook_link    = 'https://make.wordpress.org/community/handbook/wordcamp-organizer/first-steps/inclusive-and-welcoming-events/#requests-for-special-accommodations';
		$support_email    = 'support@wordcamp.org';
		$recipients       = array(
			$current_wordcamp->meta['Email Address'][0], // Lead organizer
			$current_wordcamp->meta['E-mail Address'][0], // City address
			$support_email,
		);

		foreach ( $recipients as $recipient ) {
			if ( $support_email === $recipient ) {
				// Make sure the email to WordCamp Central is in English.
				add_filter( 'locale', array( $this, 'set_locale_to_en_US' ) );
			}

			$subject = sprintf(
				/* translators: Email subject line. The %s placeholder is the name of a WordCamp. */
				wp_strip_all_tags( __( 'An attendee who requires special accommodations has registered for %s', 'wordcamporg' ) ),
				$wordcamp_name
			);

			$message_line_1 = wp_strip_all_tags( __( 'The following attendee has indicated that they require special accommodations. Please note that this information is confidential.', 'wordcamporg' ) );

			$message_line_2 = wp_strip_all_tags( __( 'Please follow the procedure outlined in the WordCamp Organizer Handbook to ensure the health and safety of this event\'s attendees.', 'wordcamporg' ) );
			if ( $support_email === $recipient ) {
				$message_line_2 = 'Please check in with the organizing team to ensure they\'re following the procedure outlined in the WordCamp Organizer Handbook to ensure the health and safety of this event\'s attendees.';
			}

			$message = sprintf(
				"%s\n\n%s\n\n%s\n\n%s",
				$message_line_1,
				esc_url_raw( $attendee_link ), // Link to attendee post's Edit screen.
				$message_line_2,
				$handbook_link // Link to page in WordCamp Organizer Handbook.
			);

			if ( $support_email === $recipient ) {
				remove_filter( 'locale', array( $this, 'set_locale_to_en_US' ) );
			}

			wp_mail( $recipient, $subject, $message );
		}

		/**
		 * Action: Fires when a notification is sent about a WordCamp attendee who requires special accommodations.
		 *
		 * @param array $details Contains information about the WordCamp and the attendee.
		 */
		do_action( 'camptix_tweaks_accommodations_notification', array(
			'wordcamp' => $current_wordcamp,
			'attendee' => $attendee,
		) );

		update_post_meta( $attendee->ID, '_tix_notify_' . self::SLUG, true );
	}

	/**
	 * Filter: Set the locale to en_US.
	 *
	 * @return string
	 */
	public function set_locale_to_en_US() {
		return 'en_US';
	}

	/**
	 * Include the new field in the personal data exporter.
	 *
	 * @param array $props
	 *
	 * @return array
	 */
	public function attendee_props_to_export( $props ) {
		$props[ 'tix_' . self::SLUG ] = $this->question;

		return $props;
	}

	/**
	 * Add the new field's value and label to the aggregated personal data for export.
	 *
	 * @param array   $export
	 * @param string  $key
	 * @param string  $label
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	public function export_attendee_prop( $export, $key, $label, $post ) {
		if ( 'tix_' . self::SLUG === $key ) {
			$value = get_post_meta( $post->ID, 'tix_' . self::SLUG, true );

			if ( isset( $this->options[ $value ] ) ) {
				$value = $this->options[ $value ];
			}

			if ( ! empty( $value ) ) {
				$export[] = array(
					'name'  => $label,
					'value' => $value,
				);
			}
		}

		return $export;
	}

	/**
	 * Include the new field in the personal data eraser.
	 *
	 * @param array $props
	 *
	 * @return array
	 */
	public function attendee_props_to_erase( $props ) {
		$props[ 'tix_' . self::SLUG ] = 'camptix_yesno';

		return $props;
	}

	/**
	 * Anonymize the value of the new field during personal data erasure.
	 *
	 * @param string  $key
	 * @param string  $type
	 * @param WP_Post $post
	 */
	public function erase_attendee_prop( $key, $type, $post ) {
		if ( 'tix_' . self::SLUG === $key ) {
			$anonymized_value = wp_privacy_anonymize_data( $type );
			update_post_meta( $post->ID, $key, $anonymized_value );
		}
	}
}

camptix_register_addon( __NAMESPACE__ . '\Accommodations_Field' );
