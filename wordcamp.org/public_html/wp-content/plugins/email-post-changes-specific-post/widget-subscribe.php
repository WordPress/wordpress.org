<?php

/**
 * A widget that allows visitors to subscribe to the current post/page
 * A form is presented to the visitor to enter their email address, and then their address is mapped to the current post ID and saved in the database
 *
 * @package EPCSpecificPost
 */
class EPCSP_SubscribeWidget extends WP_Widget {
	
	/**
	 * Constructor
	 */
	function __construct() {
		$widget_options  = array(
			'classname'   => 'epcsp_subscribe',
			'description' => __( 'Allow visitors to subscribe to email notifications for a specific post or page' )
		);
		$control_options = array( 'width' => 300 );
		
		parent::__construct( 'epcsp_subscribe', __( 'Subscribe to Specific Post' ), $widget_options, $control_options );

		add_action( 'wp_head', array( $this, 'output_css' ) );
	}

	/**
	 * Displays and processes the front-end form that visitors will enter their email address into in order to subscribe to the current post
	 * 
	 * @param array $args
	 * @param array $instance
	 */
	function widget( $args, $instance ) {
		$errors  = array();
		$message = '';
		$title   = apply_filters( 'epcsp_subscribe_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );
		
		if ( ! is_singular() || ! $this->is_supported_epc_post_type( get_post_type() ) ) {
			return;
		}

		if ( isset( $_POST['epcsp_subscribe_submit'] ) ) {
			$errors = $this->subscribe_visitor_to_post( $_POST['epcsp_subscribe_address'], get_the_ID() );

			if ( $errors ) {
				foreach ( $errors as $error ) {
					$message .= '<p>' . $error . '</p>';
				}
			} else {
				$message = 'You have been subscribed to this post.';
			}
		}
		
		echo $args['before_widget'];
		
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		echo $message;
		if ( ! isset( $_POST['epcsp_subscribe_submit'] ) || $errors ) {
			$this->render_subscription_form();
		}
		
		echo $args['after_widget'];
	}

	/**
	 * Determines if the given post type is enabled in EPC's settings
	 * 
	 * @param  string $post_type
	 * @return bool
	 */
	function is_supported_epc_post_type( $post_type ) {
		$supported = false;
		
		if ( class_exists( 'Email_Post_Changes' ) ) {
			$epc_options = Email_Post_Changes::init()->get_options();
			
			if ( in_array( $post_type, $epc_options['post_types'] ) ) {
				$supported = true;
			}
		}
		
		return $supported;
	}

	/**
	 * Renders the form the visitor inputs their email address into
	 */
	function render_subscription_form() {
		?>

		<form id="epcsp_subscribe" name="epcsp_subscribe" action="" method="POST">
			<label for="epcsp_subscribe_address">Email Address:</label>
			<input id="epcsp_subscribe_address" name="epcsp_subscribe_address" type="text" />
			<input name="epcsp_subscribe_submit" type="submit" value="Subscribe" />
		</form>

		<?php
	}

	/**
	 * Outputs the styles for the widget
	 */
	function output_css() {
		?>
		
		<style type="text/css">
			#epcsp_subscribe label,
			#epcsp_subscribe input {
				display: block;
			}
			
			#epcsp_subscribe_address {
				width: 100%;
				max-width: 300px;
				margin: 5px 0;
			}
		</style>
		
		<?php
	}
	
	/**
	 * Subscribes the visitor to the post
	 * 
	 * @param  string $email
	 * @param  int    $post_id
	 * @return array
	 */
	function subscribe_visitor_to_post( $email, $post_id ) {
		$errors  = array();
		$post_id = absint( $post_id );
		
		if ( is_email( $email ) ) {
			$subscribed_addresses = get_option( 'epcsp_subscribed_addresses', array() );
			
			if ( isset( $subscribed_addresses[ $post_id ] ) && in_array( $email, $subscribed_addresses[ $post_id ] ) ) {
				$errors[] = 'You are already subscribed to this post.';
			} else {
				$subscribed_addresses[ $post_id ][] = sanitize_email( $email );
				$subscribed_addresses[ $post_id ] = array_unique( $subscribed_addresses[ $post_id ] );
				
				update_option( 'epcsp_subscribed_addresses', $subscribed_addresses );
			}
		} else {
			$errors[] = 'The email address you entered was not valid.';
		}
		
		return $errors;
	}

	/**
	 * Processes the back-end form on the Widgets page
	 * 
	 * @param  array $new_instance
	 * @param  array $old_instance
	 * @return array
	 */
	function update( $new_instance, $old_instance ) {
		$instance          = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		
		return $instance;
	}

	/**
	 * Generates the back-end form for the Widgets page
	 * 
	 * @param array $instance
	 */
	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
		$title    = strip_tags( $instance['title'] );
		
		?>
		
		<?php if ( ! $this->is_epc_enabled() ) : ?>
			<div class="error inline">
				<strong>Warning:</strong> The 'Enable' setting on <a href="<?php echo admin_url( 'options-general.php?page=email_post_changes' ); ?>">the Email Post Changes settings page</a> is disabled. No emails will be sent until it is enabled.
			</div>
		<?php endif; ?>
		
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		
		<?php
	}

	/**
	 * Determines if the Email Post Changes plugin has the 'Enabled' setting turned on or not
	 * 
	 * @return bool
	 */
	function is_epc_enabled() {
		$enabled = false;
		
		if ( class_exists( 'Email_Post_Changes' ) ) {
			$epc_options = Email_Post_Changes::init()->get_options();

			if ( $epc_options['enable'] ) {
				$enabled = true;
			}
		}
		
		return $enabled;
	}
}