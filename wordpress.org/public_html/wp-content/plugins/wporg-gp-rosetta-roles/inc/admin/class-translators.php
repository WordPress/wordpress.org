<?php

namespace WordPressdotorg\GlotPress\Rosetta_Roles\Admin;

class Translators {

	const PAGE_SLUG = 'translators';

	/**
	 * @var string
	 */
	private $page_hook;

	public function register_page() {
		$this->page_hook = add_menu_page(
			__( 'Translators', 'wporg-translate' ),
			__( 'Translators', 'wporg-translate' ),
			'promote_users',
			self::PAGE_SLUG,
			[ $this, 'render_page' ],
			'dashicons-translation',
			71 // After Users
		);

		add_action( 'load-' . $this->page_hook, [ $this, 'load_page' ] );
		add_action( 'load-' . $this->page_hook, array( $this, 'register_screen_options' ) );
		add_filter( 'set-screen-option', array( $this, 'save_screen_options' ), 10, 3 );
	}

	/**
	 * Registers a 'per_page' screen option for the list table.
	 */
	public function register_screen_options() {
		$option = 'per_page';
		$args   = array(
			'default' => 10,
			'option'  => 'translators_per_page',
		);
		add_screen_option( $option, $args );
	}

	/**
	 * Adds the 'per_page' screen option to the whitelist so it gets saved.
	 *
	 * @param bool|int $new_value Screen option value. Default false to skip.
	 * @param string   $option    The option name.
	 * @param int      $value     The number of rows to use.
	 * @return bool|int New screen option value.
	 */
	public function save_screen_options( $new_value, $option, $value ) {
		if ( 'translators_per_page' !== $option ) {
			return $new_value;
		}

		$value = (int) $value;
		if ( $value < 1 || $value > 999 ) {
			return $new_value;
		}

		return $value;
	}

	/**
	 * Renders the admin page with a list table.
	 */
	public function render_page() {
		$list_table = $this->get_list_table();
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h2>
				<?php
				_e( 'Translators', 'wporg-translate' );

				if ( ! empty( $_REQUEST['s'] ) ) {
					echo '<span class="subtitle">' . sprintf( __( 'Search results for &#8220;%s&#8221;', 'wporg-translate' ), esc_html( wp_unslash( $_REQUEST['s'] ) ) ) . '</span>';
				}
				?>
			</h2>

			<form method="get">
				<input type="hidden" name="page" value="translators">
				<?php $list_table->search_box( __( 'Search Translators', 'wporg-translate' ), self::PAGE_SLUG ); ?>
			</form>

			<?php $list_table->views(); ?>

			<form method="post">
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handler for loading the page.
	 */
	public function load_page() {
		$list_table = $this->get_list_table();
		$current_action = $list_table->current_action();

		switch ( $current_action ) {
		}
	}

	/**
	 * Retrieves an instance of the list table.
	 *
	 * @return List_Table\Translators
	 */
	private function get_list_table() {
		static $list_table = null;

		if ( $list_table instanceof List_Table\Translators ) {
			return $list_table;
		}

		$list_table = new List_Table\Translators();

		return $list_table;
	}
}
