<?php
namespace WordPressdotorg\Plugin_Directory\Admin\List_Table;

use \WordPressdotorg\Plugin_Directory\Tools;
use \WordPressdotorg\Plugin_Directory\Template;

_get_list_table( 'WP_Posts_List_Table' );

class Plugin_Posts extends \WP_Posts_List_Table {

	protected $column_order = [
		'cb',
		'title',
		'author',
		'reviewer',
		'zip',
		'loc',
		'comments',
	];

	/**
	 * Engage the filters.
	 */
	public function __construct() {
		parent::__construct();

		add_filter( "manage_{$this->screen->post_type}_posts_columns", [ $this, 'filter_columns' ], 100 );
		add_filter( "manage_{$this->screen->id}_sortable_columns", [ $this, 'filter_sortable_columns' ], 100 );
		add_filter( 'hidden_columns', [ $this, 'filter_hidden_columns' ], 100, 3 );
	}

	/**
	 * Add the custom columns and set the order.
	 */
	public function filter_columns( $columns ) {
		// Rename some columns.
		$columns['author']         = __( 'Submitter', 'wporg-plugins' );
		$columns['reviewer']       = __( 'Assigned Reviewer', 'wporg-plugins' );
		$columns['comments']       = '<span class = "vers comment-grey-bubble" title = "' . esc_attr__( 'Internal Notes', 'wporg-plugins' ) . '"><span class = "screen-reader-text">' . __( 'Internal Notes', 'wporg-plugins' ) . '</span></span>';
		$columns['zip']            = 'Latest Zip';
		$columns['loc']            = 'Lines of PHP Code'; 
		$columns['submitted_date'] = 'Submitted Date'; 

		// We don't want the stats column.
		unset( $columns['stats'] );

		$columns = array_merge( array_flip( $this->column_order ), $columns );

		return $columns;
	}

	/**
	 * The sortable columns.
	 */
	public function filter_sortable_columns( $columns ) {
		$columns[ 'reviewer' ]       = [ 'assigned_reviewer_time', 'asc' ];
		$columns[ 'zip' ]            = [ '_submitted_zip_size', 'asc' ];
		$columns[ 'loc' ]            = [ '_submitted_zip_loc', 'asc' ];
		$columns[ 'submitted_date' ] = [ '_submitted_date', 'asc' ];

		return $columns;
	}

	/**
	 * Hide some fields by default.
	 */
	public function filter_hidden_columns( $columns, $screen, $use_defaults ) {
		if ( $screen->id !== $this->screen->id ) {
			return $columns;
		}

		// Hide certain columns on default / published views.
		if (
			in_array( $_REQUEST['post_status'] ?? 'all', [ 'all', 'publish', 'disabled', 'closed' ] ) &&
			empty( $_REQUEST['author'] ) &&
			empty( $_REQUEST['reviewer'] )
		) {
			$columns[] = 'reviewer';
			$columns[] = 'zip';
			$columns[] = 'loc';
			$columns[] = 'submitted_date';
		}

		return $columns;
	}

	/**
	 *
	 * @global array     $avail_post_stati
	 * @global \WP_Query $wp_query
	 * @global int       $per_page
	 * @global string    $mode
	 */
	public function prepare_items() {
		global $avail_post_stati, $wp_query, $per_page, $mode;

		$this->set_hierarchical_display( is_post_type_hierarchical( $this->screen->post_type ) && 'menu_order title' === $wp_query->query['orderby'] );

		$post_type = $this->screen->post_type;
		$per_page  = $this->get_items_per_page( 'edit_' . $post_type . '_per_page' );

		/** This filter is documented in wp-admin/includes/post.php */
		$per_page = apply_filters( 'edit_posts_per_page', $per_page, $post_type );

		if ( $this->hierarchical_display ) {
			$total_items = $wp_query->post_count;
		} elseif ( $wp_query->found_posts || $this->get_pagenum() === 1 ) {
			$total_items = $wp_query->found_posts;
		} else {
			$post_counts = (array) wp_count_posts( $post_type, 'readable' );

			if ( isset( $_REQUEST['post_status'] ) && in_array( $_REQUEST['post_status'], $avail_post_stati ) ) {
				$total_items = $post_counts[ $_REQUEST['post_status'] ];
			} elseif ( isset( $_REQUEST['show_sticky'] ) && $_REQUEST['show_sticky'] ) {
				$total_items = $this->sticky_posts_count;
			} elseif ( isset( $_GET['author'] ) && $_GET['author'] == get_current_user_id() ) {
				$total_items = $this->user_posts_count;
			} else {
				$total_items = array_sum( $post_counts );

				// Subtract post types that are not included in the admin all list.
				foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
					$total_items -= $post_counts[ $state ];
				}
			}
		}

		if ( ! empty( $_REQUEST['mode'] ) ) {
			$mode = $_REQUEST['mode'] === 'excerpt' ? 'excerpt' : 'list';
			set_user_setting( 'posts_list_mode', $mode );
		} else {
			$mode = get_user_setting( 'posts_list_mode', 'list' );
		}

		$this->is_trash = isset( $_REQUEST['post_status'] ) && $_REQUEST['post_status'] === 'trash';

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
		) );
	}

	/**
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions       = array();
		$post_type_obj = get_post_type_object( $this->screen->post_type );

		if ( current_user_can( $post_type_obj->cap->edit_posts ) ) {
			if ( $this->is_trash ) {
				$actions['untrash'] = __( 'Restore', 'wporg-plugins' );
			}
		}

		if ( current_user_can( 'plugin_reject' ) && ( empty( $_REQUEST['post_status'] ) || in_array( $_REQUEST['post_status'], array( 'new', 'pending' ) ) ) ) {
			$actions['plugin_reject'] = __( 'Reject', 'wporg-plugins' );
		}

		if ( current_user_can( 'plugin_approve' ) && ( empty( $_REQUEST['post_status'] ) || in_array( $_REQUEST['post_status'], array( 'closed', 'disabled' ) ) ) ) {
			$actions['plugin_open'] = __( 'Open', 'wporg-plugins' );
		}

		if ( current_user_can( 'plugin_close' ) && ( empty( $_REQUEST['post_status'] ) || in_array( $_REQUEST['post_status'], array( 'publish', 'approved', 'disabled' ) ) ) ) {
			$actions['plugin_close'] = __( 'Close', 'wporg-plugins' );
		}

		if ( current_user_can( 'plugin_close' ) && ( empty( $_REQUEST['post_status'] ) || in_array( $_REQUEST['post_status'], array( 'publish', 'approved', 'closed' ) ) ) ) {
			$actions['plugin_disable'] = __( 'Disable', 'wporg-plugins' );
		}

		if ( current_user_can( 'plugin_review' ) ) {
			$actions['plugin_assign'] = __( 'Assign reviewer', 'wporg-plugins' );
		}

		return $actions;
	}

	/**
	 * @global \WP_Post $post
	 *
	 * @param int|\WP_Post $post
	 * @param int          $level
	 */
	public function single_row( $post, $level = 0 ) {
		$global_post = get_post();

		$post                = get_post( $post );
		$this->current_level = $level;

		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$classes = array(
			'iedit',
			'author-' . get_current_user_id() == $post->post_author ? 'self' : 'other',
		);

		$lock_holder = wp_check_post_lock( $post->ID );
		if ( $lock_holder ) {
			$classes[] = 'wp-locked';
		}

		if ( $post->post_parent ) {
			$count     = count( get_post_ancestors( $post->ID ) );
			$classes[] = 'level-' . $count;
		} else {
			$classes[] = 'level-0';
		}
		?>
		<tr id="post-<?php echo $post->ID; ?>" class="<?php echo implode( ' ', get_post_class( $classes, $post->ID ) ); ?>">
			<?php $this->single_row_columns( $post ); ?>
		</tr>
		<?php
		$GLOBALS['post'] = $global_post;
	}

	/**
	 * Generates and displays row action links.
	 *
	 * @access protected
	 *
	 * @param object $post        Post being acted upon.
	 * @param string $column_name Current column name.
	 * @param string $primary     Primary column name.
	 * @return string Row actions output for posts.
	 */
	protected function handle_row_actions( $post, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$post_type_object = get_post_type_object( $post->post_type );
		$can_edit_post    = current_user_can( 'edit_post', $post->ID );
		$actions          = array();
		$title            = _draft_or_post_title();

		if ( $can_edit_post && 'trash' != $post->post_status ) {
			$actions['edit'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				get_edit_post_link( $post->ID ),
				/* translators: %s: post title */
				esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;', 'wporg-plugins' ), $title ) ),
				__( 'Edit', 'wporg-plugins' )
			);

			if ( in_array( $post->post_status, array( 'new', 'pending' ) ) && current_user_can( 'plugin_approve', $post->ID ) ) {
				$actions['inline hide-if-no-js'] = sprintf(
					'<button type="button" class="button-link editinline" aria-label="%s" aria-expanded="false">%s</button>',
					/* translators: %s: post title */
					esc_attr( sprintf( __( 'Quick edit &#8220;%s&#8221; inline', 'wporg-plugins' ), $title ) ),
					__( 'Quick&nbsp;Edit', 'wporg-plugins' )
				);
			}
		}

		if ( is_post_type_viewable( $post_type_object ) && 'publish' === $post->post_status ) {
			$actions['view'] = sprintf(
				'<a href="%s" rel="permalink" aria-label="%s">%s</a>',
				get_permalink( $post->ID ),
				/* translators: %s: post title */
				esc_attr( sprintf( __( 'View &#8220;%s&#8221;', 'wporg-plugins' ), $title ) ),
				__( 'View', 'wporg-plugins' )
			);
		}

		if ( is_post_type_hierarchical( $post->post_type ) ) {

			/**
			 * Filter the array of row action links on the Pages list table.
			 *
			 * The filter is evaluated only for hierarchical post types.
			 *
			 * @param array $actions An array of row action links. Defaults are
			 *                         'Edit', 'Quick Edit', 'Restore, 'Trash',
			 *                         'Delete Permanently', 'Preview', and 'View'.
			 * @param \WP_Post $post The post object.
			 */
			$actions = apply_filters( 'page_row_actions', $actions, $post );
		} else {

			/**
			 * Filter the array of row action links on the Posts list table.
			 *
			 * The filter is evaluated only for non-hierarchical post types.
			 *
			 * @param array $actions An array of row action links. Defaults are
			 *                         'Edit', 'Quick Edit', 'Restore, 'Trash',
			 *                         'Delete Permanently', 'Preview', and 'View'.
			 * @param \WP_Post $post The post object.
			 */
			$actions = apply_filters( 'post_row_actions', $actions, $post );
		}

		return $this->row_actions( $actions );
	}

	/**
	 * Outputs the hidden row displayed when inline editing.
	 *
	 * @global string $mode List table view mode.
	 */
	public function inline_edit() {
		global $mode;

		$screen = $this->screen;

		$taxonomy_names          = get_object_taxonomies( $screen->post_type );
		$hierarchical_taxonomies = array();

		foreach ( $taxonomy_names as $taxonomy_name ) {
			$taxonomy = get_taxonomy( $taxonomy_name );

			if ( ! $taxonomy->show_in_quick_edit ) {
				continue;
			}

			if ( $taxonomy->hierarchical ) {
				$hierarchical_taxonomies[] = $taxonomy;
			}
		}

		$m = ( isset( $mode ) && 'excerpt' === $mode ) ? 'excerpt' : 'list';
	?>

	<form method="get"><table style="display: none"><tbody id="inlineedit">

		<tr id="inline-edit"
			class="inline-edit-row inline-edit-row-post inline-edit-<?php echo $screen->post_type; ?> quick-edit-row quick-edit-row-post"
			style="display: none"><td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">

		<fieldset class="inline-edit-col-left">
			<legend class="inline-edit-legend"><?php _e( 'Quick Edit', 'wporg-plugins' ); ?></legend>
			<div class="inline-edit-col">

			<label>
				<span class="title"><?php _e( 'Slug', 'wporg-plugins' ); ?></span>
				<span class="input-text-wrap"><input type="text" name="post_name" value="" /></span>
			</label>

		</div></fieldset>

	<?php if ( count( $hierarchical_taxonomies ) ) : ?>

		<fieldset class="inline-edit-col-center inline-edit-categories"><div class="inline-edit-col">

	<?php foreach ( $hierarchical_taxonomies as $taxonomy ) : ?>

			<span class="title inline-edit-categories-label"><?php echo esc_html( $taxonomy->labels->name ); ?></span>
			<input type="hidden" name="tax_input[<?php echo esc_attr( $taxonomy->name ); ?>][]" value="0" />
			<ul class="cat-checklist <?php echo esc_attr( $taxonomy->name ); ?>-checklist">
				<?php wp_terms_checklist( null, array( 'taxonomy' => $taxonomy->name ) ); ?>
			</ul>

	<?php endforeach; // $hierarchical_taxonomies as $taxonomy ?>

		</div></fieldset>

	<?php endif; // count( $hierarchical_taxonomies ) ?>

		<p class="submit inline-edit-save">
			<button type="button" class="button cancel alignleft"><?php _e( 'Cancel', 'wporg-plugins' ); ?></button>
			<?php wp_nonce_field( 'inlineeditnonce', '_inline_edit', false ); ?>
			<button type="button" class="button button-primary save alignright"><?php _e( 'Update', 'wporg-plugins' ); ?></button>
			<span class="spinner"></span>
			<input type="hidden" name="post_author" value="" />
			<input type="hidden" name="post_view" value="<?php echo esc_attr( $m ); ?>" />
			<input type="hidden" name="screen" value="<?php echo esc_attr( $screen->id ); ?>" />
			<span class="error" style="display:none"></span>
			<br class="clear" />
		</p>
		</td></tr>

		</tbody></table></form>
<?php
	}

	/**
	 * Prepares list view links, including plugins that the current user has commit access to.
	 *
	 * @global array $locked_post_status This seems to be deprecated.
	 * @global array $avail_post_stati
	 * @global \wpdb $wpdb
	 * @return array
	 */
	protected function get_views() {
		global $locked_post_status, $avail_post_stati, $wpdb;

		if ( ! empty( $locked_post_status ) ) {
			return array();
		}

		$post_type    = $this->screen->post_type;
		$status_links = array();
		$num_posts    = wp_count_posts( $post_type, 'readable' );
		$total_posts  = array_sum( (array) $num_posts );
		$class        = '';

		$current_user_id = get_current_user_id();
		$all_args        = array( 'post_type' => $post_type );
		$mine            = '';

		$plugins        = Tools::get_users_write_access_plugins( $current_user_id );
		$plugins        = array_map( 'sanitize_title_for_query', $plugins );
		$exclude_states = get_post_stati( array(
			'show_in_admin_all_list' => false,
		) );

		if ( ! current_user_can( 'plugin_approve' ) ) {
			$exclude_states = array_merge( $exclude_states, array(
				'publish'  => 'publish',
				'closed'   => 'closed',
				'rejected' => 'rejected',
				'private'  => 'private',
			) );
		}

		$user_post_count_query = "
			SELECT COUNT( 1 )
			FROM $wpdb->posts
			WHERE post_type = %s
			AND post_author = %d
		";

		if ( ! empty( $plugins ) ) {
			$user_post_count_query = str_replace( 'AND post_author = %d', "AND ( post_author = %d OR post_name IN ( '" . implode( "','", $plugins ) . "' ) )", $user_post_count_query );
		}

		$user_post_count = intval( $wpdb->get_var( $wpdb->prepare( $user_post_count_query, $post_type, $current_user_id ) ) );

		// Subtract post types that are not included in the admin all list.
		foreach ( $exclude_states as $state ) {
			$total_posts -= $num_posts->$state;
		}

		if ( $user_post_count && $user_post_count !== $total_posts ) {
			if ( isset( $_GET['author'] ) && $_GET['author'] == $current_user_id ) {
				$class = 'current';
			}

			$mine_args = array(
				'post_type' => $post_type,
				'author'    => $current_user_id,
			);

			$mine_inner_html = sprintf(
				_nx(
					'My Plugins <span class="count">(%s)</span>',
					'My Plugins <span class="count">(%s)</span>',
					$user_post_count,
					'posts',
					'wporg-plugins'
				),
				number_format_i18n( $user_post_count )
			);

			if ( ! current_user_can( 'plugin_review' ) ) {
				$status_links['mine'] = $this->get_edit_link( $mine_args, $mine_inner_html, 'current' );

				return $status_links;
			} else {
				$mine = $this->get_edit_link( $mine_args, $mine_inner_html, $class );
			}

			$all_args['all_posts'] = 1;
			$class                 = '';
		}

		if ( empty( $class ) && ( $this->is_base_request() || isset( $_REQUEST['all_posts'] ) ) ) {
			$class = 'current';
		}

		$all_inner_html = sprintf(
			_nx(
				'All <span class="count">(%s)</span>',
				'All <span class="count">(%s)</span>',
				$total_posts,
				'posts',
				'wporg-plugins'
			),
			number_format_i18n( $total_posts )
		);

		$status_links['all'] = $this->get_edit_link( $all_args, $all_inner_html, $class );

		// Assigned to me.
		if ( current_user_can( 'plugin_review' ) ) {
			$assigned_count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(p.ID)
				FROM $wpdb->posts p
					JOIN $wpdb->postmeta pm ON p.ID = pm.post_id
				WHERE pm.meta_key = 'assigned_reviewer' AND pm.meta_value = %d AND p.post_status NOT IN( 'approved', 'publish' )",
				get_current_user_id()
			) );

			$assigned_args = array(
				'post_type' => $post_type,
				'reviewer'  => get_current_user_id(),
			);

			$assigned_label = sprintf(
				_nx(
					'Assigned to Me <span class="count">(%s)</span>',
					'Assigned to Me <span class="count">(%s)</span>',
					$assigned_count,
					'posts',
					'wporg-plugins'
				),
				number_format_i18n( $assigned_count )
			);

			$status_links['assigned'] = $this->get_edit_link( $assigned_args, $assigned_label, ( ($_REQUEST['reviewer'] ?? 0) === get_current_user_id() ? 'current' : '' ) );
		}

		if ( $mine ) {
			$status_links['mine'] = $mine;
		}

		foreach ( get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' ) as $status ) {
			$class = '';

			$status_name = $status->name;

			if ( ! in_array( $status_name, $avail_post_stati ) || empty( $num_posts->$status_name ) ) {
				continue;
			}

			if ( ! current_user_can( 'plugin_approve' ) && ! in_array( $status_name, array( 'new', 'pending' ) ) ) {
				continue;
			}

			if ( isset( $_REQUEST['post_status'] ) && $status_name === $_REQUEST['post_status'] ) {
				$class = 'current';
			}

			$status_args = array(
				'post_status' => $status_name,
				'post_type'   => $post_type,
			);

			$status_label = sprintf(
				translate_nooped_plural( $status->label_count, $num_posts->$status_name ),
				number_format_i18n( $num_posts->$status_name )
			);

			$status_links[ $status_name ] = $this->get_edit_link( $status_args, $status_label, $class );
		}

		if ( ! empty( $this->sticky_posts_count ) ) {
			$class = ! empty( $_REQUEST['show_sticky'] ) ? 'current' : '';

			$sticky_args = array(
				'post_type'   => $post_type,
				'show_sticky' => 1,
			);

			$sticky_inner_html = sprintf(
				_nx(
					'Sticky <span class="count">(%s)</span>',
					'Sticky <span class="count">(%s)</span>',
					$this->sticky_posts_count,
					'posts',
					'wporg-plugins'
				),
				number_format_i18n( $this->sticky_posts_count )
			);

			$sticky_link = array(
				'sticky' => $this->get_edit_link( $sticky_args, $sticky_inner_html, $class ),
			);

			// Sticky comes after Publish, or if not listed, after All.
			$split        = 1 + array_search( ( isset( $status_links['publish'] ) ? 'publish' : 'all' ), array_keys( $status_links ) );
			$status_links = array_merge( array_slice( $status_links, 0, $split ), $sticky_link, array_slice( $status_links, $split ) );
		}

		return $status_links;
	}

	/**
	 * Display the additional filter fields.
	 */
	public function extra_tablenav( $which ) {
		parent::extra_tablenav( $which );

		if ( 'top' === $which ) {
			?>
			<fieldset class="alignleft actions filter-reviewer">
				<?php
				wp_dropdown_users( [
					'name'              => 'reviewer',
					'selected'          => intval( $_REQUEST['reviewer'] ?? 0 ),
					'show_option_none'  => __( 'All Reviewers', 'wporg-plugins' ),
					'option_none_value' => 0,
					'role__in'          => [ 'plugin_admin', 'plugin_reviewer' ],
				] );
				submit_button( __( 'Filter', 'wporg-plugins' ), 'secondary', false, false );
				?>
			</fieldset>
			<?php
		}
	}

	/**
	 * Display the additional bulk action fields.
	 */
	protected function bulk_actions( $which = '' ) {
		parent::bulk_actions( $which );

		$maybe_dash_two = 'top' === $which ? '' : '-2';

		?>
		<fieldset class="alignleft actions hide-if-js bulk-plugin_assign" disabled="disabled">
			<?php
			wp_dropdown_users( [
				'name'              => 'reviewer',
				'id'                => "reviewer{$maybe_dash_two}",
				'selected'          => 0,
				'show_option_none'  => __( 'Assign Review to ...', 'wporg-plugins' ),
				'option_none_value' => 0,
				'role__in'          => [ 'plugin_admin', 'plugin_reviewer' ],
			] );
			?>
		</fieldset>
		<fieldset class="alignleft actions hide-if-js bulk-plugin_close bulk-plugin_disable" disabled="disabled">
			<select name="close_reason" id="close_reason<?php echo $maybe_dash_two; ?>">
				<option disabled="disabled" value='' selected="selected"><?php esc_html_e( 'Close/Disable Reason:', 'wporg-plugins' ); ?></option>
				<?php foreach ( Template::get_close_reasons() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</fieldset>

		<fieldset class="alignleft actions hide-if-js bulk-plugin_reject" disabled="disabled">
			<select name="rejection_reason" id="rejection_reason">
				<option disabled="disabled" value='' selected="selected"><?php esc_html_e( 'Rejection Reason:', 'wporg-plugins' ); ?></option>
				<?php foreach ( Template::get_rejection_reasons() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</fieldset>

		<?php

		// Output the JS+CSS needed
		if ( 'top' === $which ) {
			?>
			<style>
				#posts-filter fieldset.bulk-plugin_close {
					margin: 0;
				}
			</style>
			<script>
			jQuery( function( $ ) {
				$( '.bulkactions' ).on( 'change', function() {
					var $this = $( this ),
						$select = $this.find( 'select[name^=action]' ),
						val = $select.find(':selected').val();

					$( '.bulkactions .actions.bulk-plugin_close, .actions.bulk-plugin_disable, .actions.bulk-plugin_assign' ).prop( 'disabled', true ).hide();
					$( '.bulkactions .actions.bulk-' + val ).prop( 'disabled', false ).show();

					// Sync the values between the various selects in top+bottom.
					$this.find('select').not( $select ).each( function() {
						$( '.bulkactions' ).not( $this ).find( 'select[name="' + this.name + '"]' ).val( this.value );
					} );

					$( '.bulkactions input.action' ).toggleClass( 'button-primary', val != '-1' );
				} );
			} );
			</script>
			<?php
		}
	}

	/**
	 * The custom Assigned Reviewer column.
	 */
	public function column_reviewer( $post ) {
		$reviewer_id   = (int) ( $post->assigned_reviewer ?? 0 );
		$reviewer      = $reviewer_id ? get_user_by( 'id', $reviewer_id ) : false;
		$reviewer_time = (int) ( $post->assigned_reviewer_time ?? 0 );

		if ( $reviewer ) {
			printf(
				"<a href='%s'>%s</a><br><span>%s</span>",
				add_query_arg( [ 'reviewer' => $reviewer_id ] ),
				$reviewer->display_name ?: $reviewer->user_login,
				sprintf(
					/* translators: %s The time/date different, '1 hour' */
					__( '%s ago', 'wporg-plugins' ),
					human_time_diff( $reviewer_time )
				)
			);
		} else {
			echo '-';
		}
	}

	public function column_zip( $post ) {
		$media = get_attached_media( 'application/zip', $post );

		if ( ! $media || ! in_array( $post->post_status, [ 'new', 'pending', 'approved' ] ) ) {
			echo '-';
			return;
		}

		foreach ( $media as $zip_file ) {
			$zip_size = size_format( filesize( get_attached_file( $zip_file->ID ) ), 1 );

			$url  = wp_get_attachment_url( $zip_file->ID );
			$name = basename( $url );
			$name = explode( '_', $name, 3 )[2];

			printf(
				'<a href="%1$s">%2$s</a><br>%3$s<br>(<a href="%4$s" target="_blank">test</a> | <a href="%5$s" target="_blank">pcp</a>)<br></li>',
				esc_url( $url ),
				esc_html( $name ),
				esc_html( $zip_size ),
				esc_url( Template::preview_link_zip( $post->post_name, $zip_file->ID ) ),
				esc_url( Template::preview_link_zip( $post->post_name, $zip_file->ID, 'pcp' ) )
			);
		}
	}

	public function column_loc( $post ) {
		if ( ! in_array( $post->post_status, [ 'new', 'pending', 'approved' ] ) ) {
			echo '-';
			return;
		}

		echo number_format_i18n( (int) $post->_submitted_zip_loc ) ?: '-';
	}

	public function column_submitted_date( $post ) {
		echo gmdate( 'Y/m/d g:i a', $post->_submitted_date ?? 0 );
	}
}
