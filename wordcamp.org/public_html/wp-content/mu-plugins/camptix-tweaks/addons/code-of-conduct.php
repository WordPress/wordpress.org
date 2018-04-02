<?php

namespace WordCamp\CampTix_Tweaks;
defined( 'WPINC' ) or die();

use CampTix_Plugin, CampTix_Addon;

/**
 * Class Code_Of_Conduct_Field.
 *
 * Add a non-optional attendee field confirming that they agree to follow the event code of conduct.
 *
 * @package WordCamp\CampTix_Tweaks
 */
class Code_Of_Conduct_Field extends CampTix_Addon {
	const SLUG = 'coc';

	/**
	 * Hook into WordPress and Camptix.
	 */
	public function camptix_init() {
		// Registration field
		add_action( 'camptix_attendee_form_after_questions', array( $this, 'render_registration_field' ), 15, 2 );
		add_filter( 'camptix_checkout_attendee_info', array( $this, 'validate_registration_field' ) );
		add_action( 'camptix_form_attendee_info_errors', array( $this, 'add_registration_field_validation_error' ) );
		add_filter( 'camptix_form_register_complete_attendee_object', array( $this, 'populate_attendee_object' ), 10, 2 );
		add_action( 'camptix_checkout_update_post_meta', array( $this, 'save_registration_field' ), 10, 2 );
	}

	/**
	 * Render the new field for the registration form during checkout.
	 *
	 * @param array $form_data
	 * @param int   $i
	 */
	public function render_registration_field( $form_data, $i ) {
		$current_data = wp_parse_args( $form_data['tix_attendee_info'][ $i ], array(
			self::SLUG => false,
		) );

		$current_data[ self::SLUG ] = wp_validate_boolean( $current_data[ self::SLUG ] );

		?>

		<tr class="tix-row-<?php echo esc_attr( self::SLUG ); ?>">
			<td class="tix-required tix-left">
				<?php
				if ( $coc_url = $this->maybe_get_coc_url() ) :
					printf(
						/* translators: %s placeholder is a URL */
						wp_kses_post( __( 'Do you agree to follow the event <a href="%s">Code of Conduct</a>?', 'wordcamporg' ) ),
						esc_url( $coc_url )
					);
				else :
					esc_html_e( 'Do you agree to follow the event Code of Conduct?', 'wordcamporg' );
				endif;
				?>
				<span class="tix-required-star">*</span>
			</td>

			<td class="tix-right">
				<label><input name="tix_attendee_info[<?php echo esc_attr( $i ); ?>][<?php echo esc_attr( self::SLUG ); ?>]" type="checkbox" <?php checked( $current_data[ self::SLUG ] ); ?> /> <?php esc_html_e( 'Yes', 'wordcamporg' ); ?></label>
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
			$camptix->error_flags[ self::SLUG . '_unchecked' ] = true;
		}

		$data[ self::SLUG ] = wp_validate_boolean( $data[ self::SLUG ] );

		if ( true !== $data[ self::SLUG ] ) {
			$camptix->error_flags[ self::SLUG . '_unchecked' ] = true;
		}

		return $data;
	}

	/**
	 *
	 *
	 * @param array $error_flags
	 */
	public function add_registration_field_validation_error( $error_flags ) {
		/* @var CampTix_Plugin $camptix */
		global $camptix;

		if ( isset( $error_flags[ self::SLUG . '_unchecked' ] ) ) {
			$camptix->error( __( 'You must agree to follow the event Code of Conduct to obtain a ticket.', 'wordcamporg' ) );
		}
	}

	/**
	 * Add the value of the new field to the attendee object during checkout processing.
	 *
	 * @param \WP_Post $attendee
	 * @param array    $data
	 *
	 * @return \WP_Post
	 */
	public function populate_attendee_object( $attendee, $data ) {
		$attendee->{ self::SLUG } = $data[ self::SLUG ];

		return $attendee;
	}

	/**
	 * Save the value of the new field to the attendee post upon completion of checkout.
	 *
	 * @param int      $post_id
	 * @param \WP_Post $attendee
	 *
	 * @return bool|int
	 */
	public function save_registration_field( $post_id, $attendee ) {
		return update_post_meta( $post_id, 'tix_' . self::SLUG, $attendee->{ self::SLUG } );
	}

	/**
	 * If the Code of Conduct page is still the same one created with the site, get its URL.
	 *
	 * @return false|string
	 */
	protected function maybe_get_coc_url() {
		$url = '';

		$coc_page = get_posts( array(
			'post_type'   => 'page',
			'name'        => 'code-of-conduct',
			'numberposts' => 1,
		) );

		if ( $coc_page ) {
			$url = get_the_permalink( array_shift( $coc_page ) );
		}

		return $url;
	}
}

camptix_register_addon( __NAMESPACE__ . '\Code_Of_Conduct_Field' );
