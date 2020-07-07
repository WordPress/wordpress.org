<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use WordPressdotorg\Plugin_Directory\CLI\Block_Plugin_Checker;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

class Block_Validator {

	/**
	 * Displays a form to validate block plugins.
	 */
	public static function display() {
		ob_start();
		$plugin_url = $_REQUEST['plugin_url'] ?? '';

		if ( is_user_logged_in() ) :
		?>
		<div class="wrap">
			<form method="post" action="">
				<p>
					<label for="plugin_url"><?php _e( 'Plugin repo URL', 'wporg-plugins' ); ?></label>
				</p>
				<p>
					<input type="text" id="plugin_url" name="plugin_url" size="70" placeholder="https://plugins.svn.wordpress.org/" value="<?php echo esc_attr( $plugin_url ); ?>" />
					<input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Validate!', 'wporg-plugins' ); ?>" />
					<?php wp_nonce_field( 'validate-block-plugin', 'block-nonce' ); ?>
				</p>
			</form>

			<?php
			if ( $_POST && !empty( $_POST['plugin_url'] ) && wp_verify_nonce( $_POST['block-nonce'], 'validate-block-plugin' ) ) {
				self::validate_block( $_POST['plugin_url'] );
			} elseif ( $_POST && !empty( $_POST['block-directory-edit'] ) ) {
				$post = get_post( intval( $_POST['plugin-id'] ) );
				if ( $post && wp_verify_nonce( $_POST['block-directory-nonce'], 'block-directory-edit-' . $post->ID ) ) {
					if ( current_user_can( 'edit_post', $post->ID ) ) {
						$terms = wp_list_pluck( get_the_terms( $post->ID, 'plugin_section' ), 'slug' );
						if ( 'add' === $_POST['block-directory-edit'] ) {
							$terms[] = 'block';
						} elseif ( 'remove' === $_POST['block-directory-edit'] ) {
							$terms = array_diff( $terms, array( 'block' ) );
						}
						wp_set_object_terms( $post->ID, $terms, 'plugin_section' );
					}

					self::validate_block( $post->post_name );
				}
			}
			?>
		</div>
		<?php else: ?>
		<div class="wrap">
			<p><?php _e( 'Please log in to use the validator.', 'wporg-plugins' ); ?></p>
		</div>
		<?php endif;
		return ob_get_clean();
	}

	protected static function plugin_is_in_block_directory( $slug ) {
		$plugin = Plugin_Directory::get_plugin_post( $slug );

		return ( 
			$plugin &&
			$plugin->post_name === $slug && 
			has_term( 'block', 'plugin_section', $plugin )
		);
	}

	/**
	 * Validates readme.txt contents and adds feedback.
	 *
	 * @param string $plugin_url The URL of a Subversion or GitHub repository.
	 */
	protected static function validate_block( $plugin_url ) {

		$checker = new Block_Plugin_Checker();
		$results = $checker->run_check_plugin_repo( $plugin_url );

		echo '<h2>' . __( 'Results', 'wporg-plugins' ) . '</h2>';

		if ( $checker->repo_url && $checker->repo_revision ) {
			echo '<p>';
			printf(
				'Results for %1$s revision %2$s',
				'<code>' . esc_url( $checker->repo_url ) . '</code>',
				esc_html( $checker->repo_revision )
			);
			echo '</p>';
		}

		if ( $checker->slug ) {
			$plugin = Plugin_Directory::get_plugin_post( $checker->slug );
			if ( current_user_can( 'edit_post', $plugin->ID ) ) {
				echo '<form method="post">';
				echo '<fieldset>';
				echo '<legend>' . __( 'Plugin Review Tools', 'wporg-plugins' ) . '</legend>';
				echo wp_nonce_field( 'block-directory-edit-' . $plugin->ID, 'block-directory-nonce' );
				echo '<input type="hidden" name="plugin-id" value="' . esc_attr( $plugin->ID ) . '" />';
				if ( self::plugin_is_in_block_directory( $checker->slug ) ) {
					echo '<button type="submit" name="block-directory-edit" value="remove">' . __( 'Remove from Block Directory', 'wporg-plugins' ) . '</button>';
				} else {
					echo '<button type="submit" name="block-directory-edit" value="add">' . __( 'Add to Block Directory', 'wporg-plugins' ) . '</button>';
				}
	
				echo '<ul><li><a href="' . get_edit_post_link( $plugin->ID ) . '">' . __( 'Edit plugin', 'wporg-plugins' ) . '</a></li>';
				echo '<li><a href="' . esc_url( 'https://plugins.trac.wordpress.org/browser/' . $checker->slug . '/trunk' ) .'">' . __( 'Trac browser', 'wporg-plugins' ) . '</a></li></ul>';
				echo '</fieldset>';
				echo '</form>';
			}
		}

		$results_by_type = array();
		foreach ( $results as $item ) {
			$results_by_type[ $item->type ][] = $item;
		}

		$output = '';

		if ( empty( $results_by_type['error'] ) ) {
			$output .= '<h3>' . __( 'Success', 'wporg-plugins' ) . '</h3>';
			$output .= "<div class='notice notice-success notice-alt'>\n";
			if ( $checker->slug && self::plugin_is_in_block_directory( $checker->slug ) ) {
				$output .= __( 'No problems were found. This plugin is already in the Block Directory.', 'wporg-plugins' );
			} else {
				$output .= __( 'No problems were found. Your plugin has passed the first step towards being included in the Block Directory.', 'wporg-plugins' );
			}
			$output .= "</div>\n";
		} else {
			$output .= '<h3>' . __( 'Problems were encountered', 'wporg-plugins' ) . '</h3>';
			$output .= "<div class='notice notice-error notice-alt'>\n";
			$output .= __( 'Some problems were found. They need to be addressed before your plugin will work in the Block Directory.', 'wporg-plugins' );
			$output .= "</div>\n";
		}

		$error_types = array(
			'error'   => __( 'Fatal Errors:', 'wporg-plugins' ),
			'warning' => __( 'Warnings:', 'wporg-plugins' ),
			'info'    => __( 'Notes:', 'wporg-plugins' ),
		);
		foreach ( $error_types as $type => $warning_label ) {
			if ( empty( $results_by_type[ $type ] ) ) {
				continue;
			}

			$output .= "<h3>{$warning_label}</h3>\n";
			$output .= "<div class='notice notice-{$type} notice-alt'>\n";
			$output .= "<ul class='{$type}'>\n";
			foreach ( $results_by_type[ $type ] as $item ) {
				$docs_link = '';
				if ( 'check' === substr( $item->check_name, 0, 5 ) ) {
					$docs_link = "<a href='help#{$item->check_name}'>" . __( 'More about this.', 'wporg-plugins' ) . '</a>';
				}
				$output .= "<li class='{$item->check_name}'>{$item->message} {$docs_link}</li>\n";
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
