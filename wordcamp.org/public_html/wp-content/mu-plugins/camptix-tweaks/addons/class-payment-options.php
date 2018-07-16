<?php
/**
 * Renders a different payment options layout, which shows Stripe as the preferred option
 */

namespace WordCamp\CampTix_Tweaks;

defined( 'WPINC' ) or die();

use CampTix_Addon;

/**
 * Class Payment_Options
 *
 * Renders payment options with a preference for Stripe
 *
 * @package WordCamp\CampTix_Tweaks
 */
class Payment_Options extends CampTix_Addon {

	/**
	 * Initialize Payment_Options class
	 */
	function camptix_init() {
		add_filter( 'tix_render_payment_options', array( $this, 'generate_payment_options'), 15, 4 );
		$this->enqueue_scripts_and_styles();
	}

	/**
	 * Enqueue styles and scripts needed for the addon to work
	 */
	function enqueue_scripts_and_styles() {
		wp_register_script(
			'payment_options',
			plugins_url( 'js/payment-options.js', __FILE__ ),
			array( 'stripe-checkout', 'camptix' ),
			filemtime( __DIR__ . '/js/payment-options.js' )
		);

		wp_register_style(
			'payment_options',
			plugins_url( 'css/payment-options.css', __FILE__ ),
			array(),
			filemtime( __DIR__ . '/css/payment-options.css' )
		);

		wp_enqueue_script( 'payment_options' );
		wp_enqueue_style( 'payment_options' );
	}

	/**
	 * We have stripe selected when there is no selected payment method, or when stripe is already selected
	 *
	 * @param array $payment_methods
	 * @param string $selected_payment_method
	 *
	 * @return bool
	 */
	private function has_stripe_selected( $payment_methods, $selected_payment_method ){
		return array_key_exists( 'stripe', $payment_methods ) && ( ! isset( $selected_payment_method ) || $selected_payment_method === 'stripe' );
	}

	/**
	 * Filter implementation for new payment layout.
	 *
	 * @param array  $payment_output Not needed since we are generating a new layout.
	 * @param float  $total Total amount.
	 * @param array  $payment_methods List of payment methods.
	 * @param string $selected_payment_method Already selected payment method.
	 */
	function generate_payment_options( $payment_output, $total, $payment_methods, $selected_payment_method ) {
		?>
		<div class="tix-submit">
			<?php if ( $total > 0 ) : ?>
				<div class="tix-payment-method" role="tabs">
					<?php $this->render_tab_bar( $payment_methods, $selected_payment_method ); ?>
				</div>
				<div class="tix-payment-method-container
				<?php
					if (
							$this->only_one_payment_method( $payment_methods ) ||
							$this->has_stripe_selected( $payment_methods, $selected_payment_method ) ) {
						echo 'tix-hidden ';
					}
					echo ! $this->is_stripe_available( $payment_methods ) ? 'tix-wide-tab' : '';
				?>">
					<?php $this->render_alternate_payment_options( $payment_methods, $selected_payment_method ); ?>
				</div>
				<input class="tix-checkout-button" type="submit" value="<?php esc_attr_e( 'Checkout &rarr;', 'wordcamporg' ); ?>" />
			<?php else : ?>
				<input class="tix-checkout-button" type="submit" value="<?php esc_attr_e( 'Claim Tickets &rarr;', 'wordcamporg' ); ?>" />
			<?php endif; ?>
			<br class="tix-clear" />
		</div>
		<?php
	}

	/**
	 * Render a payment option as a separate tab. Used for rendering stripe tab, or a payment method tab incase only 1
	 * is available.
	 *
	 * @param array  $payment_methods
	 * @param string $key
	 * @param bool   $selected Whether this option is pre selected
	 */
	private function render_payment_option_as_tab( $payment_methods, $key, $selected ) {
		$is_only_payment_option = $this->only_one_payment_method( $payment_methods );
		?>
		<input type="radio" role="tab" name="tix_payment_method" id="tix-preferred-payment-option"
			   value="<?php echo esc_html( $key ); ?>"
			   autocomplete="off"
				<?php echo $selected || $is_only_payment_option ? 'checked' : ''; ?>
		>
		<label for="tix-preferred-payment-option"
			   class="tix-payment-tab
			   <?php
				   echo $is_only_payment_option ? 'tix-wide-tab' : ' ';
				   echo $selected || $is_only_payment_option ? ' tix-tab-selected' : '';
			   ?>
			">
			<?php
				//translators: %s: Name of the available payment method
				printf( esc_html__( 'Pay with %s', 'wordcamporg' ), esc_html( $payment_methods[ $key ]['name'] ) );
			?>
		</label>
		<?php
	}

	/**
	 * Renders tab of the payment options layout. Stripe is rendered as a different tab and is actually a label of an
	 * input
	 *
	 * @param array  $payment_methods
	 * @param string $selected_payment_method Pre selected payment method
	 */
	function render_tab_bar( $payment_methods, $selected_payment_method ) {

		if ( $this->only_one_payment_method( $payment_methods ) ) {
			// render payment option as a tab and bail.
			$payment_method_key = array_keys( $payment_methods )[0];
			$this->render_payment_option_as_tab( $payment_methods, $payment_method_key, true );
			return;
		}

		// Stripe is the preferred payment method when it is available.
		$has_stripe_payments_tab = false;

		if ( $this->is_stripe_available( $payment_methods ) ) {
			$has_stripe_payments_tab = true;
			$this->render_payment_option_as_tab(
					$payment_methods,
					'stripe',
					$this->has_stripe_selected( $payment_methods, $selected_payment_method )
			);
		}
		?>
		<button
				role="tab"
				class="tix_other_payment_options tix-payment-tab
					<?php
						echo ! $this->has_stripe_selected( $payment_methods, $selected_payment_method ) ? 'tix-tab-selected ' : '';
						echo ! $this->is_stripe_available( $payment_methods ) ? 'tix-wide-tab ' : '';
					?>"
				type="button">
			<?php
				if ( $has_stripe_payments_tab ) {
					esc_html_e( 'Other payment methods', 'wordcamporg' );
				} else {
					esc_html_e( 'Payment methods', 'wordcamporg' );
				}
			?>
		</button>
		<?php
	}

	/**
	 * Utility method to check if stripe payment gateway is enabled
	 *
	 * @param array $payment_methods
	 *
	 * @return bool
	 */
	private function is_stripe_available( $payment_methods ) {
		return array_key_exists( 'stripe', $payment_methods );
	}

	/**
	 * Utility function to check if only payment method is available
	 *
	 * @param array $payment_methods
	 *
	 * @return bool
	 */
	private function only_one_payment_method( $payment_methods ) {
		return count( $payment_methods ) === 1;
	}

	/**
	 * Renders all other payment methods except stripe
	 *
	 * @param array  $payment_methods
	 * @param string $selected_payment_method Pre selected payment method
	 */
	function render_alternate_payment_options( $payment_methods, $selected_payment_method ) {
		if ( $this->only_one_payment_method( $payment_methods ) ) {
			// bail if only one payment option is available.
			return;
		}

		// Pre-select first payment method if stripe is not available
		if ( ! $this->is_stripe_available( $payment_methods ) && ! isset( $selected_payment_method ) ) {
			$selected_payment_method = array_keys( $payment_methods )[0];
		}

		foreach ( $payment_methods as $payment_method_key => $payment_method ) {
			if ( 'stripe' === $payment_method_key ) {
				continue;
			}
			?>

			<div class="tix-alternate-payment-option">
				<input type="radio" name="tix_payment_method"
						id="tix-payment-method_<?php echo esc_attr( $payment_method_key ); ?>"
						value="<?php echo esc_attr( $payment_method_key ); ?>"
						autocomplete="off"
						required
						<?php echo $selected_payment_method === $payment_method_key ? 'checked' : ''; ?>
				>

				<label for="tix-payment-method_<?php echo esc_attr( $payment_method_key ); ?>">
					<?php echo esc_html( $payment_method['name'] ); ?>
				</label>
			</div>

			<?php
		}
	}
}

camptix_register_addon( __NAMESPACE__ . '\Payment_Options' );
