<?php
/**
 * Widget API: WPORG_Widget_Download class
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

/**
 * Core class used to implement a Download button widget.
 *
 * Displays a WordPress download button.
 *
 * @see WP_Widget
 */
class WPORG_Widget_Download extends \WP_Widget {

	/**
	 * Sets up a new Download widget instance.
	 *
	 * @access public
	 */
	public function __construct() {
		parent::__construct( 'wporg_download', __( 'Download', 'wporg' ), array(
			'classname'                   => 'widget_download',
			'description'                 => __( 'WordPress download button.', 'wporg' ),
			'customize_selective_refresh' => true,
		) );
	}

	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.Found
	/**
	 * Outputs the content for the current Download widget instance.
	 *
	 * @access public
	 *
	 * @param array $args     Display arguments including 'before_widget' and 'after_widget'.
	 * @param array $instance Settings for the current Download widget instance.
	 */
	public function widget( $args, $instance ) {
		// phpcs:enable
		global $rosetta;

		$latest_release = false;

		if ( null !== $rosetta ) {
			$latest_release = $GLOBALS['rosetta']->rosetta->get_latest_release();
		}

		if ( false === $latest_release ) {
			return;
		}

		// phpcs:disable WordPress.XSS.EscapeOutput.OutputNotEscaped

		echo $args['before_widget'];
		?>
		<div>
			<a class="button button-primary button-large" href="<?php echo esc_url( $latest_release['zip_url'] ); ?>" role="button">
				<?php
				echo apply_filters( 'no_orphans', sprintf(
					/* translators: WordPress version. */
					__( 'Download WordPress %s', 'wporg' ),
					$latest_release['version']
				) );
				?>
			</a>
			<div>
				<?php
				printf(
					/* translators: Size of .zip file. */
					__( '.zip &mdash; %s MB', 'wporg' ),
					esc_html( $latest_release['zip_size_mb'] )
				);
				?>
			</div>
		</div>

		<div>
			<a href="<?php echo esc_url( $latest_release['targz_url'] ); ?>">
				<?php
				printf(
					/* translators: Size of .tar.gz file. */
					__( 'Download .tar.gz &mdash; %s MB', 'wporg' ),
					esc_html( $latest_release['tar_size_mb'] )
				);
				?>
			</a>
		</div>
		<?php
		echo $args['after_widget'];
	}
}
