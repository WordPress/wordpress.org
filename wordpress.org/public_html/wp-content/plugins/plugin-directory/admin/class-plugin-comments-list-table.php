<?php
namespace WordPressdotorg\Plugin_Directory\Admin;

_get_list_table( 'WP_Post_Comments_List_Table' );

/**
 * Comments list table for comments meta box.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin
 */
class Plugin_Comments_List_Table extends \WP_Post_Comments_List_Table {
	/**
	 * Comment type.
	 *
	 * @var string
	 */
	public $comment_type = '';

	/**
	 * Constructor.
	 *
	 * @see WP_List_Table::__construct() for more information on default arguments.
	 *
	 * @param array $args An associative array of arguments.
	 */
	public function __construct( $args = array() ) {
		if ( ! empty( $args['comment_type'] ) ) {
			$this->comment_type = $args['comment_type'];
		}
		parent::__construct( $args );
	}

	/**
	 *
	 * @param bool $output_empty
	 */
	public function display( $output_empty = false ) {
		$singular = $this->_args['singular'];

		wp_nonce_field( "fetch-list-" . get_class( $this ), '_ajax_fetch_list_nonce' );
		?>
		<table class="<?php echo implode( ' ', $this->get_table_classes() ); ?>" data-comment-type="<?php echo esc_attr( $this->comment_type ); ?>" style="display:none;">
			<colgroup>
				<col width="20%">
				<col width="80%">
			</colgroup>
			<tbody id="the-comment-list"<?php if ( $singular ) { echo " data-wp-lists='list:$singular'"; } ?>>
			<?php
				if ( ! $output_empty ) {
					$this->display_rows_or_placeholder();
				}
			?>
			</tbody>
		</table>
	<?php
	}
}
