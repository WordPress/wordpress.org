<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * Manage the persistent author notice for the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Author_Notice {

	const DEFAULT_TEXT  = '<p>This is a message that will be displayed on the top of the plugins page to plugin authors, even if the plugin is closed. To edit, simply click and type.</p>';
	const POST_META_KEY = '_author_notice';

	/**
	 * The HTML allowed in the author notice.
	 */
	const ALLOWED_TAGS = [
		'p'      => true,
		'strong' => true,
		'em'     => true,
		'a'      => [
			'href' => true,
		],
		'i'      => true,
		'b'	     => true,
		'br'     => true,
		'code'   => true,
		'pre'    => true,
		'ul'     => true,
		'ol'     => true,
		'li'     => true,
	];

	/**
	 * Displays the author notice controls.
	 */
	public static function display() {
		$post   = get_post();
		$notice = get_post_meta( $post->ID, self::POST_META_KEY, true ) ?: [ 'type' => '', 'html' => '', 'when' => 0, 'who' => 0 ];

		if ( ! $notice['html'] ) {
			$notice['html'] = self::DEFAULT_TEXT;
		}

		?>
		<select name="author_notice[type]" id="author-notice-select">
			<option value="" <?php selected( '', $notice['type'] ); ?>><?php esc_html_e( 'Do not show', 'wporg-plugins' ); ?></option>
			<optgroup label="<?php esc_attr_e( 'Notice types', 'wporg-plugins' ); ?>">
				<option value="info" <?php selected( 'info', $notice['type'] ); ?>><?php esc_html_e( 'Info / Notice', 'wporg-plugins' ); ?></option>
				<option value="warning" <?php selected( 'warning', $notice['type'] ); ?>><?php esc_html_e( 'Warning', 'wporg-plugins' ); ?></option>
				<option value="error" <?php selected( 'error', $notice['type'] ); ?>><?php esc_html_e( 'Error', 'wporg-plugins' ); ?></option>
			</optgroup>
		</select>
		<?php
		if ( $notice['when'] ) {
			$who = get_user_by( 'id', $notice['who'] );
			printf(
				'<span>' . __( 'Last edited by %s <time title="%s">%s ago</a>', 'wporg-plugins' ) . '</span>',
				$who->display_name ?: $who->user_login,
				gmdate( 'Y-m-d H:i:s', $notice['when'] ),
				human_time_diff( $notice['when'] )
			);
		}
		?>

		<div id="author-notice-texteditable" class="inline notice notice-alt notice-<?php echo esc_attr( $notice['type'] ); ?>" contentEditable="true">
			<?php echo wp_kses( $notice['html'], self::ALLOWED_TAGS ); ?>
		</div>

		<input type="hidden" name="author_notice[html]" id="author-notice" value="<?php echo esc_attr( $notice['html'] ); ?>" />

		<script>
			jQuery( function( $ ) {
				$( '#author-notice-select' ).on( 'change', function() {
					var $notice = $( '#author-notice-texteditable' );
					$notice.removeClass( 'notice- notice-info notice-warning notice-error' );
					$notice.addClass( 'notice-' + $( this ).val() );
				} );

				$( '#author-notice-texteditable' ).on( 'input change', function() {
					// Don't allow deleting the <p> tag.
					if ( ! $( this ).children( 'p,div,ul,ol,pre,code' ).length ) {
						var text = $(this).html();
						$( this ).html('<p/>').find('p').html(text);
					}

					// Update the hidden input value with the HTML.
					$( '#author-notice' ).val( $( this ).html() );
				} );
			} );
		</script>
		<style>
			#author-notice-texteditable.notice-:not(:focus) {
				opacity: 0.6;
			}
		</style>
		<?php
	}

	/**
	 * Saves the author notice.
	 *
	 * @param int|WP_Post $post Post ID or post object.
	 */
	static function save_post( $post ) {
		$post          = get_post( $post );

		if (
			$post &&
			isset( $_REQUEST['author_notice'] ) &&
			is_array( $_REQUEST['author_notice'] ) &&
			current_user_can( 'plugin_admin_edit', $post->ID )
		) {
			$new_author_notice = wp_unslash( $_REQUEST['author_notice'] );
			self::set( $post, $new_author_notice['html'], $new_author_notice['type'] );
		}
	}

	/**
	 * Set the Author notice.
	 */
	static function set( $post, $notice, $type = 'error' ) {
		$post_meta         = get_post_meta( $post->ID, self::POST_META_KEY, true );
		$author_notice     = $post_meta ?: [ 'type' => '', 'html' => '' ];
		$new_author_notice = [
			'type' => sanitize_key( $type ),
			'html' => wp_kses( trim( $notice ), self::ALLOWED_TAGS ),
		];

		// Check it's not empty with tags removed.
		if (
			$new_author_notice['html'] &&
			! trim( wp_strip_all_tags( $new_author_notice['html'] ) )
		) {
			$new_author_notice['html'] = '';
		}

		// Default or no text, remove the notice.
		if ( empty( $new_author_notice['html'] ) || self::DEFAULT_TEXT === $new_author_notice['html'] ) {
			$new_author_notice['type'] = '';
			$new_author_notice['html'] = '';
		}

		// Remove it.
		if ( $post_meta && ! $new_author_notice['type'] && ! $new_author_notice['html'] ) {
			delete_post_meta( $post->ID, self::POST_META_KEY );
			Tools::audit_log( 'Author notice removed.' );
			return;
		}

		// Value changed?
		if (
			$author_notice['html'] != $new_author_notice['html'] ||
			$author_notice['type'] != $new_author_notice['type']
		) {
			$author_notice = [
				'type' => $new_author_notice['type'],
				'html' => $new_author_notice['html'],
				'when' => time(),
				'who'  => get_current_user_id(),
			];

			update_post_meta( $post->ID, self::POST_META_KEY, $author_notice );

			$type_text = $author_notice['type'] ?: __( 'Do not show', 'wporg-plugins' );
			Tools::audit_log(
				sprintf(
					'Author notice set to: [%s] %s',
					$type_text,
					wp_strip_all_tags( $author_notice['html'] )
				),
				$post
			);
		}
	}
}
