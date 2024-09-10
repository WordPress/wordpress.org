<?php
/**
 * Title: Page Developers/Add
 * Slug: wporg-plugins-2024/page-add
 * Inserter: no
 */

// Run the blocks used on this page to enqueue their style assets.
do_blocks( '<!-- wp:post-title {"level":1,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}}} /-->' );

?>
<h1 style="margin-bottom:var(--wp--preset--spacing--30);" class="wp-block-post-title"><?php esc_html_e( 'Add your plugin', 'wporg-plugins' ); ?></h1>

<div class="entry-content">
	<section>
		<div class="container">
			<p>
				<?php
				printf(
					/* translators: 1: URL to plugin guidelines, 2: URL to plugin developer FAQ. */
					wp_kses_post( __( 'Before you submit your plugin, we ask you to review our <a href="%1$s">Guidelines</a> and read the <a href="%2$s">Frequently Asked Questions</a>. A brief selection of common questions is available below the form.', 'wporg-plugins' ) ),
					esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/' ),
					esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/' )
				);
				?>
			</p>

			<?php echo do_shortcode( '[wporg-plugin-upload]' ); ?>

			<p>
				<?php
				printf(
					/* translators: URL to plugin guidelines. */
					wp_kses_post( __( 'Once submitted, your plugin will be manually reviewed for any common errors as well as ensuring it complies with <a href="%s">all the guidelines</a>.', 'wporg-plugins' ) ),
					esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/' )
				);
				?>
			</p>
		</div>
	</section>

	<section>
		<div class="container">
			<h2 id="faq" class="wp-block-heading"><?php esc_html_e( 'Frequently Asked Questions', 'wporg-plugins' ); ?></h2>

			<h3><?php esc_html_e( 'How long will the review process take?', 'wporg-plugins' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: URL to plugin developer FAQ. */
					wp_kses_post( __( 'This is in the <a href="%s">Developer FAQ</a>. It takes anywhere between 1 and 10 days. We attempt to review all plugins within 5 business days of submission, but the process takes as long as it takes, depending on the complexity of your plugin.', 'wporg-plugins' ) ),
					esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/' )
				);
				?>
			</p>

			<h3><?php _e( 'How can I expedite my plugin review?', 'wporg-plugins' ); ?></h3>
			<p>
				<?php
				printf(
					__( 'Your plugin review cannot be prioritized over others, to help us approve your plugin sooner, please ensure that you have read the <a href="%s">Security chapter</a> of the Plugin Handbook.', 'wporg-plugins' ),
					'https://developer.wordpress.org/apis/security/'
				);
				?>
			</p>
			<p><?php _e( 'Our three most common reasons for not approving a plugin are:', 'wporg-plugins' ); ?></p>
			<ul>
				<li><?php printf( __( 'The plugin contains unescaped output: <a href="%s">Learn about Escaping Data</a>', 'wporg-plugins' ), 'https://developer.wordpress.org/apis/security/escaping/' ); ?></li>
				<li><?php printf( __( 'The plugin accepts unsanitized data: <a href="%s">Learn about Sanitizing Data</a>', 'wporg-plugins' ), 'https://developer.wordpress.org/apis/security/sanitizing/' ); ?></li>
				<li><?php printf( __( 'The plugin processes form data without a nonce: <a href="%s">Learn about Nonces</a>', 'wporg-plugins' ), 'https://developer.wordpress.org/apis/security/nonces/' ); ?></li>
			</ul>
			<p><?php _e( 'If the code in your plugin falls into one of the above categories, <strong>your plugin will not be approved</strong>. The plugin review team will refer you back to these Handbook pages, adding further delay to the review process.', 'wporg-plugins' ); ?></p>

			<h3><?php esc_html_e( 'What will my plugin URL be?', 'wporg-plugins' ); ?></h3>
			<p><?php echo wp_kses_post( __( 'Your plugin&#8217;s URL will be populated based on the value of <code>Plugin Name</code> in your main plugin file (the one with the plugin headers). If you set yours as <code>Plugin Name: Boaty McBoatface</code> then your URL will be <code>https://wordpress.org/plugins/boaty-mcboatface</code> and your slug will be <code>boaty-mcboatface</code> for example. If there is an existing plugin with your name, then you will be <code>boaty-mcboatface-2</code> and so on. It behaves exactly like WordPress post names.', 'wporg-plugins' ) ); ?></p>
			<p><?php echo wp_kses_post( __( 'Once your plugin is approved, it <em>cannot</em> be renamed.', 'wporg-plugins' ) ); ?></p>

			<h3><?php esc_html_e( 'I made a mistake in my plugin name. Should I update it?', 'wporg-plugins' ); ?></h3>
			<p><?php
				printf(
					/* translators: %s: Email address */
					__( 'It depends on what you want to change. If you want to change the plugin <strong>display name</strong> you can update that by updating your plugin files in this page. If what you want to change is the <strong>permalink / slug</strong> of your plugin, you can do that ONCE before we begin with the review (if that&#8217;s available you&#8217;ll see a link to change it on this page). If this is not possible, you will need to contact us at %s, we can change it as long as the plugin has not yet been approved.', 'wporg-plugins' ),
					'<code>plugins@wordpress.org</code>'
				);
			?></p>

			<h3><?php esc_html_e( 'Why can&#8217;t I submit a plugin with certain display names?', 'wporg-plugins' ); ?></h3>
			<p><?php echo wp_kses_post( __( 'Certain plugin names are prohibited due to trademark abuse. Similarly, we prevent their use in plugin slugs entirely for your protection.', 'wporg-plugins' ) ); ?></p>
		</div>
	</section>
</div>