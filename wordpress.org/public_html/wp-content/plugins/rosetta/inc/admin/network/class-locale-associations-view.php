<?php

namespace WordPressdotorg\Rosetta\Admin\Network;

use WordPressdotorg\Rosetta\Admin\Admin_Page;
use WordPressdotorg\Rosetta\Admin\Admin_Page_View;

class Locale_Associations_View implements Admin_Page_View {

	/**
	 * @var \WordPressdotorg\Rosetta\Admin\Network\Locale_Associations;
	 */
	private $page;

	/**
	 * Gets the title of the admin page.
	 *
	 * @return string The title.
	 */
	public function get_title() {
		return __( 'Locale Associations', 'rosetta' );
	}

	/**
	 * Sets associated page controller.
	 *
	 * @param \WordPressdotorg\Rosetta\Admin\Admin_Page $page The page instance.
	 */
	public function set_page( Admin_Page $page ) {
		$this->page = $page;
	}

	/**
	 * Renders the admin page.
	 */
	public function render() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->get_title() ); ?></h1>
			<p><?php _e( 'Manage the locale association for localized sites. Without an association the site&#8217;s front end is not available.', 'rosetta' ); ?></p>
			<p><a href="#add-new-associations"><?php _e( 'Add New Association', 'rosetta' ); ?></a> | <a  href="#existing-associations"><?php _e( 'Existing Associations', 'rosetta' ); ?></a> | <a href="#sites-without-locale-association"><?php _e( 'Sites Without Locale Association', 'rosetta' ); ?></a></p>
			<hr/>
			<?php
			$this->render_message();
			$this->render_form();
			$this->render_table();
			$this->render_sites_without_locale();
			?>
		</div>
		<?php
	}

	/**
	 * Renders a message for errors/updates.
	 */
	private function render_message() {
		if ( ! isset( $_GET['performed_action'] ) || ( ! isset( $_GET['updated'] ) && ! isset( $_GET['error'] ) ) ) {
			return;
		}

		$code = sprintf(
			'%s|%s',
			$_GET['performed_action'],
			isset( $_GET['updated'] ) ? $_GET['updated'] : $_GET['error']
		);

		switch ( $code ) {
			case 'add-association|nonce_failure' :
			case 'delete-association|nonce_failure' :
			case 'delete-association|delete_failure' :
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					__( 'An error occurred. Please try again.', 'rosetta' )
				);
				break;
			case 'add-association|missing_data' :
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					__( 'Please provide a locale and a subdomain.', 'rosetta' )
				);
				break;
			case 'add-association|locale_does_not_exist' :
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					__( 'The locale does not exist yet.', 'rosetta' )
				);
				break;
			case 'add-association|success' :
				printf(
					'<div class="notice notice-success"><p>%s</p></div>',
					__( 'The new association has been added.', 'rosetta' )
				);
				break;
			case 'delete-association|missing_data' :
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					__( 'The ID of the association is missing. Please try again.', 'rosetta' )
				);
				break;
			case 'delete-association|success' :
				printf(
					'<div class="notice notice-success"><p>%s</p></div>',
					__( 'The association has been deleted.', 'rosetta' )
				);
				break;
		}
	}

	/**
	 * Renders the add new form.
	 */
	private function render_form() {
		?>
		<h2 id="add-new-associations"><?php _e( 'Add New Association', 'rosetta' ); ?> <a href="#wpbody-content"><small>&uarr;</small></a></h2>
		<form action="" method="post">
			<?php wp_nonce_field( 'add-association' ); ?>
			<input type="hidden" name="action" value="add-association" />

			<p>
				<label for="locale"><?php _e( 'Locale:', 'rosetta' ); ?></label>
				<select id="locale" name="locale" required>
					<option value=""><?php _e( '&mdash; Select &mdash;', 'rosetta' ); ?></option>
					<?php
					foreach ( $this->page->get_available_wp_locales() as $locale ) {
						printf(
							'<option value="%s">%s</option>',
							esc_attr( $locale ),
							esc_html( $locale )
						);
					}
					?>
				</select>

				<label for="subdomain"><?php _e( 'Subdomain:', 'rosetta' ); ?></label>
				<input type="text" id="subdomain" name="subdomain" class="code" required />
			</p>

			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Add New Association', 'rosetta' ); ?>" />
			</p>
		</form>
		<?php
	}

	/**
	 * Renders the associations table.
	 */
	private function render_table() {
		?>
		<h2 id="existing-associations"><?php _e( 'Existing Associations', 'rosetta' ); ?> <a href="#wpbody-content"><small>&uarr;</small></a></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php _e( 'Locale', 'rosetta' ); ?></th>
					<th><?php _e( 'Subdomain', 'rosetta' ); ?></th>
					<th><?php _e( 'Sites', 'rosetta' ); ?></th>
					<th><?php _e( 'Latest Release', 'rosetta' ); ?></th>
					<th>&mdash;</th>
				</tr>
			</thead>
			<tbody id="the-list">
				<?php foreach ( $this->page->get_associations() as $association ) : ?>
					<tr>
						<td><?php echo esc_html( $association->locale ); ?></td>
						<td><?php echo esc_html( $association->subdomain ); ?></td>
						<td>
							<?php
							$sites = get_sites( [
								'lang_id'    => $association->locale_id,
								'network_id' => get_current_network_id(),
								'orderby'    => 'path_length',
							] );
							if ( $sites ) {
								echo '<ul>';
								foreach ( $sites as $site ) {
									printf(
										'<li><a href="%s">%s (%s)</a></li>',
										esc_url( $site->home ),
										esc_html( $site->blogname ),
										esc_html( $site->domain . $site->path )
									);
								}
								echo '</ul>';
							} else {
								echo '&mdash;';
							}
							?>
						</td>
						<td><?php echo esc_html( $association->latest_release ?: '&mdash;' ); ?></td>
						<td>
							<form action="" method="post">
								<?php wp_nonce_field( 'delete-association-' . $association->locale_id ); ?>
								<input type="hidden" name="action" value="delete-association" />
								<input type="hidden" name="id" value="<?php echo esc_attr( $association->locale_id ); ?>"/>
								<input type="submit" class="button delete" value="<?php esc_attr_e( 'Delete', 'rosetta' ); ?>" />
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders a list of sites without a locale association.
	 */
	public function render_sites_without_locale() {
		?>
		<h2 id="sites-without-locale-association"><?php _e( 'Sites Without Locale Association', 'rosetta' ); ?> <a href="#wpbody-content"><small>&uarr;</small></a></h2>
		<?php
		$domains_with_locale = wp_list_pluck( $this->page->get_associations(), 'subdomain' );
		array_walk( $domains_with_locale, function( &$domain ) {
			$domain = "$domain.wordpress.org";
		} );

		$sites = get_sites( [
			'network_id'     => get_current_network_id(),
			'path'           => '/',
			'number'         => '', // All.
			'domain__not_in' => $domains_with_locale
		] );

		if ( $sites ) {
			echo '<ul>';
			foreach ( $sites as $site ) {
				printf(
					'<li><a href="%s">%s (%s)</a></li>',
					esc_url( $site->home ),
					esc_html( $site->blogname ),
					esc_html( $site->domain . $site->path )
				);
			}
			echo '</ul>';
		} else {
			echo '&mdash;';
		}
	}
}
