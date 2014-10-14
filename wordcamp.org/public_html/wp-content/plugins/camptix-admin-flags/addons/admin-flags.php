<?php
/**
 * Admin Flags Addon for CampTix
 *
 * With this addon you can setup simple flags for CampTix attendees
 * and allow admins to toggle such flags through the admin UI. Flags
 * are not visible to attendees but can be seen/filtered in exports.
 *
 * Use case: flag everyone attending a closed party/event.
 */
class CampTix_Admin_Flags_Addon extends CampTix_Addon {

	public function __construct() {
		add_action( 'camptix_init', array( $this, 'camptix_init' ) );
	}

	/**
	 * Runs during camptix_init.
	 */
	public function camptix_init() {
		global $camptix;

		// Admin Settings UI.
		if ( current_user_can( $camptix->caps['manage_options'] ) ) {
			add_filter( 'camptix_setup_sections', array( $this, 'setup_sections' ) );
			add_action( 'camptix_menu_setup_controls', array( $this, 'setup_controls' ), 10, 1 );
			add_filter( 'camptix_validate_options', array( $this, 'validate_options' ), 10, 2 );
		}

		$this->flags = array();
		$camptix_options = $camptix->get_options();

		// No further actions if we don't have any configured flags.
		if ( empty( $camptix_options['camptix-admin-flags-data-parsed'] ) )
			return;

		$this->flags = (array) $camptix_options['camptix-admin-flags-data-parsed'];

		if ( current_user_can( $camptix->caps['manage_attendees'] ) ) {
			// Individual editing from Edit Attendee screen
			add_action( 'save_post', array( $this, 'save_post' ), 11, 1 );
			add_action( 'camptix_attendee_submitdiv_misc', array( $this, 'publish_metabox_actions' ) );

			// Bulk editing from Attendees screen
			add_filter( 'manage_tix_attendee_posts_columns', array( $this, 'add_custom_columns' ) );
			add_action( 'manage_tix_attendee_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
			add_action( 'admin_footer-edit.php', array( $this, 'render_client_side_templates' ) );
			add_action( 'wp_ajax_tix_admin_flag_toggle', array( $this, 'toggle_flag' ) );
			add_action( 'admin_footer-edit.php', array( $this, 'print_javascript' ) );
			add_action( 'admin_print_styles-edit.php', array( $this, 'print_css' ) );
		}

		// Export Handlers.
		add_filter( 'camptix_attendee_report_extra_columns', array( $this, 'export_columns' ) );
		add_filter( 'camptix_attendee_report_column_value', array( $this, 'export_columns_values' ), 10, 3 );

		// Attendees list shortcode handlers.
		add_filter( 'shortcode_atts_camptix_attendees', array( $this, 'shortcode_attendees_atts' ), 10, 3 );
		add_filter( 'camptix_attendees_shortcode_query_args', array( $this, 'shortcode_attendees_query' ), 10, 2 );
	}

	/**
	 * Allows a has_admin_flag attribute for the [camptix_attendees] shortcode.
	 */
	public function shortcode_attendees_atts( $out, $pairs, $atts ) {
		$admin_flags = array();
		if ( ! empty( $atts['has_admin_flag'] ) )
			$admin_flags = array_map( 'trim', explode( ',', $atts['has_admin_flag'] ) );

		$admin_flags_clean = array();
		foreach ( $this->flags as $key => $label )
			if ( in_array( $key, $admin_flags ) )
				$admin_flags_clean[] = $key;

		$out['has_admin_flag'] = $admin_flags;
		return $out;
	}

	/**
	 * Modify the attendees list shortcode query based on has_admin_flag.
	 */
	public function shortcode_attendees_query( $query_args, $shortcode_args ) {
		if ( empty( $shortcode_args['has_admin_flag'] ) )
			return $query_args;

		// Sanitized in self::shortcode_attendees_atts.
		$flags = $shortcode_args['has_admin_flag'];

		if ( empty( $query_args['meta_query'] ) )
			$query_args['meta_query'] = array();

		foreach ( $flags as $flag ) {
			$query_args['meta_query'][] = array(
				'key' => 'camptix-admin-flag',
				'value' => $flag,
				'compare' => '=',
				'type' => 'CHAR',
			);
		}

		return $query_args;
	}

	/**
	 * Configure Export Columns.
	 */
	public function export_columns( $columns ) {
		foreach ( $this->flags as $key => $label ) {
			$column_key = sprintf( 'camptix-admin-flags-%s', $key );
			$columns[ $column_key ] = $label;
		}

		return $columns;
	}

	/**
	 * Export Column Values
	 *
	 * Prints "Yes" if the flag is configured and set for an
	 * attendee. Prints "No" if the flag is configured but not set.
	 */
	public function export_columns_values( $value, $index, $attendee ) {
		if ( 0 !== strpos( $index, 'camptix-admin-flags-' ) )
			return $value;

		// See self::export_columns() for key format.
		$key = str_replace( 'camptix-admin-flags-', '', $index );
		if ( ! array_key_exists( $key, $this->flags ) )
			return $value;

		$attendee_flags = (array) get_post_meta( $attendee->ID, 'camptix-admin-flag' );
		return in_array( $key, $attendee_flags ) ? 'Yes' : 'No';
	}

	/**
	 * Add a new section to the Setup screen.
	 */
	public function setup_sections( $sections ) {
		$sections['admin-flags'] = __( 'Admin Flags', 'camptix' );
		return $sections;
	}

	/**
	 * Add some controls to our Setup section.
	 */
	public function setup_controls( $section ) {
		global $camptix;

		if ( 'admin-flags' != $section )
			return;

		add_settings_section( 'general', __( 'Admin Flags', 'camptix' ), array( $this, 'setup_controls_section' ), 'camptix_options' );
		$camptix->add_settings_field_helper( 'camptix-admin-flags-data', __( 'Admin Flags Data', 'camptix' ), 'field_textarea' );
	}

	/**
	 * Setup section description.
	 */
	public function setup_controls_section() {
		?>
		<p>The Admin Flags addon for CampTix allows you to define a list of special flags that can be toggled for every attendee through the admin UI. Flags are not visible to attendees but can be seen and filtered in exports.</p>

		<p><strong>Flags Data Format</strong>: One flag per line, each line in the format of <code>flag-slug: Flag label</code></p>
		<?php
	}

	/**
	 * Runs whenever the CampTix option is updated.
	 */
	public function validate_options( $output, $input ) {
		if ( ! isset( $input['camptix-admin-flags-data'] ) )
			return $output;

		$has_error = false;
		$flags = array();
		$data = explode( "\n", $input['camptix-admin-flags-data'] );

		foreach ( $data as $line ) {

			if ( empty( $line ) )
				continue;

			// flag-key: Flag Label
			if ( ! preg_match( '#^([^:]+?):(.+)$#', $line, $matches ) ) {
				$has_error = true;
				continue;
			}

			$key = sanitize_html_class( sanitize_title_with_dashes( trim( $matches[1] ) ) );
			$label = trim( $matches[2] );
			$flags[ $key ] = $label;
		}

		$lines = array();
		foreach ( $flags as $key => $label )
			$lines[] = sprintf( '%s: %s', $key, $label );

		$output['camptix-admin-flags-data'] = implode( "\n", $lines );
		$output['camptix-admin-flags-data-parsed'] = $flags;

		if ( $has_error )
			add_settings_error( 'tix', 'error', __( 'Flags data has been saved, but one or more flags was invalid, so it has been stripped.', 'camptix' ), 'error' );

		return $output;
	}

	/**
	 * Runs during the generic save_post.
	 */
	public function save_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || 'tix_attendee' != get_post_type( $post_id ) )
			return;

		if ( empty( $_POST['camptix-admin-flags-nonce'] ) || ! wp_verify_nonce( $_POST['camptix-admin-flags-nonce'], 'camptix-admin-flags-update' ) )
			return;

		delete_post_meta( $post_id, 'camptix-admin-flag' );

		foreach ( $this->flags as $key => $label )
			if ( ! empty( $_POST['camptix-admin-flags'][ $key ] ) )
				add_post_meta( $post_id, 'camptix-admin-flag', $key );
	}

	/**
	 * Adds to the CampTix additional metabox actions.
	 */
	public function publish_metabox_actions() {
		$post = get_post();
		$attendee_flags = (array) get_post_meta( $post->ID, 'camptix-admin-flag' );
		?>

		<?php wp_nonce_field( 'camptix-admin-flags-update', 'camptix-admin-flags-nonce' ); ?>
		<div class="camptix-admin-flags">
			<?php foreach ( $this->flags as $key => $label ) : ?>

				<div class="tix-pub-section-item">
					<input id="camptix-admin-flags-<?php echo sanitize_html_class( $key ); ?>" name="camptix-admin-flags[<?php echo esc_attr( $key ); ?>]" type="checkbox" <?php checked( in_array( $key, $attendee_flags ) ); ?> value="1" />
					<label for="camptix-admin-flags-<?php echo sanitize_html_class( $key ); ?>"><?php echo esc_html( $label ); ?></label>
				</div>

			<?php endforeach; ?>
		</div>

		<?php
	}

	/**
	 * Add extra columns to the Attendees screen.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function add_custom_columns( $columns ) {
		$columns = array_merge( array( 'admin-flags' => __( 'Admin Flags', 'camptix' ) ), $columns );

		return $columns;
	}

	/**
	 * Render custom columns on the Attendees screen.
	 *
	 * @param string $column
	 * @param int $attendee_id
	 */
	public function render_custom_columns( $column, $attendee_id ) {
		$attendee_flags = (array) get_post_meta( $attendee_id, 'camptix-admin-flag' );

		switch ( $column ) {
			case 'admin-flags':
				echo '<ul>';
					foreach ( $this->flags as $key => $label ) {
						$enabled = in_array( $key, $attendee_flags );

						?>

						<li>
							<span class="tix-admin-flag-label"><?php echo esc_html( $label ); ?></span>:

							<a
								href="#"
								data-attendee-id="<?php echo esc_attr( $attendee_id ); ?>"
								data-key="<?php echo esc_attr( $key ); ?>"
								data-nonce="<?php echo esc_attr( wp_create_nonce( sprintf( 'tix_toggle_flag_%s_%s', $attendee_id, $key ) ) ); ?>"
								data-command="<?php echo esc_attr( $enabled ? 'disable' : 'enable' ); ?>"
								class="tix-toggle-flag">

								<?php if ( $enabled ) : ?>
									<?php _e( 'Disable', 'camptix' ); ?>
								<?php else : ?>
									<?php _e( 'Enable', 'camptix' ); ?>
								<?php endif; ?>
							</a>
						</li>

						<?php
					}
				echo '</ul>';

				break;
		}
	}

	/**
	 * Render the templates used by JavaScript
	 */
	public function render_client_side_templates() {
		if ( 'tix_attendee' != $GLOBALS['typenow'] ) {
			return;
		}

		?>

		<script type="text/html" id="tmpl-tix-admin-flags-spinner">
			<div class="spinner"></div>
		</script>

		<script type="text/html" id="tmpl-tix-admin-flag-toggle">
			<span class="tix-admin-flag-label">{{data.label}}</span>:

			<a
				href="#"
				data-attendee-id="{{data.attendee_id}}"
				data-key="{{data.key}}"
				data-nonce="{{data.nonce}}"
				data-command="{{data.command}}"
				class="tix-toggle-flag">

				{{data.command}}    <?php // todo use i18n var ?>
			</a>
		</script>

		<?php
	}

	/**
	 * AJAX handler to toggle a flag from the Attendees screen.
	 */
	public function toggle_flag() {
		/** @var $camptix CampTix_Plugin */
		global $camptix;

		if ( empty( $_REQUEST['action'] ) || empty( $_REQUEST['attendee_id'] ) || empty( $_REQUEST['key'] ) || empty( $_REQUEST['command'] ) || empty( $_REQUEST['nonce'] ) ) {
			wp_send_json_error( array( 'error' => 'Required parameters not set.' ) );
		}

		$attendee_id = absint( $_REQUEST['attendee_id'] );
		$key         = sanitize_text_field( $_REQUEST['key'] );
		$command     = sanitize_text_field( $_REQUEST['command'] );

		if ( ! wp_verify_nonce( $_REQUEST['nonce'], sprintf( 'tix_toggle_flag_%s_%s', $attendee_id, $key ) ) || ! current_user_can( $camptix->caps['manage_attendees'], $attendee_id ) ) {
			wp_send_json_error( array( 'error' => 'Permission denied.' ) );
		}

		$attendee = get_post( $attendee_id );

		if ( ! is_a( $attendee, 'WP_Post' ) || 'tix_attendee' != $attendee->post_type ) {
			wp_send_json_error( array( 'error' => 'Invalid attendee.' ) );
		}

		if ( 'enable' == $command ) {
			add_post_meta( $attendee_id, 'camptix-admin-flag', $key );
		} elseif ( 'disable' == $command ) {
			delete_post_meta( $attendee_id, 'camptix-admin-flag', $key );
		}

		wp_send_json_success();
	}

	/**
	 * Print our JavaScript
	 */
	public function print_javascript() {
		if ( 'tix_attendee' != $GLOBALS['typenow'] ) {
			return;
		}

		?>

		<!-- camptix-admin-flags -->
		<script type="text/javascript">
			// Bulk toggling of flags
			( function( $ ) {
				$( '#posts-filter' ).on( 'click', 'a.tix-toggle-flag', function( event ) {
					var itemTemplate,
						item       = $( this ).parent(),
						attendeeID = $( this ).data( 'attendee-id' ),
						key        = $( this ).data( 'key' ),
						command    = $( this ).data( 'command' ),
						label      = $( item ).find( '.tix-admin-flag-label' ).text(),
						nonce      = $( this ).data( 'nonce' );

					event.preventDefault();

					// Show a spinner until the AJAX call is done
					itemTemplate = _.template( $( '#tmpl-tix-admin-flags-spinner' ).html(), null, camptix.template_options );
					item.html( itemTemplate( {} ) );

					// Send the request to toggle the flag
					$.post(
						ajaxurl,

						{
							action:      'tix_admin_flag_toggle',
							attendee_id: attendeeID,
							key:         key,
							command:     command,
							nonce:       nonce
						},

						function( response ) {
							if ( response.hasOwnProperty( 'success' ) && true === response.success ) {
								if ( 'enable' == command ) {
									command = 'disable';
								} else if ( 'disable' == command ) {
									command = 'enable';
								}
							}

							itemTemplate = _.template( $( '#tmpl-tix-admin-flag-toggle' ).html(), null, camptix.template_options );
							item.html( itemTemplate( {
								attendee_id: attendeeID,
								key:         key,
								command:     command,
								label:       label,
								nonce:       nonce
							} ) );
						}
					);
				} );
			} ( jQuery ) );
		</script>
		<!-- end camptix-admin-flags -->

		<?php
	}

	/**
	 * Print our CSS
	 */
	public function print_css() {
		if ( 'tix_attendee' != $GLOBALS['typenow'] ) {
			return;
		}

		?>

		<!-- camptix-admin-flags -->
		<style type="text/css">
			body.post-type-tix_attendee table.posts td.admin-flags div.spinner {
				display: block;
				float: none;
				margin-left: auto;
				margin-right: auto;
			}
		</style>
		<!-- end camptix-admin-flags -->

		<?php
	}

	/**
	 * Register self with CampTix.
	 */
	public static function register_addon() {
		camptix_register_addon( __CLASS__ );
	}
}

CampTix_Admin_Flags_Addon::register_addon();
