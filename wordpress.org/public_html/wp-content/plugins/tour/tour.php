<?php
/**
 * Plugin Name: Tour
 * Plugin URI: http://wordpress.org/plugins/tour/
 * Description: A WordPress plugin for creating tours for your site.
 * Version: 1.0
 * Author: Automattic
 * Author URI: http://automattic.com/
 * Text Domain: tour
 * License: GPLv2 or later
 */

defined( 'ABSPATH' ) || die();

function tour_enqueue_scripts() {
	static $once = false;
	if ( $once ) {
		return;
	}
	$once = true;

	wp_register_style( 'driver-js', plugins_url( 'assets/css/driver-js.css', __FILE__ ), array(), filemtime( __DIR__ . '/assets/css/driver-js.css' ) );
	wp_register_style( 'tour-css', plugins_url( 'assets/css/style.css', __FILE__ ), array(), filemtime( __DIR__ . '/assets/css/style.css' ) );
	wp_enqueue_style( 'driver-js' );
	wp_enqueue_style( 'tour-css' );
	wp_enqueue_script( 'driver-js', plugins_url( 'assets/js/driver-js.js', __FILE__ ), array(), filemtime( __DIR__ . '/assets/js/driver-js.js' ), array( 'in_footer' => true ) );
	wp_register_script( 'tour', plugins_url( 'assets/js/tour.js', __FILE__ ), array( 'driver-js' ), filemtime( __DIR__ . '/assets/js/tour.js' ), false );
	wp_enqueue_script( 'tour' );
	wp_localize_script(
		'tour',
		'tour_plugin', array(
			'tours'    => apply_filters( 'tour_list', array() ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'rest_url' => rest_url(),
			'progress' => get_user_option( 'tour-progress', get_current_user_id() ),
		)
	);

	if ( current_user_can( 'edit_posts' ) ) {
		wp_register_script( 'tour-admin', plugins_url( 'assets/js/tour-admin.js', __FILE__ ), array(  'driver-js' ), filemtime( __DIR__ . '/assets/js/tour-admin.js' ), true );
		wp_enqueue_script( 'tour-admin' );
	}
}

add_action( 'admin_enqueue_scripts', 'tour_enqueue_scripts' );
add_action( 'wp_enqueue_scripts', 'tour_enqueue_scripts' );
add_action( 'gp_head', 'wp_enqueue_scripts' );

function tour_register_post_type() {
	register_post_type(
		'tour',
		array(
			'labels'       => array(
				'name'          => __( 'Tours', 'tour' ),
				'singular_name' => __( 'Tour', 'tour' ),
				'add_new'       => __( 'Create New', 'tour' ),
				'add_new_item'  => __( 'Create New Tour', 'tour' ),
				'edit_item'     => __( 'Edit Tour', 'tour' ),
				'new_item'      => __( 'New Tour', 'tour' ),
				'all_items'     => __( 'All Tours', 'tour' ),
				'view_item'     => __( 'View Tour', 'tour' ),
				'search_items'  => __( 'Search Tours', 'tour' ),
				'not_found'     => __( 'No tours found.', 'tour' ),

			),

			'public'       => false,
			'show_ui'      => true,
			'show_in_nav_menus'      => true,
			'show_in_menu' => 'tour',
			'supports'     => array( 'title', 'revisions' ),
		)
	);
}
add_action( 'init', 'tour_register_post_type' );

add_action(
	'rest_api_init',
	function() {
		register_rest_route(
			'tour/v1',
			'save-progress',
			array(
				'methods'  => 'POST',
				'callback' => function( WP_REST_Request $request ) {
					if ( ! is_user_logged_in() ) {
						return array( 'success' => 'logged-out' );
					}
					$step = $request->get_param( 'step' );
					$tour_id = $request->get_param( 'tour' );

					$tour = get_post( $tour_id );
					if ( ! $tour || is_wp_error( $tour ) || $tour->post_type !== 'tour' ) {
						return array(
							'success' => false,
						);
					}

					$tour_progress = get_user_option( 'tour-progress', get_current_user_id() );
					if ( ! $tour_progress ) {
						$tour_progress = array();
					}
					if ( $step < 0 || ! is_numeric( $step ) ) {
						unset( $tour_progress[ $tour_id ] );
					} else {
						$tour_progress[ $tour_id ] = $step;
					}
					update_user_option( get_current_user_id(), 'tour-progress', $tour_progress );
					return array(
						'success' => true,
					);
				},
			)
		);

		register_rest_route(
			'tour/v1',
			'report-missing',
			array(
				'methods'  => 'POST',
				'callback' => function( WP_REST_Request $request ) {
					$step = $request->get_param( 'step' );
					$tour_id = $request->get_param( 'tour' );
					$selector = $request->get_param( 'selector' );
					$url = $request->get_param( 'url' );

					$tour = get_post( $tour_id );
					if ( ! $tour || is_wp_error( $tour ) || $tour->post_type !== 'tour' ) {
						return array(
							'success' => false,
						);
					}

					if ( $tour ) {
						$missing_steps = get_post_meta( $tour, 'missing_steps', true );
						if ( ! $missing_steps ) {
							$missing_steps = array();
						}
						if ( ! isset( $missing_steps[ $step ] ) ) {
							$missing_steps[ $step ] = array();
						}
						if ( ! isset( $missing_steps[ $step ][ $url ] ) ) {
							$missing_steps[ $step ][ $url ] = array();
						}
						if ( ! isset( $missing_steps[ $step ][ $url ][ $selector ] ) ) {
							$missing_steps[ $step ][ $url ][ $selector ] = 0;
						}
						$missing_steps[ $step ][ $url ][ $selector ] += 1;
						update_post_meta( $tour, 'missing_steps', $missing_steps );
						return array(
							'success' => true,
						);
					}
					return array(
						'success' => false,
					);
				},
			)
		);

		register_rest_route(
			'tour/v1',
			'save',
			array(
				'methods'  => 'POST',
				'callback' => function( WP_REST_Request $request ) {
					if ( ! current_user_can( 'edit_posts' ) ) {
						return array(
							'success' => false,
						);
					}
					$steps = json_decode( $request->get_param( 'steps' ), true );
					if ( ! isset( $steps[0]['title'] ) ) {
						return array(
							'success' => false,
						);
					}
					if ( ! isset( $steps[1]['popover'] ) ) {
						return array(
							'success' => false,
						);
					}
					$tour_id = $request->get_param( 'tour' );

					$tour = get_post( $tour_id );
					if ( ! $tour || is_wp_error( $tour ) || $tour->post_type !== 'tour' ) {
						return array(
							'success' => false,
						);
					}

					if ( $tour ) {
						wp_update_post(
							array(
								'ID'           => $tour_id,
								'post_content' => json_encode( $steps ),
								'post_status'  => 'publish',
							),
							true
						);
					}

					return $tour_id;
				},
			)
		);
	}
);

add_filter(
	'post_row_actions',
	function( $actions, $post ) {
		if ( $post->post_type !== 'tour' || $post->post_status === 'trash' ) {
			return $actions;
		}

		$tour_steps = json_decode( wp_unslash( $post->post_content ), true );
		if ( empty( $tour_steps[0]['title'] ) ) {
			return $actions;
		}

		$caption = __( 'Add more steps', 'tour' );

		$actions['add-more-steps'] = '<a href="' . get_permalink( $post->ID ) . '" data-tour-id="' . esc_attr( $post->ID ) . '" data-add-more-steps-text="' . esc_attr( $caption ) . '" data-finish-tour-creation-text="' . esc_attr( __( 'Finish tour creating the tour',' tour' ) ) . '" title="' . esc_attr( $caption ) . '">' . esc_html( $caption ) . '</a>';
		return $actions;
	}, 10, 2
);

add_filter(
	'tour_row_actions',
	function ( $actions, $tag ) {
		$actions[] = 'Add more steps';
		return $actions;
	}, 10, 2
);

add_filter(
	'wp_insert_post_data',
	function ( $data, $postarr ) {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-post_' . $postarr['ID'] ) ) {
			return $data;
		}

		if ( ! isset( $_POST['tour'] ) || ! isset( $_POST['color'] ) || 'tour' !== $data['post_type'] ) {
			return $data;
		}

		$data['post_title'] = sanitize_text_field( $_POST['post_title'] );

		$tour = array(
			array(
				'color' => sanitize_text_field( $_POST['color'] ),
				'title' => $data['post_title'],
			)
		);

		if ( isset( $_POST['override_json'] ) ) {
			$data['post_content'] = sanitize_text_field( $_POST['json'] );
			return $data;
		}

		if ( isset( $_POST['order'] ) ) {
			foreach ( $_POST['order'] as $i ) {
				$step = $_POST['tour'][$i];

				if ( '' === trim( $step['element'] ) ) {
					continue;
				}

				if ( ! isset( $step['popover'] ) ) {
					continue;
				}

				$step['element'] = sanitize_text_field( $step['element'] );
				foreach ( $step['popover'] as $k => $v ) {
					if ( ! in_array( $k, array( 'title', 'description'))) {
						unset($step['popover'][$k]);
					}
				}
				$tour[] = $step;
			}
		}

		$data['post_content'] = wp_json_encode( $tour );
		return $data;
	},
	10,
	2
);

add_action( 'postbox_classes_tour_tour-json', function( $classes ) {
	$classes[] = 'closed';
	return $classes;
});
add_action( 'admin_init', function() {
	add_meta_box(
		'tour-json',
		'JSON',
		function( $post ) {
			$tour = json_decode( wp_unslash( $post->post_content ), true );
			if ( $tour ) {
				$json = json_encode( $tour, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
				?><textarea name="json" style="font-family: monospace; width: 100%" rows="<?php echo esc_attr( min( 50, 2 + count( explode( PHP_EOL, $json ) ) ) ); ?>"><?php echo esc_html( $json ); ?></textarea><br/>
				<label><input type="checkbox" name="override_json" value="1"> <?php esc_html_e( 'Override when saving', 'tour' ); ?></label>
				<?php
			}
		},
		'tour',
		'side',
		'low'
	);
} );

add_action( 'edit_form_after_editor', function( $post ) {
	if ( 'tour' !== get_post_type( $post ) ) {
		return;
	}

	$tour = json_decode( wp_unslash( $post->post_content ), true );
	if ( ! $tour ) {
		$color = '#3939c7';
		$tour = array();
	} else {
		$color = $tour[0]['color'];
		array_shift( $tour );
	}

	?>
	<div style="border: 1px solid #ccc; border-radius: 4px; padding: .5em; margin-top: 2em">
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Color', 'tour' ); ?></th>
				<td>
					<input type="color" name="color" id="tour_color" value="<?php echo esc_attr( $color  ); ?>" />
				</td>
			</tr>
		</table>
	</div>
	<div id="steps">
		<?php
		foreach ( $tour as $k => $step ) {
			?>
			<div class="step" style="border: 1px solid #ccc; border-radius: 4px; padding: .5em; margin-top: 2em">
				<input type="hidden" name="order[]" value="<?php echo esc_attr( $k ); ?>"/>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><label for="tour-title-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'Title', 'tour' ); ?></label><br>
							</th>
							<td>
								<input name="tour[<?php echo esc_attr( $k ); ?>][popover][title]" rows="7" id="tour-step-title-<?php echo esc_attr( $k ); ?>" class="regular-text" value="<?php echo esc_attr( $step['popover']['title'] ); ?>"/>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="tour-step-description-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'Description', 'tour' ); ?></label></th>
							<td>
								<textarea name="tour[<?php echo esc_attr( $k ); ?>][popover][description]" rows="7" id="tour-step-description-<?php echo esc_attr( $k ); ?>" class="large-text"><?php echo esc_html( $step['popover']['description'] ); ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="tour-step-element-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'CSS Selector', 'tour' ); ?></label></th>
							<td>
								<textarea name="tour[<?php echo esc_attr( $k ); ?>][element]" rows="7" id="tour-step-element-<?php echo esc_attr( $k ); ?>" class="large-text code tour-step-css"><?php echo esc_html( is_array( $step['element'] ) ? reset( $step['element'] ) : $step['element'] ); ?></textarea>
							</td>
						</tr>
					</tbody>
				</table>
				<a href="#" class="delete-tour-step" data-delete-text="<?php esc_attr_e( 'Delete', 'tour' ); ?>" data-undo-text="<?php esc_attr_e( 'Undo Delete', 'tour' ); ?>"><?php esc_html_e( 'Delete', 'tour' ); ?></a>
				<a href="#" class="tour-move-up"><?php esc_html_e( 'Move Up', 'tour' ); ?></a>
				<a href="#" class="tour-move-down"><?php esc_html_e( 'Move Down', 'tour' ); ?></a>

			</div>
			<?php
			}
			?>
		</div>
	<?php if ( $post->post_title ) : ?>
		<br/><button id="add-more-steps" class="button"><?php esc_html_e( 'Add Steps', 'tour' ); ?></button>
	<?php else : ?>
		<p class="description">
			Set a title to add tour steps.
		</p>
	<?php endif; ?>
	<style>
		#driver-popover-content {
			max-width: none;
		}
	</style>
	<script>
		document.getElementById('post').addEventListener('submit', function ( event ) {
			setTourCookie( document.getElementById('post_ID').value );
		} );

		<?php if ( $post->post_title ) : ?>
		document.getElementById('add-more-steps').addEventListener('click', function ( event ) {
			event.preventDefault();
			setTourCookie( document.getElementById('post_ID').value );
			const driver = window.driver.js.driver;
			var driverObj = driver( {
				showProgress: false,
				steps: [
					{
						element: '#tour-launcher',
						popover: {
							title: 'Add your first step',
							description: 'Click this to enable and disable.',
							side: 'top'
						}
					},
					{
						popover: {
							title: 'Select the element to highlight',
							description: '<img src="<?php echo esc_url( plugins_url( 'assets/images/select-tour-step.gif', __FILE__ ) ); ?>" alt="<?php esc_attr_e( 'Tour creation mode', 'tour' ); ?>" width="525" height="166" />',
							side: 'top'
						}
					}
				]
			} );
			driverObj.drive();
		} );
		<?php endif; ?>
		var updateArrows = function() {
			document.querySelectorAll('.step').forEach( function( element ) {
				element.querySelector('.tour-move-up').style.display = element.previousElementSibling ? 'inline' : 'none';
				element.querySelector('.tour-move-down').style.display = element.nextElementSibling ? 'inline' : 'none';
			});
		}

		document.addEventListener('click', function( event ) {
			if ( ! event.target.matches('.tour-move-up') ) {
				return;
			}
			event.preventDefault();
			var element = event.target.closest('div');
			var parent = element.parentNode;
			var prev = element.previousElementSibling;
			if ( prev ) {
				parent.insertBefore( element, prev );
			}
			updateArrows();
		});

		document.addEventListener('click', function( event ) {
			if ( ! event.target.matches('.tour-move-down') ) {
				return;
			}
			event.preventDefault();
			var element = event.target.closest('div');
			var parent = element.parentNode;
			var next = element.nextElementSibling;
			if ( next ) {
				parent.insertBefore( next, element );
			}
			updateArrows();
		});
		updateArrows();

		document.addEventListener('click', function( event ) {
			if ( ! event.target.matches('.delete-tour-step') ) {
				return;
			}
			event.preventDefault();
			var t = event.target.closest('div').querySelector('table');
			var css = t.querySelector('.tour-step-css');
			if ( t.style.display === 'none' ) {
				t.style.display = 'table';
				css.value = css.dataset.oldValue;
				event.target.textContent = event.target.dataset.deleteText;
				return;
			}
			t.style.display = 'none';
			css.dataset.oldValue = css.value;
			css.value = '';
			event.target.textContent = event.target.dataset.undoText;
		});

	</script>
	<?php
}
);

add_filter(
	'tour_list',
	function( $tour ) {
		$args = array(
			'post_type'      => 'tour',
			'posts_per_page' => -1,
		);
		if ( current_user_can( 'edit_posts' ) ) {
			$args['post_status'] = array( 'publish', 'draft' );
		}
		$tours = get_posts( $args );

		foreach ( $tours as $_tour ) {
			$tour_steps         = json_decode( wp_unslash( $_tour->post_content ), true );
			if ( ! $tour_steps ) {
				$tour_steps = array(
					array(
						'title' => $_tour->post_title,
						'color' => '#3939c7',
					)
				);
			}
			$tour[ $_tour->ID ] = $tour_steps;
		}

		return $tour;
	}
);

function tour_add_admin_menu() {
	add_menu_page( 'Tour', 'Tour', 'edit_posts', 'tour', 'tour', 'dashicons-admin-site-alt3', 6 );
	add_submenu_page( 'tour', 'Settings', 'Settings', 'edit_posts', 'tour-settings', 'tour_admin_settings' );
}

add_action( 'admin_menu', 'tour_add_admin_menu' );

function tour_admin_settings() {}

function output_tour_button() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	?>
	<style>
		#tour-launcher {
			position: fixed;
			bottom: 76px;
			right: 24px;
			font-size: 13px;
			border: 1px solid #ccc;
			border-radius: 10px;
			background: #fff;
			padding: .5em;
			line-height: 1;
			box-shadow: 0 0 3px #999;
			z-index: 999999;
		}
		#tour-launcher span#tour-title {
			cursor: pointer;
			line-height: 1.3em;
		}
		#tour-launcher span#tour-title:hover {
			text-shadow: 0 0 1px #999;
		}
	</style>
	<div id="tour-launcher" style="display: none;">
		<span class="dashicons dashicons-admin-site-alt3"></span>
		<span id="tour-title"></span>
		<br>
		<span style="float: right">
		<span id="tour-steps"></span>
		<a href="">close</a>
		</span>
	</div>
	<?php
}

add_action( 'wp_footer', 'output_tour_button' );
add_action( 'admin_footer', 'output_tour_button' );

add_action('show_user_profile', function() {
	?>
	<h2>Tour</h2>
	<p>Reset your tour progress:</p>
	<table class="">
		<thead>
			<tr>
				<td>Name</td>
				<td>Progress</td>
				<td>Action</td>
			</tr>
		</thead>
	<?php
	$progress = get_user_option( 'tour-progress', get_current_user_id() );
	foreach ( apply_filters( 'tour_list', array() ) as $tour_id => $tour ) {
		$tour_title = $tour[0]['title'];
		?>
		<tr>
			<td><?php echo esc_html( $tour_title ); ?>:</td>
			<td class="tour-progress" data-not-started-text="<?php esc_attr_e( 'Not started.', 'tour' ); ?>"><?php
		if ( isset( $progress[$tour_id] ) && $progress[$tour_id] ) {
			echo esc_html( $progress[$tour_id] );
		} else {
			esc_html_e( 'Not started.', 'tour' );
		}
		?></td>
		<td><a href="" class="reset-tour" data-reset-tour-id="<?php echo esc_html( $tour_id ); ?>">Reset</td>
		</tr>
		<?php
	}

	?>
	</table>
	<script>
document.addEventListener('click', function( event ) {
	if ( ! event.target.dataset.resetTourId ) {
		return;
	}

	event.preventDefault();

	var xhr = new XMLHttpRequest();
	xhr.open('POST', tour_plugin.rest_url + 'tour/v1/save-progress');
	xhr.setRequestHeader('Content-Type', 'application/json');
	xhr.setRequestHeader('X-WP-Nonce', tour_plugin.nonce);
	xhr.send(JSON.stringify({
		tour: event.target.dataset.resetTourId,
		step: -1
	}));
	var p = event.target.closest('tr').querySelector('.tour-progress');

	p.textContent = p.dataset.notStartedText;

} );
	</script>
	<?php
} );
