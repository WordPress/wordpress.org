<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

/**
 * The Plugin Review metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Review_Tools {
	static function display() {
		$post      = get_post();
		$zip_files = get_attached_media( 'application/zip', $post );

		if ( $zip_files ) {
			$zip_file = current( $zip_files );
			$zip_url  = wp_get_attachment_url( $zip_file->ID );
			printf( '<p>' . __( '<strong>Zip file:</strong> %s', 'wporg-plugins' ) . '</p>',
				sprintf( '<a href="%s">%s</a>', esc_url( $zip_url ), esc_html( $zip_url ) )
			);
		}

		echo "<ul>
			<li><a href='https://plugins.trac.wordpress.org/log/{$post->post_name}/'>" . __( 'Development Log', 'wporg-plugins' ) . "</a></li>
			<li><a href='https://plugins.svn.wordpress.org/{$post->post_name}/'>" . __( 'Subversion Repository', 'wporg-plugins' ) . "</a></li>
			<li><a href='https://plugins.trac.wordpress.org/browser/{$post->post_name}/'>" . __( 'Browse in Trac', 'wporg-plugins' ) . '</a></li>
		</ul>';

	}
}

