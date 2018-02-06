<?php
/**
 * User mapping class.
 *
 * @package WPImporterMapUsers
 */

/**
 * Class WordPress_Map_Users_Import
 */
class WordPress_Map_Users_Import extends WP_Import {

	/**
	 * The schema for mapping old authors to new authors.
	 *
	 * @var array
	 */
	public $author_mapping = [];

	/**
	 * Authors that could not be mapped.
	 *
	 * @var array
	 */
	protected $unmatched_authors = [];

	/**
	 * Displays an introductory text and an upload form.
	 */
	public function greet() {
		echo '<div class="narrow">';
		echo '<p>' . esc_html__( 'Howdy! Upload your WordPress eXtended RSS (WXR) file and we&#8217;ll import the posts, pages, comments, custom fields, categories, and tags into this site.', 'wordpress-importer' ) . '</p>';
		echo '<p>' . esc_html__( 'Choose a WXR (.xml) file to upload, then click Upload file and import.', 'wordpress-importer' ) . '</p>';
		wp_import_upload_form( 'admin.php?import=wordpress-user-map&amp;step=1' );
		echo '</div>';
	}

	/**
	 * Displays pre-import options, author importing/mapping and option to fetch attachments.
	 */
	public function import_options() {
		$this->get_author_mapping();

		$matched = count( $this->authors ) - count( $this->unmatched_authors );
		?>
		<form action="<?php echo esc_url( admin_url( 'admin.php?import=wordpress-user-map&amp;step=2' ) ); ?>" method="post">
			<?php wp_nonce_field( 'import-wordpress' ); ?>
			<input type="hidden" name="import_id" value="<?php echo esc_attr( $this->id ); ?>"/>

			<?php if ( $this->unmatched_authors ) : ?>
			<h3><?php esc_html_e( 'Map Authors' ); ?></h3>
			<script>
				jQuery( function( $ ) {
					$( '.same-name' ).change( function() {
						var checkbox = $( this );

						if ( checkbox.prop( 'checked' ) ) {
							checkbox.parent().prev().val( checkbox.attr( 'value' ) );
						} else if ( checkbox.parent().prev().val() === checkbox.attr( 'value' ) ) {
							checkbox.parent().prev().val( '' );
						}
					} );

					$( '.gimme-a-box' ).change( function() {
						var checked = $( this ).prop( 'checked' );

						$( '.map-users' ).toggle( ! checked );
						$( '.freeform-map' ).toggle( checked );
					} );
				} );
			</script>
			<p>
			<?php
			if ( $matched ) :
				/* translators: Number of matched authors. */
				printf( esc_html( _n( 'We were able to match %s author to a WordPress.org account using email addresses.', 'We were able to match %s authors to a WordPress.org account using email addresses.', $matched ) ), esc_html( $matched ) );
			else :
				esc_html_e( 'We couldn&#8217;t find any users to match. You&#8217;ll have to do it live.', 'wordpress-importer' );
			endif; // $matched.
			?>
			</p>
			<strong><?php esc_html_e( 'Specify a catch-all user for any users that do not have a username specified below:', 'wordpress-importer' ); ?></strong>
			<?php
			wp_dropdown_users( [
				'name'            => 'user_catchall',
				'show_option_all' => __( '- Select -', 'wordpress-importer' ),
			] );
			?>

			<div class="map-users">
				<p><?php esc_html_e( 'Map individual users:', 'wordpress-importer' ); ?></p>
				<?php
				foreach ( $this->unmatched_authors as $author ) :
					$format = $author['author_display_name'] ? '%s (%s)' : '%s';
				?>
				<p>
					<strong><?php printf( esc_html( $format ), esc_html( $author['author_login'] ), esc_html( $author['author_display_name'] ) ); ?></strong><br />
					<?php esc_html_e( 'Map to WP.org username:', 'wordpress-importer' ); ?>
					<input type="text" name="unmatched_authors[<?php echo esc_attr( $author['author_id'] ); ?>]" />
					<label>
						<input value="<?php echo esc_attr( $author['author_login'] ); ?>" class="same-name" type="checkbox" />
						<?php esc_html_x( 'Same', 'map to same author', 'wordpress-importer' ); ?>
					</label>
				</p>
				<?php endforeach; // $this->unmatched_authors. ?>
			</div>
			<p>
				<label>
					<input type="checkbox" class="gimme-a-box" name="freeform"/>
					<?php esc_html_e( 'Nah man, let me freestyle this.', 'wordpress-importer' ); ?>
				</label>
			</p>
			<div class="freeform-map" style="display:none">
				<p class="description"><?php echo wp_kses_post( __( 'One mapping per line. Use the format <code>old username > new username</code>', 'wordpress-importer' ) ); ?></p>
				<textarea class="code large-text" rows="30" cols="40" name="freeform_user_map"></textarea>
			</div>
			<?php endif; // $this->unmatched_authors. ?>

			<?php if ( $this->allow_fetch_attachments() ) : ?>
			<h3><?php esc_html_e( 'Import Attachments', 'wordpress-importer' ); ?></h3>
			<p>
				<input type="checkbox" value="1" name="fetch_attachments" id="import-attachments"/>
				<label for="import-attachments"><?php esc_html_e( 'Download and import file attachments', 'wordpress-importer' ); ?></label>
			</p>
			<?php endif; ?>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Maps authors.
	 *
	 * @access protected
	 *
	 * @global wpdb $wpdb The WordPress database class.
	 */
	public function get_author_mapping() {
		global $wpdb;

		$old_emails_by_login       = [];
		$old_logins_by_email       = [];
		$old_logins_by_id          = [];
		$logins_by_lowercase_login = [];

		foreach ( $this->authors as $author_login => $author ) {
			$old_logins_by_email[ $author['author_email'] ]           = $author_login; // Case sensitive result.
			$old_logins_by_id[ $author['author_id'] ]                 = $author_login;
			$logins_by_lowercase_login[ strtolower( $author_login ) ] = $author_login;

			$old_emails_by_login[ strtolower( $author_login ) ] = $author['author_email']; // Case insensitive lookup.
		}

		$prepared_emails = array_map( 'esc_sql', $old_emails_by_login );
		$prepared_emails = "'" . implode( "', '", $prepared_emails ) . "'";

		// phpcs:disable WordPress.VIP, WordPress.CSRF.NonceVerification.NoNonceVerification
		// phpcs:ignore WordPress.WP.PreparedSQL.NotPrepared
		$old_emails_with_match = $wpdb->get_results( "SELECT user_email, ID FROM $wpdb->users WHERE user_email IN ($prepared_emails) ORDER BY ID", OBJECT_K );
		foreach ( $old_emails_with_match as $user_email => $user ) {
			$this->author_mapping[ $old_logins_by_email[ $user_email ] ] = $user->ID;
		}

		$this->unmatched_authors = array_diff_key( $this->authors, $this->author_mapping );
		ksort( $this->unmatched_authors );

		if ( ! isset( $_POST['user_catchall'] ) ) {
			return;
		}

		$catchall_user_id = absint( $_POST['user_catchall'] );

		$username_mapping = [];
		if ( ! empty( $_POST['freeform'] ) ) {
			$raw_mapping = $_POST['freeform_user_map'];
			$raw_mapping = explode( "\n", $raw_mapping );

			foreach ( $raw_mapping as $line ) {
				if ( empty( $line ) ) {
					continue;
				}

				$line                         = array_map( 'trim', explode( '>', $line, 2 ) );
				$username_mapping[ $line[0] ] = $line[1];
			}
		} else {
			$raw_mapping = $_POST['unmatched_authors'];
			foreach ( $raw_mapping as $old_id => $new_name ) {
				$username_mapping[ $old_logins_by_id[ $old_id ] ] = $new_name;
			}
		}

		// $username_mapping is arrays of array( 'old username' => 'new username' ).
		$new_usernames_prepared = array_map( 'esc_sql', $username_mapping );
		$new_usernames_prepared = "'" . implode( "', '", $new_usernames_prepared ) . "'";

		// phpcs:ignore WordPress.WP.PreparedSQL.NotPrepared
		$new_usernames_with_match = $wpdb->get_results( "SELECT user_login, ID FROM $wpdb->users WHERE user_login IN ($new_usernames_prepared) ORDER BY ID", OBJECT_K );
		$new_usernames_with_match = array_change_key_case( $new_usernames_with_match );

		// phpcs:enable

		foreach ( $username_mapping as $old_username => $new_username ) {
			$old_username = strtolower( $old_username );

			if ( isset( $new_usernames_with_match[ strtolower( $new_username ) ] ) && isset( $logins_by_lowercase_login[ $old_username ] ) ) {
				$this->author_mapping[ $logins_by_lowercase_login[ $old_username ] ] = $new_usernames_with_match[ $new_username ]->ID;
			}
		}

		if ( $catchall_user_id ) {
			foreach ( $this->unmatched_authors as $old_username => $author ) {
				if ( ! isset( $this->author_mapping[ $old_username ] ) ) {
					$this->author_mapping[ $old_username ] = $catchall_user_id;
				}
			}
		}
	}
}
