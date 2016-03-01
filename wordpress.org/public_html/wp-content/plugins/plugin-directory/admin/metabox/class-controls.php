<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

/**
 * The Plugin Controls / Publish metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Controls {

	/**
	 * Displays the Publish metabox for plugins.
	 * The HTML here matches what Core uses.
	 */
	static function display() {
		echo '<div class="submitbox" id="submitpost">';
			echo '<div id="misc-publishing-actions">';
				self::display_post_status();

				if ( 'publish' == get_post_status() ) {
					self::display_tested_up_to();
				}
			echo '</div>';

			echo '<div id="major-publishing-actions"><div id="publishing-action">';
				echo '<span class="spinner"></span>';
				printf( '<input type="submit" name="save_changes" id="publish" class="button button-primary button-large" value="%s">', __( 'Save Changes', 'wporg-plugins' ) );
			echo '</div><div class="clear"></div></div>';
		echo '</div>';
	}

	/**
	 * Displays the Plugin Status control in the Publish metabox.
	 */
	protected static function display_post_status() {
		$post = get_post();

		$statuses = array( 'publish', 'pending', 'disabled', 'closed', 'rejected' );
		// TODO Add capability check here
		?>
		<div class="misc-pub-section misc-pub-plugin-status">
			<label for="post_status"><?php _e( 'Status:', 'wporg-plugins' ); ?></label>
			<strong id="plugin-status-display"><?php echo esc_html( get_post_status_object( $post->post_status )->label ); ?></strong>
			<button type="button" class="button-link edit-plugin-status hide-if-no-js">
				<span aria-hidden="true"><?php _e( 'Edit', 'wporg-plugins' ); ?></span>
				<span class="screen-reader-text"><?php _e( 'Edit plugin status', 'wporg-plugins' ); ?></span>
			</button>

			<div id="plugin-status-select" class="plugin-control-select hide-if-js">
				<input type="hidden" name="hidden_post_status" id="hidden-post-status" value="<?php echo esc_attr( $post->post_status ); ?>">
				<select name="post_status" id="plugin-status">
					<?php
					foreach ( $statuses as $statii ) {
						$status_object = get_post_status_object( $statii );
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $statii ),
							selected( $post->post_status, $statii, false ),
							esc_html( $status_object->label )
						);
					}
					?>
				</select>
				<button type="button" class="save-plugin-status hide-if-no-js button"><?php _e( 'OK', 'wporg-plugins' ); ?></button>
				<button type="button" class="cancel-plugin-status hide-if-no-js button-link"><?php _e( 'Cancel', 'wporg-plugins' ); ?></button>
			</div>

		</div><!-- .misc-pub-section --><?php
	}

	/**
	 * Displays the Tested Up To control in the Publish metabox.
	 */
	protected static function display_tested_up_to() {
		$post           = get_post();
		$tested_up_to   = (string) get_post_meta( $post->ID, 'tested', true );
		$versions       = self::get_tested_up_to_versions( $tested_up_to );
		$tested_up_to   = $versions['tested_up_to'];
		$unknown_string = _x( 'Unknown', 'unknown version', 'wporg-plugins' );
		?>
		<div class="misc-pub-section misc-pub-tested">
			<label for="tested_with"><?php _e( 'Tested With:', 'wporg-plugins' ); ?></label>
			<strong id="tested-with-display"><?php echo ( $tested_up_to ? sprintf( 'WordPress %s', $tested_up_to ) : $unknown_string ); ?></strong>
			<button type="button" class="button-link edit-tested-with hide-if-no-js">
				<span aria-hidden="true"><?php _e( 'Edit', 'wporg-plugins' ); ?></span>
				<span class="screen-reader-text"><?php _e( 'Edit tested with version', 'wporg-plugins' ); ?></span>
			</button>

			<div id="tested-with-select" class="plugin-control-select hide-if-js">
				<input type="hidden" name="hidden_tested_with" id="hidden-tested-with" value="<?php echo esc_attr( $tested_up_to ); ?>">
				<select name="tested_with" id="tested-with">
					<?php
					foreach ( $versions['versions'] as $ver ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $ver ),
							selected( $tested_up_to, $ver, true ),
							esc_html( $ver ? sprintf( 'WordPress %s', $ver ) : $unknown_string )
						);
					}
					?>
				</select>
				<button type="button" class="save-tested-with hide-if-no-js button"><?php _e( 'OK', 'wporg-plugins' ); ?></button>
				<button type="button" class="cancel-tested-with hide-if-no-js button-link"><?php _e( 'Cancel', 'wporg-plugins' ); ?></button>
			</div>

		</div><!-- .misc-pub-section --><?php
	}

	/**
	 * Fetch all versions which an author can set their plugin as tested with.
	 *
	 * This returns the latest release in the previous 4 branches, trunk, and
	 * the current version the plugin is marked as tested with.
	 *
	 * @param string $tested_up_to The version which the plugin is currently specified as compatible to.
	 *
	 * @return array An array containing 'versions' an array of versions for display, and 'tested_up_to'
	 *               the sanitized/most recent version of the $tested_up_to parameter.
	 */
	protected static function get_tested_up_to_versions( $tested_up_to ) {
		global $wp_version;
		// Fetch all "compatible" versions, this array is in the form of [ '4.4.2' => [ '4.4.1', '4.4' ], ...]
		if ( function_exists( 'wporg_get_version_equivalents' ) ) {
			// This function is a global WordPress.org function.
			$all_versions = wporg_get_version_equivalents();
		} else {
			$all_version = array( (string)(float)$wp_version => array( $wp_version ) );
		}

		$versions = array_slice( array_keys( $all_versions ), 0, 4 );
		// WordPress.org runs trunk, this keeps the highest version selectable as trunk
		$versions[5] = preg_replace( '!-\d{4,}$!', '', $wp_version );

		$found = false;
		foreach( $versions as $version ) {
			if ( isset( $all_versions[ $version ] ) && in_array( $tested_up_to, $all_versions[ $version ] ) ) {
				$tested_up_to = $version;
				$found = true;
				break;
			}
		}
		// If the version specified isn't going to display, insert it into the list.
		if ( ! $found ) {
			$versions[4] = $tested_up_to;
			ksort( $versions );
		}

		return compact( 'versions', 'tested_up_to' );
	}
}

