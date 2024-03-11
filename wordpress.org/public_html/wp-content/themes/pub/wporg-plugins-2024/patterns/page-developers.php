<?php
/**
 * Title: Page Developers
 * Slug: wporg-plugins-2024/page-developers
 * Inserter: no
 */

// Run the blocks used on this page to enqueue their style assets.
do_blocks( '<!-- wp:post-title {"level":1,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|30"}}}} /-->' );

?>
<div class="alignwide">
	<h1 style="margin-bottom:var(--wp--preset--spacing--30);" class="wp-block-post-title"><?php esc_html_e( 'Developer Information', 'wporg-plugins' ); ?></h1>

	<div class="entry-content">
		<p>
			<?php
			/* translators: URL to plugin submission form. */
			printf( wp_kses_post( __( 'You&#8217;ve got a cool new plugin and are hoping to give it some exposure. You&#8217;re in the right place. Just <a href="%s">ask us to host it for you</a>. You&#8217;ll be able to:', 'wporg-plugins' ) ), esc_url( home_url( 'developers/add/' ) ) );
			?>
		</p>
		<ul>
			<li><?php esc_html_e( 'Keep track of how many people have downloaded it.', 'wporg-plugins' ); ?></li>
			<li><?php esc_html_e( 'Let people leave comments about your plugin.', 'wporg-plugins' ); ?></li>
			<li><?php esc_html_e( 'Get your plugin rated against all the other cool WordPress plugins.', 'wporg-plugins' ); ?></li>
			<li><?php esc_html_e( 'Give your plugin lots of exposure in this centralized repository.', 'wporg-plugins' ); ?></li>
		</ul>

		<h3><?php esc_html_e( 'There are some restrictions', 'wporg-plugins' ); ?></h3>
		<ul>
			<li>
				<?php
				/* translators: URL to licence list. */
				printf( wp_kses_post( __( 'Your plugin must be compatible with the <a href="%s">GNU General Public License v2</a>, or any later version. We strongly recommend using the same license as WordPress — &#8220;GPLv2 or later.&#8221;', 'wporg-plugins' ) ), esc_url( 'https://www.gnu.org/licenses/license-list.html#GPLCompatibleLicenses' ) );
				?>
			</li>
			<li><?php esc_html_e( 'The plugin must not do anything illegal or be morally offensive (that&#8217;s subjective, we know).', 'wporg-plugins' ); ?></li>
			<li>
				<?php
				/* translators: URL to Subversion. */
				printf( wp_kses_post( __( 'You have to actually use the <a href="%s">Subversion</a> repository we give you in order for your plugin to show up on this site. The WordPress Plugin Directory is a hosting site, not a listing site.', 'wporg-plugins' ) ), esc_url( 'https://subversion.apache.org/' ) );
				?>
			</li>
			<li><?php esc_html_e( 'The plugin must not embed external links on the public site (like a &#8220;powered by&#8221; link) without explicitly asking the user&#8217;s permission.', 'wporg-plugins' ); ?></li>
			<li>
				<?php
				/* translators: URL to plugin guidelines. */
				printf( wp_kses_post( __( 'Your plugin must abide by our list of <a href="%s">detailed guidelines</a>, which include not being a spammer and not abusing the systems.', 'wporg-plugins' ) ), esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/' ) );
				?>
			</li>
		</ul>

		<h3><?php esc_html_e( 'Submission is Simple', 'wporg-plugins' ); ?></h3>
		<ol>
			<li>
				<?php
				/* translators: URL to registration form. */
				printf( wp_kses_post( __( '<a href="%s">Sign up</a> for an account on WordPress.org.', 'wporg-plugins' ) ), esc_url( wp_registration_url() ) );
				?>
			</li>
			<li>
				<?php
				/* translators: URL to plugin submission form. */
				printf( wp_kses_post( __( '<a href="%s">Submit your plugin for review</a>.', 'wporg-plugins' ) ), esc_url( home_url( 'developers/add/' ) ) );
				?>
			</li>
			<li>
				<?php
				/* translators: URL to submission FAQ. */
				printf( wp_kses_post( __( 'After your plugin is <a href="%s">manually reviewed</a>, it will either be approved or you will be emailed and asked to provide more information and/or make corrections.', 'wporg-plugins' ) ), esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/#questions-about-submissions-and-approval' ) );
				?>
			</li>
			<li>
				<?php
				/* translators: URL to Subversion how-to page. */
				printf( wp_kses_post( __( 'Once approved, you&#8217;ll be given access to a <a id="subversion" href="%s">Subversion Repository</a> where you&#8217;ll store your plugin.', 'wporg-plugins' ) ), esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/' ) );
				?>
			</li>
			<li>
				<?php
				/* translators: 1: URL to readme section; 2: URL to home page; */
				printf( wp_kses_post( __( 'Shortly after you upload your plugin (and a <a href="%1$s">readme file</a>!) to that repository, it will be automatically displayed in the <a href="%2$s">plugins browser</a>.', 'wporg-plugins' ) ), '#readme', esc_url( home_url( '/' ) ) );
				?>
			</li>
			<li>
				<?php
				/* translators: URL to developer FAQ. */
				printf( wp_kses_post( __( 'Check out the <strong><a href="%s">FAQ</a></strong> for more information.', 'wporg-plugins' ) ), esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/' ) );
				?>
			</li>
		</ol>

		<h3 id="readme"><?php esc_html_e( 'Readme files', 'wporg-plugins' ); ?></h3>
		<p>
			<?php
			/* translators: 1: URL to readme file; 2: URL to readme validator; */
			printf( wp_kses_post( __( 'To make your entry in the plugin browser most useful, each plugin should have a readme file named <code>readme.txt</code> that adheres to the <a href="%1$s">WordPress plugin readme file standard</a>. You can put your readme file through the <a href="%2$s">readme validator</a> to check it.', 'wporg-plugins' ) ), esc_url( home_url( 'readme.txt' ) ), esc_url( home_url( '/developers/readme-validator/' ) ) );
			?>
		</p>
	</div>
</div>