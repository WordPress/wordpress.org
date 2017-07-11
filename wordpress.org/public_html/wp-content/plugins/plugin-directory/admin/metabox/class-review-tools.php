<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

/**
 * The Plugin Review metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Review_Tools {
	static function display() {
		$post = get_post();

		$zip_files = array();
		foreach ( get_attached_media( 'application/zip', $post ) as $zip_file ) {
			$zip_files[ $zip_file->post_date ] = array(	wp_get_attachment_url( $zip_file->ID ), $zip_file );
		}
		uksort( $zip_files, function( $a, $b ) {
			return strtotime( $a ) < strtotime( $b );
		} );

		if ( $zip_url = get_post_meta( $post->ID, '_submitted_zip', true ) ) {
			// Back-compat only.
			$zip_files[ 'User provided URL' ] = array( $zip_url, null );
		}

		foreach ( $zip_files as $zip_date => $zip ) {
			list( $zip_url, $zip_file ) = $zip;
			$zip_size = ( is_object( $zip_file ) ? size_format( filesize( get_attached_file( $zip_file->ID ) ), 1 ) : __( 'unknown size', 'wporg-plugins' ) );
			printf( '<p>' . __( '<strong>Zip file:</strong> %s', 'wporg-plugins' ) . '</p>',
				sprintf( '%s <a href="%s">%s</a> (%s)', esc_html( $zip_date ), esc_url( $zip_url ), esc_html( $zip_url ), esc_html( $zip_size ) )
			);
		}

		if ( 'pending' != $post->post_status && 'new' != $post->post_status ) {
			echo "<ul>
				<li><a href='https://plugins.trac.wordpress.org/log/{$post->post_name}/'>" . __( 'Development Log', 'wporg-plugins' ) . "</a></li>
				<li><a href='https://plugins.svn.wordpress.org/{$post->post_name}/'>" . __( 'Subversion Repository', 'wporg-plugins' ) . "</a></li>
				<li><a href='https://plugins.trac.wordpress.org/browser/{$post->post_name}/'>" . __( 'Browse in Trac', 'wporg-plugins' ) . '</a></li>
			</ul>';
		}
		if ( $post->post_excerpt && in_array( $post->post_status, array( 'new', 'pending', 'approved' ) ) ) {
			echo '<p>' . strip_tags( $post->post_excerpt ) . '</p>';
		}

		add_filter( 'wp_comment_reply', function( $string ) use ( $post ) {
			$author = get_user_by( 'id', $post->post_author );
			
			$type   = 'Notice';
			if ( $post->post_status == 'new' || $post->post_status == 'pending' ) {
				$type = 'Request';
			}
			
			?>
			<form id="contact-author" class="contact-author" method="POST" action="https://supportpress.wordpress.org/plugins/thread-new.php">
				<input type="hidden" name="to_email" value="<?php echo esc_attr( $author->user_email ); ?>" />
				<input type="hidden" name="to_name" value="<?php echo esc_attr( $author->display_name ); ?>" />
				<input type="hidden" name="subject" value="<?php printf( esc_attr__( '[WordPress Plugin Directory] %s: %s', 'wporg-plugins' ), $type, $post->post_title ); ?>" />
				<button class="button button-primary" type="submit"><?php _e( 'Contact plugin author', 'wporg-plugins' ); ?></button>
			</form>
			<?php
			return $string;
		} );
	}
}

