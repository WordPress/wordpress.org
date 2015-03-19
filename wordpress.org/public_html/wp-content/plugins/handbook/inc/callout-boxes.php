<?php
/**
 * Class to register shortcodes for callout boxes to be used in handbooks.
 *
 * @package handbook
 */

class WPorg_Handbook_Callout_Boxes {

	/**
	 * Array of key => value pairs where the shortcode name is the key, and the
	 * localized callout box prefix is the value.
	 *
	 * @access public
	 * @var array
	 */
	public $shortcodes = array();

	/**
	 * Constructor.
	 *
	 * @access public
	 */
	public function __construct() {
		$this->shortcodes = array(
			'info'    => __( 'Note:',    'wporg' ),
			'tip'     => __( 'Tip:',     'wporg' ),
			'alert'   => __( 'Alert:',   'wporg' ),
			'tutorial' => __( 'Tutorial:', 'wporg' ),
			'warning' => __( 'Warning:', 'wporg' )
		);

		add_action( 'init', array( $this, 'register_shortcodes' ) );
	}

	/**
	 * Register the callout box shortcodes.
	 *
	 * @access public
	 */
	public function register_shortcodes() {
		foreach ( array_keys( $this->shortcodes ) as $name) {
			add_shortcode( $name, array( $this, "{$name}_shortcode" ) );
		}
	}

	/**
	 * Output callback for the `[info]` shortcode.
	 *
	 * @access public
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string Shortcode output as HTML markup.
	 */
	public function info_shortcode( $atts, $content = '' ) {
		return $this->build_callout_output( $content, 'info' );
	}

	/**
	 * Output callback for the `[tip]` shortcode.
	 *
	 * @access public
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string Shortcode output as HTML markup.
	 */
	public function tip_shortcode( $atts, $content = '' ) {
		return $this->build_callout_output( $content, 'tip' );
	}

	/**
	 * Output callback for the `[alert]` shortcode.
	 *
	 * @access public
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string Shortcode output as HTML markup.
	 */
	public function alert_shortcode( $atts, $content = '' ) {
		return $this->build_callout_output( $content, 'alert' );
	}

	/**
	 * Output callback for the `[tutorial]` shortcode.
	 *
	 * @access public
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string Shortcode output as HTML markup.
	 */
	public function tutorial_shortcode( $atts, $content = '' ) {
		return $this->build_callout_output( $content, 'tutorial' );
	}

	/**
	 * Output callback for the `[warning]` shortcode.
	 *
	 * @access public
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string Shortcode output as HTML markup.
	 */
	public function warning_shortcode( $atts, $content = '' ) {
		return $this->build_callout_output( $content, 'warning' );
	}

	/**
	 * Build callout box output for the given shortcode.
	 *
	 * @access protected
	 *
	 * @param string $content   Shortcode content.
	 * @param string $shortcode Shortcode name.
	 * @return string Shortcode output as HTML markup.
	 */
	protected function build_callout_output( $content, $shortcode ) {
		$class = $prefix = $output = '';

		// Sanitize message content.
		$content = wp_kses_post( $content );

		if ( isset( $this->shortcodes[ $shortcode ] ) && ! empty( $content ) ) {
			// Shortcode-type CSS class.
			$class = sanitize_html_class( $shortcode );
			$class = empty( $class ) ? '' : "callout-{$class}";

			// Message prefix.
			$prefix = '<strong>' . $this->shortcodes[ $shortcode ] . '</strong>';

			// Content with prefix.
			$content = "{$prefix} {$content}";

			// Callout box output.
			$output .= "<div class='callout {$class}'>";
			$output .= '<div class="dashicons"></div>'; // Icon holder
			$output .= apply_filters( 'the_content', $content );
			$output .= '</div>';
		}
		return $output;
	}
}

$callouts = new WPorg_Handbook_Callout_Boxes();
