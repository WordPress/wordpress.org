<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use WordPressdotorg\Plugin_Directory\CLI\Block_Plugin_Checker;

class Block_Validator {

	/**
	 * Displays a form to validate block plugins.	
	 */
	public static function display() {
		?>
		<div class="wrap">
<?php
		if ( $_POST && wp_verify_nonce( $_POST['block-nonce'], 'validate-block-plugin' ) ) {

				self::validate_block( $_POST['plugin_url'] );
			}

			$plugin_url      = $_REQUEST['plugin_url'] ?? '';
			?>

			<form method="post" action="">
				<p>
					<input type="text" name="plugin_url" size="70" placeholder="https://plugins.svn.wordpress.org/" value="<?php echo esc_url( $plugin_url ); ?>" />
					<input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Validate!', 'wporg-plugins' ); ?>" />
					<?php wp_nonce_field( 'validate-block-plugin', 'block-nonce' ); ?>
				</p>
			</form>

		</div>
		<?php
	}

	/**
	 * Validates readme.txt contents and adds feedback.
	 */
	protected static function validate_block( $plugin_url ) {

		$checker = new Block_Plugin_Checker();
		$results = $checker->run_check_plugin_repo( $plugin_url );

		if ( $checker->repo_url && $checker->repo_revision ) {
			echo '<h2>Validating..</h2>';
			echo '<p>Results for ' . esc_url( $checker->repo_url ) . ' revision ' . esc_html( $checker->repo_revision ) . '</p>';
		}

		$results_by_type = array();
		foreach ( $results as $item ) {
			$results_by_type[ $item->type ][] = $item;
		}


		$output = '';

		$error_types = array(
			'error'   => __( 'Fatal Errors:', 'wporg-plugins' ),
			'warning' => __( 'Warnings:', 'wporg-plugins' ),
			'info'    => __( 'Notes:', 'wporg-plugins' ),
		);
		foreach ( $error_types as $type => $warning_label ) {

				if ( empty( $results_by_type[ $type ] ) )
					continue;

				$output .= "<h3>{$warning_label}</h3>\n";
				$output .= "<div class='notice notice-{$type} notice-alt'>\n";
				$output .= "<ul class='{$type}'>\n";
				foreach ( $results_by_type[ $type ] as $item ) {
					$output .= "<li title='{$item->check_name}'><a href='/hypothetical/doc/page#{$item->check_name}'><span class='dashicons dashicons-info'></span></a> {$item->message}</li>\n";
				}
				$output .= "</ul>\n";
				$output .= "</div>\n";
		}

		if ( empty( $output ) ) {
			$output .= '<div class="notice notice-success notice-alt">';
			$output .= '<p>' . __( 'Congratulations! No errors found.', 'wporg-plugins' ) . '</p>';
			$output .= '</div>';
		}

		echo $output;
	}
}
