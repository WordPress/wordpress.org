<?php
/**
 * Post handling customizations.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class Flagged {

	/**
	 * Initializer.
	 */
	public static function init() {
		// Register post statuses.
		add_action( 'init',                                  [ __CLASS__, 'register_post_statuses' ] );

		// Restrict access to the listing of flagged photos.
		add_action( 'current_screen',                        [ __CLASS__, 'restrict_photo_listing' ] );

		// Restrict access to edit a flagged post.
		add_action( 'current_screen',                        [ __CLASS__, 'restrict_photo_editing' ] );

		// Add 'Flagged' admin menu link under 'Photos'.
		add_action( 'admin_menu',                            [ __CLASS__, 'modify_admin_menu_links' ] );

		// Add post state indicator.
		add_filter( 'display_post_states',                   [ __CLASS__, 'display_post_states' ], 10, 2 );

		// Add count of user's flagged photos in author column.
		//     Note: Priority after Admin::add_published_photos_count_to_author()
		add_filter( 'the_author',                            [ __CLASS__, 'add_flagged_photos_count_to_author' ], 11 );

		// Add support to the post edit page.
		add_action( 'admin_footer-post.php',                 [ __CLASS__, 'output_js_for_post_edit_support' ] );

		// Add support to quick edit and bulk edit.
		add_filter( 'quick_edit_dropdown_pages_args',        [ __CLASS__, 'add_custom_status_to_quick_edit' ] );
		add_action( 'admin_footer',                          [ __CLASS__, 'add_flagged_quick_edit_javascript' ] );

		// Allow Photo metabox to be shown for flagged photos.
		add_filter( 'wporg_photos_post_statuses_with_photo', [ __CLASS__, 'amend_with_post_status' ] );
	}

	/**
	 * Returns the flagged post status.
	 *
	 * @return string
	 */
	public static function get_post_status() {
		return 'flagged';
	}

	/**
	 * Returns the capability required to manage flagged photos.
	 *
	 * @return string
	 */
	public static function get_capability() {
		return 'manage_flagged_photos';
	}

	/**
	 * Registers post statuses.
	 */
	public static function register_post_statuses() {
		$is_user_allowed = current_user_can( self::get_capability() );

		register_post_status( 'flagged', [
			'label'                     => _x( 'Flagged', 'photo status', 'wporg-photos' ),
			'label_count'               => _n_noop( 'Flagged <span class="count">(%s)</span>', 'Flagged <span class="count">(%s)</span>', 'wporg-photos' ),
			'protected'                 => true,
			'public'                    => false,
			'show_in_admin_all_list'    => $is_user_allowed,
			'show_in_admin_status_list' => $is_user_allowed,
		] );
	}

	/**
	 * Returns the number of total flagged photos.
	 *
	 * @return int The number of total flagged photos.
	 */
	public static function count() {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s AND post_status = %s",
			Registrations::get_post_type(),
			self::get_post_status()
		) );
	}

	/**
	 * Amends an array with the flagged post status.
	 *
	 * @param string[] $post_statuses Array of post statuses.
	 * @return string[]
	 */
	public static function amend_with_post_status( $post_statuses ) {
		$post_statuses[] = self::get_post_status();
		return $post_statuses;
	}

	/**
	 * Restrict access to the listing of flagged photos.
	 *
	 * @param WP_Screen $current_screen Current WP_Screen object.
	 */
	public static function restrict_photo_listing( $screen ) {
		// Bail if not in admin.
		if ( ! is_admin() ) {
			return;
		}

		// Bail if not listing photos.
		if ( 'edit-' . Registrations::get_post_type() !== $screen->id ) {
			return;
		}

		// Bail if post status is not explicitly requested.
		if ( empty( $_GET['post_status'] ) ) {
			return;
		}

		// Bail if post status isn't 'flagged'.
		if ( self::get_post_status() !== $_GET['post_status'] ) {
			return;
		}

		// Bail if user has capability.
		if ( current_user_can( self::get_capability() ) ) {
			return;
		}

		// Fall back to as if 'All' view was requested.
		$_GET['post_status'] = 'all';
	}

	/**
	 * Restricts access to edit a flagged post.
	 *
	 * @param WP_Screen $current_screen Current WP_Screen object.
	 */
	public static function restrict_photo_editing( $screen ) {
		$post_id = $_GET['post'] ?? null;
		if ( $post_id && $screen && Registrations::get_post_type() === $screen->id ) {
			$post = get_post( $post_id );
			if (
				$post
			&&
				// Post is a photo.
				Registrations::get_post_type() === get_post_type( $post )
			&&
				// Photo is flagged.
				self::get_post_status() === get_post_status( $post )
			&&
				// User can't manage flagged photos.
				! current_user_can( self::get_capability() )
			) {
				wp_die( __( 'Sorry, you are not allowed to edit this post.', 'wporg-photos' ) );
			}
		}
	}

	/**
	 * Adds 'Flagged' to admin menu for photos.
	 */
	public static function modify_admin_menu_links() {
		$path = 'edit.php?post_type=' . Registrations::get_post_type();

		$flagged_count = self::count();
		$count_indicator = $flagged_count
			? "<span class=\"update-plugins count-{$flagged_count}\"><span class=\"plugin-count\">{$flagged_count}</span></span>"
			: '';
 
		// Add 'Flagged' link if user can read flagged photos.
		add_submenu_page(
			$path,
			__( 'Flagged Photos', 'wporg-photos' ),
			/* translators: %s: Markup for the count indicator. */
			sprintf( __( 'Flagged %s', 'wporg-photos' ), $count_indicator ),
			self::get_capability(),
			esc_url( add_query_arg( [ 'post_status' => self::get_post_status() ], $path ) ),
			'',
			2
		);
	}

	/**
	 * Adds 'Flagged' post state indicator when appropriate.
	 */
	public static function display_post_states( $post_states, $post ) {
		if ( Registrations::get_post_type() === get_post_type( $post ) && self::get_post_status() === get_post_status( $post ) ) {
			$post_states[] = __( 'Flagged', 'wporg-photos' );
		}
		return $post_states;
	}

	/**
	 * Appends the count of the published photos to author names in photo post
	 * listings.
	 *
	 * @param string $display_name The author's display name.
	 * @return string
	 */
	public static function add_flagged_photos_count_to_author( $display_name ) {
		global $authordata;

		if ( ! is_admin() || ! Admin::should_include_photo_column() ) {
			return $display_name;
		}

		// Close link to contributor's listing of photos.
		$display_name .= '</a>';

		// Show number of flagged photos.
		$flagged_count = User::count_flagged_photos( $authordata->ID );
		$flagged_link = '';
		if ( $flagged_count ) {
			if ( current_user_can( self::get_capability() ) ) {
				$flagged_link = add_query_arg( [
					'post_type'   => Registrations::get_post_type(),
					'post_status' => self::get_post_status(),
					'author'      => $authordata->ID,
				], 'edit.php' );
			}
			$display_name .= '<div class="user-flagged-count">'
				. sprintf(
					/* translators: %s: Count of user's flagged photos possibly linked to listing of their flagged photos. */
					_n( 'Flagged: <strong>%s</strong>', 'Flagged: <strong>%s</strong>', $flagged_count, 'wporg-photos' ),
					$flagged_link ? sprintf( '<a href="%s">%d</a>', $flagged_link, $flagged_count ) : $flagged_count
				)
				. "</div>\n";
		}

		// Prevent unbalanced tag.
		$display_name .= '<a>';

		return $display_name;
	}

	/**
	 * Outputs JS to the post edit page of photos to support flagged photos.
	 */
	public static function output_js_for_post_edit_support() {
		$post = get_post();
		$complete = '';
		$label = '';
		if ( Registrations::get_post_type() === get_post_type( $post ) ) {
			$post_status = self::get_post_status();
			$is_flagged = get_post_status( $post ) === $post_status;
			$selected = $is_flagged ? 'selected' : '';
			$label_text = __( 'Flagged', 'wporg-photos' );
			$label = $is_flagged ? "<span id=\"post-status-display\">{$label_text}</span>" : '';
	
			echo "
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				const select = document.querySelector('select#post_status');
				const option = document.createElement('option');
				option.value = '{$post_status}';
				option.innerHTML = '{$label_text}';
				option.selected = '{$selected}' === 'selected';
				select.appendChild(option);
	
				const label = document.querySelector('.misc-pub-section label');
				label.innerHTML += '{$label}';

				if (option.selected) {
					document.getElementById('post-status-display').innerHTML = '{$label_text}';
				}
			});
			</script>
			";
		}
	}

	/**
	 * Adds custom post statuses to the Quick Edit panel.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function add_custom_status_to_quick_edit( $args ) {
		$args['include'] = array_merge( $args['include'], [ self::get_post_status() ] );
		return $args;
	}

	/**
	 * Outputs the JavaScript to allow assignment of flagged status via Quick Edit and Bulk Edit.
	 */
	public static function add_flagged_quick_edit_javascript() {
		$current_screen = get_current_screen();
		$post_type = Registrations::get_post_type();

		if ( ( "edit-{$post_type}" !== $current_screen->id ) || ( $post_type !== $current_screen->post_type ) ) {
			return;
		}
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				// Function to add the new status
				function addNewStatus(target) {
					const select = target.querySelector('select[name="_status"]');
					const post_status = '<?php echo self::get_post_status(); ?>';

					if (select) {
						const optionExists = Array.from(select.options).some(opt => opt.value === post_status);
						if (!optionExists) {
							const newOption = new Option( "<?php _e( 'Flagged', 'wporg-photos' ); ?>", post_status);
							select.add(newOption);
						}
					}
				}

				// Function to check if a new node is the Quick Edit or Bulk Edit form
				function checkNewNode(target) {
					if (target.classList.contains('inline-edit-<?php echo Registrations::get_post_type(); ?>') || target.id === 'bulk-edit') {
						addNewStatus(target);
					}
				}

				// MutationObserver setup
				const observer = new MutationObserver((mutations) => {
					mutations.forEach((mutation) => {
						if (mutation.addedNodes) {
							mutation.addedNodes.forEach(checkNewNode);
						}
					});
				});

				observer.observe(document.getElementById('the-list'), { childList: true, subtree: true });
			}, false);
		</script>
		<?php
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Flagged', 'init' ] );
