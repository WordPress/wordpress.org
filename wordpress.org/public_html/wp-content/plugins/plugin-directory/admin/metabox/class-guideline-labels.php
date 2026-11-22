<?php
/**
 * Guideline labels metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

use WordPressdotorg\Plugin_Directory\Guideline_Labels as Labels;

/**
 * Lets the Plugins Team label a plugin as not meeting specific guidelines.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Guideline_Labels {

	/**
	 * Displays the label controls.
	 *
	 * @param \WP_Post $post The plugin post.
	 */
	public static function display( \WP_Post $post ): void {
		$labels  = Labels::get( $post );
		$notes   = Labels::get_notes( $post );
		$version = (string) get_post_meta( $post->ID, 'version', true );

		wp_nonce_field( 'guideline-labels-' . $post->ID, 'guideline_labels_nonce' );

		echo '<p>' . esc_html__( 'Labeled plugins stay available, with a public notice naming each guideline they don’t meet.', 'wporg-plugins' ) . '</p>';

		foreach ( Labels::get_reasons() as $reason => $data ) {
			$label = $labels[ $reason ] ?? null;
			$field = 'guideline_labels[' . $reason . ']';
			$id    = 'guideline-label-' . $reason;
			?>
			<fieldset class="guideline-label">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $field ); ?>[enabled]" value="1" <?php checked( (bool) $label ); ?> />
					<?php
					printf(
						/* translators: 1: Guideline number. 2: Reason description. */
						esc_html__( 'Guideline %1$d: %2$s', 'wporg-plugins' ),
						(int) $data['guideline'],
						esc_html( $data['message'] )
					);
					?>
				</label>
				<?php
				if ( $label ) {
					$who = get_user_by( 'id', $label['labeled_by'] );
					printf(
						' <span class="description">%s</span>',
						esc_html(
							sprintf(
								/* translators: 1: Display name. 2: Date. */
								__( 'Added by %1$s on %2$s.', 'wporg-plugins' ),
								$who ? ( $who->display_name ?: $who->user_login ) : __( 'Unknown', 'wporg-plugins' ),
								wp_date( get_option( 'date_format' ), $label['labeled_at'] )
							)
						)
					);
				}
				?>
				<p>
					<label for="<?php echo esc_attr( $id ); ?>-since"><?php esc_html_e( 'Since version', 'wporg-plugins' ); ?></label>
					<input type="text" class="small-text" id="<?php echo esc_attr( $id ); ?>-since" name="<?php echo esc_attr( $field ); ?>[since_version]" value="<?php echo esc_attr( $label['since_version'] ?? $version ); ?>" />
				</p>
				<p>
					<label for="<?php echo esc_attr( $id ); ?>-note"><?php esc_html_e( 'Internal note (never shown publicly)', 'wporg-plugins' ); ?></label><br />
					<textarea class="large-text" rows="2" id="<?php echo esc_attr( $id ); ?>-note" name="<?php echo esc_attr( $field ); ?>[note]"><?php echo esc_textarea( $notes[ $reason ] ?? '' ); ?></textarea>
				</p>
			</fieldset>
			<?php
		}
		?>
		<style>
			#plugin-guideline-labels fieldset.guideline-label {
				border-top: 1px solid #f0f0f1;
				padding: 8px 0;
			}
			#plugin-guideline-labels fieldset.guideline-label p {
				margin: 4px 0 0 24px;
			}
			#plugin-guideline-labels fieldset.guideline-label:not(:has(input[type="checkbox"]:checked)) p {
				display: none;
			}
			#plugin-guideline-labels fieldset.guideline-label .description {
				vertical-align: middle;
			}
		</style>
		<?php
	}

	/**
	 * Saves the labels.
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 */
	public static function save_post( int|\WP_Post $post ): void {
		$post = get_post( $post );

		if (
			! $post ||
			'plugin' !== $post->post_type ||
			! isset( $_POST['guideline_labels_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['guideline_labels_nonce'] ) ), 'guideline-labels-' . $post->ID ) ||
			! current_user_can( 'plugin_label' )
		) {
			return;
		}

		// Sanitized per field by Labels::set().
		$submitted = isset( $_POST['guideline_labels'] ) && is_array( $_POST['guideline_labels'] ) ? wp_unslash( $_POST['guideline_labels'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$current   = Labels::get( $post );

		foreach ( array_keys( Labels::get_reasons() ) as $reason ) {
			$fields = isset( $submitted[ $reason ] ) && is_array( $submitted[ $reason ] ) ? $submitted[ $reason ] : array();

			if ( empty( $fields['enabled'] ) ) {
				if ( isset( $current[ $reason ] ) ) {
					Labels::remove( $post, $reason );
				}
				continue;
			}

			$args = array(
				'since_version' => is_string( $fields['since_version'] ?? null ) ? $fields['since_version'] : '',
				'note'          => is_string( $fields['note'] ?? null ) ? $fields['note'] : '',
			);

			Labels::set( $post, $reason, $args );
		}
	}
}
