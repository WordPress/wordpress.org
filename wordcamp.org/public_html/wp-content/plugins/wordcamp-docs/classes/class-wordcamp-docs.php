<?php
class WordCamp_Docs {
	private static $templates = null;
	private static $errors = array();
	private static $step = 0;

	/**
	 * Runs at plugins_loaded
	 */
	public static function load() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'form_handler' ) );

		add_filter( 'wcdocs_templates', array( __CLASS__, 'default_templates' ) );
	}

	/**
	 * To load additional templates just hook into wcdocs_template
	 * some time around plugins_loaded and add your own objects that
	 * implement the WordCamp_Docs_Template class.
	 *
	 * @return array An array of template objects.
	 */
	private static function get_templates() {
		if ( self::$templates === null )
			self::$templates = apply_filters( 'wcdocs_templates', array() );

		return self::$templates;
	}

	/**
	 *
	 * Get template by key.
	 *
	 * @param string $key Template key.
	 * @return object|null The template object or null if not found.
	 */
	private static function get_template( $key ) {
		$templates = self::get_templates();
		if ( empty( $templates[ $key ] ) )
			return null;

		return $templates[ $key ];
	}

	/**
	 * Load default templates.
	 *
	 * @param array $templates An array of available templates.
	 * @return array Same array of templates with new ones added.
	 */
	public static function default_templates( $templates ) {
		require_once( WORDCAMP_DOCS__PLUGIN_DIR . 'templates/sponsorship-agreement.php' );
		require_once( WORDCAMP_DOCS__PLUGIN_DIR . 'templates/speaker-visa.php' );
		require_once( WORDCAMP_DOCS__PLUGIN_DIR . 'templates/attendee-visa.php' );

		$templates['sponsorship-agreement'] = new WordCamp_Docs_Template_Sponsorship_Agreement;
		$templates['speaker-visa'] = new WordCamp_Docs_Template_Speaker_Visa;
		$templates['attendee-visa'] = new WordCamp_Docs_Template_Attendee_Visa;

		return $templates;
	}

	/**
	 * Add a menu item.
	 */
	public static function admin_menu() {
		add_menu_page( __( 'WordCamp Docs', 'wordcamporg' ), __( 'Docs', 'wordcamporg' ), 'manage_options', 'wcdocs', array( __CLASS__, 'render_menu_page' ), 'dashicons-portfolio', 58 );
	}

	/**
	 * Our main form handler, runs at admin_init.
	 */
	public static function form_handler() {
		if ( empty( $_POST['wcdocs_submit'] ) )
			return;

		if ( empty( $_POST['_wpnonce'] ) )
			return self::error( __( 'Empty nonce', 'wordcamporg' ) );

		$nonce = $_POST['_wpnonce'];
		$step = absint( $_POST['wcdocs_submit'] );

		if ( ! wp_verify_nonce( $nonce, 'wcdocs_step_' . $step ) )
			return self::error( __( 'Invalid nonce', 'wordcamporg' ) );

		// Check selected template on any step.
		$templates = self::get_templates();
		$template = sanitize_text_field( $_POST['wcdocs_template'] );
		if ( ! array_key_exists( $template, $templates ) )
			return self::error( __( 'Selected template does not exist', 'wordcamporg' ) );

		$template = $templates[ $template ];

		switch ( $step ) {
			case 1: // submitted step 1

				// Nothing else to check on this step.
				self::$step = 2;
				break;

			case 2: // submitted step 2

				require_once( WORDCAMP_DOCS__PLUGIN_DIR . 'classes/class-wordcamp-docs-pdf-generator.php' );
				$generator = new WordCamp_Docs_PDF_Generator;

				// Sanitize input
				$data = $template->sanitize( $_POST );
				$generator->generate_pdf_from_string(
					$template->render( $data ),
					sanitize_file_name( $template->get_filename() ),
					array(
						'assets' => $template->get_assets(),
						'margins' => array( 10, 10, 10, 10 ),
					) );

				$generator->serve_pdf_to_browser( $template->get_filename(), true );
				$generator->delete_tmp_folder();
				die();
				break;

		}
	}

	/**
	 * Append an error message to self::$errors
	 *
	 * @param string $message Error message contents.
	 */
	private static function error( $message ) {
		self::$errors[] = $message;
	}

	/**
	 * Render the contents of our admin section.
	 */
	public static function render_menu_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'WordCamp Docs', 'wordcamporg' ); ?></h1>

			<?php if ( ! empty( self::$errors ) ) : ?>
				<?php foreach ( self::$errors as $error ) : ?>
					<div class="error">
						<p><?php echo esc_html( $error ); ?></p>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if ( self::$step <= 1 ) : ?>

				<p><?php _e( 'This tool will help you generate various documents and forms for your WordCamp.', 'wordcamporg' ); ?></p>

				<form method="POST">
					<input type="hidden" name="wcdocs_submit" value="1" />
					<?php wp_nonce_field( 'wcdocs_step_1' ); ?>

					<p><?php _e( 'Pick a template to get started:', 'wordcamporg' ); ?></p>
					<select name="wcdocs_template">
						<?php foreach ( self::get_templates() as $key => $template ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $template->get_name() ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="submit">
						<input type="submit" class="button-primary" value="Next &rarr;">
					</p>
				</form>

			<?php elseif ( self::$step == 2 ) : ?>

				<form method="POST">
					<input type="hidden" name="wcdocs_submit" value="2" />
					<input type="hidden" name="wcdocs_template" value="<?php echo esc_attr( $_POST['wcdocs_template'] ); ?>">
					<?php wp_nonce_field( 'wcdocs_step_2' ); ?>

					<?php
						$template = self::get_template( $_POST['wcdocs_template'] );
						$template->form( $_POST );
					?>

					<p class="submit">
						<input type="submit" class="button-primary" value="<?php esc_attr_e( 'Download PDF', 'wordcamporg' ); ?>">
					</p>
				</form>

			<?php endif; ?>
		</div>
		<?php
	}

	private function __construct() {} // Not this time.
}

add_action( 'plugins_loaded', array( 'WordCamp_Docs', 'load' ) );
