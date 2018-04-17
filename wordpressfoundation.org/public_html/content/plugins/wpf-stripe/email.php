<?php

namespace WordPress_Foundation\Stripe\Email;

use WordPress_Foundation\Stripe;
use WP_Post;

defined( 'WPINC' ) || die();

const EMAIL_POST_TYPE = 'wpf_donation_email';

/**
 * Send a donation email.
 *
 * @param string $template_name
 * @param array  $transaction_data
 */
function send_email( $template_name, $transaction_data ) {
	$transaction_data = wp_parse_args( $transaction_data, array(
		'email' => '',
	) );

	$template = get_email_template( $template_name );

	if ( ! $template ) {
		Stripe\log( 'Email template is missing.', compact( 'template_name', 'transaction_data' ) );
		return;
	}

	$to      = $transaction_data['email'];
	$subject = merge_template_data( $template->post_title, $transaction_data );
	$message = merge_template_data( $template->post_content, $transaction_data );
	$headers = array( 'Reply-To: stripe-wpf@wordcamp.org' );

	if ( ! is_email( $to ) || empty( $subject ) || empty( $message ) ) {
		Stripe\log( 'Invalid email parameters.', compact( 'to', 'subject', 'message' ) );
		return;
	}

	//todo create multipart HTML/plaintext

	$success = wp_mail( $to, $subject, $message, $headers );

	if ( ! $success ) {
		Stripe\log( 'Email failed to send.', compact( 'to', 'subject', 'message' ) );
	}
}

/**
 * List the possible template names for a donation email.
 *
 * @return array
 */
function get_template_names() {
	return array(
		'upcoming-renewal',
		'thanks-one-time-donation',
		'thanks-recurring-donation',
	);
}

/**
 * Get a template post for a donation email.
 *
 * @param string $template_name
 *
 * @return WP_Post|bool
 */
function get_email_template( $template_name ) {
	$query_args = array(
		'post_type'   => EMAIL_POST_TYPE,
		'orderby'     => 'date',
		'order'       => 'DESC',
		'numberposts' => 1,
		'meta_key'    => 'wpf-stripe_email-template-name',
		'meta_value'  => $template_name,
	);

	$result = get_posts( $query_args );

	if ( empty( $result ) ) {
		return false;
	}

	$current_template = array_shift( $result );

	return $current_template;
}

/**
 * List the merge tags that can be used in a donation email template.
 *
 * @return array
 */
function get_merge_tags() {
	return array(
		'full_name',
		'first_name',
		'last_name',
		'full_address',
		'address1',
		'address2',
		'city',
		'state',
		'zip_code',
		'country',
		'transaction_date',
		'transaction_amount',
		'payment_card_type',
		'payment_card_last4',
	);
}

/**
 * Take a template string with merge tags and replace the tags with data.
 *
 * Note that this should be used for both the email subject and message.
 *
 * @param string $template
 * @param array  $data
 *
 * @return null|string|string[]
 */
function merge_template_data( $template, $data ) {
	foreach ( get_merge_tags() as $tag ) {
		$replacement = ( array_key_exists( $tag, $data ) ) ? $data[ $tag ] : '';
		$template    = preg_replace( "#\{$tag\}#", $replacement, $template );
	}

	return $template;
}

/**
 * Email template CPT.
 */
function register_post_type() {
	$labels = array(
		'name'           => 'Donation Email Templates',
		'singular_name'  => 'Donation Email Template',
		'menu_name'      => 'Donation Emails',
		'name_admin_bar' => 'Donation Email',
		'all_items'      => 'All Email Templates',
		'add_new_item'   => 'Add New Email Template',
		'add_new'        => 'Add New',
		'new_item'       => 'New Email Template',
		'edit_item'      => 'Edit Email Template',
		'update_item'    => 'Update Email Template',
		'view_item'      => 'View Email Template',
		'view_items'     => 'View Email Templates',
	);

	$args = array(
		'label'        => __( 'Donation Email Templates', 'text_domain' ),
		'description'  => __( 'Templates for emails sent to donors', 'text_domain' ),
		'labels'       => $labels,
		'supports'     => array( 'title', 'editor', 'author', 'revisions', 'custom-fields' ),
		'hierarchical' => false,
		'public'       => false,
		'show_ui'      => true,
	);

	\register_post_type( EMAIL_POST_TYPE, $args );
}

add_action( 'init', __NAMESPACE__ . '\register_post_type' );

/**
 * Add a help tab to the edit screen for email template posts.
 */
function add_contextual_help_tabs() {
	global $typenow;

	if ( ! isset( $typenow ) || EMAIL_POST_TYPE !== $typenow ){
		return;
	}

	$screen = get_current_screen();

	$tabs = array(
		'instructions' => 'Instructions',
	);

	foreach ( $tabs as $id => $label ) {
		$screen->add_help_tab( array(
			'id'       => 'wpf-stripe_' . $id,
			'title'    => $label,
			'callback' => __NAMESPACE__ . '\render_contextual_help_tabs',
		) );
	}
}

add_action( 'load-edit.php',     __NAMESPACE__ . '\add_contextual_help_tabs' );
add_action( 'load-post-new.php', __NAMESPACE__ . '\add_contextual_help_tabs' );

/**
 * Callback to render the help tab.
 *
 * @param \WP_Screen $screen
 * @param array      $tab
 */
function render_contextual_help_tabs( $screen, $tab ) {
	$tab_id = str_replace( 'wpf-stripe_', '', $tab['id'] );

	switch ( $tab_id ) {
		case 'instructions' :
			$merge_tags     = get_merge_tags();
			$template_names = get_template_names();

			?>

			<p><strong>Donation Email Template Instructions</strong></p>
			<p>The post title is the email <strong>subject</strong>, and the post content is the email <strong>message</strong>.</p>
			<p>The following merge tags can be used in the subject and the message:</p>

			<ul>
				<?php foreach ( $merge_tags as $merge_tag ) : ?>
					<li><code>{<?php echo $merge_tag; ?>}</code></li>
				<?php endforeach; ?>
			</ul>

			<p>To assign a template to a particular donation email, create a custom field with <code>wpf-stripe_email-template-name</code> as the key and the email name as the value. The following emails need to have templates assigned to them:</p>

			<ul>
				<?php foreach ( $template_names as $template_name ) : ?>
					<li><code><?php echo $template_name; ?></code></li>
				<?php endforeach; ?>
			</ul>

			<?php
			break;
	}
}

/**
 * Show admin notices.
 */
function admin_notices() {
	$notices = array();

	// Missing email templates.
	$email_page_url = add_query_arg( 'post_type', EMAIL_POST_TYPE, admin_url( 'edit.php' ) );

	foreach ( get_template_names() as $name ) {
		$template = get_email_template( $name );

		if ( ! $template ) {
			$notices[] = sprintf(
				'The <code>%s</code> email does not have an assigned template. Check the Help tab for <a href="%s">Donation Email Templates</a> for more information.',
				esc_html( $name ),
				esc_url( $email_page_url )
			);
		}
	}

	// Output all notices.
	foreach ( $notices as $notice ) {
		?>

		<div class="notice notice-error">
			<?php echo wpautop( $notice ); ?>
		</div>

		<?php
	}
}

add_action( 'admin_notices', __NAMESPACE__ . '\admin_notices' );
