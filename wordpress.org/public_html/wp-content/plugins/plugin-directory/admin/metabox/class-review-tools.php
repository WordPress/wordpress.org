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
		$plugin_slug = $post->post_name;

		$zip_files = get_attached_media( 'application/zip', $post );
		if ( $zip_files && 'pending' == $post->post_status ) {
			$zip_file = current( $zip_files );
			$zip_url = wp_get_attachment_url( $zip_file->ID );
			printf(
				'<p>' . __( '<strong>Zip file:</strong> %s', 'wporg-plugins' ) . '</p>',
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( $zip_url ),
					esc_html( $zip_url )
				)
			);
		}

		echo "<ul>
			<li><a href='https://plugins.trac.wordpress.org/log/{$plugin_slug}/'>" . __( 'Development Log', 'wporg-plugins' ) . "</a></li>
			<li><a href='https://plugins.svn.wordpress.org/{$plugin_slug}/'>" . __( 'Subversion Repository', 'wporg-plugins' ) . "</a></li>
			<li><a href='https://plugins.trac.wordpress.org/browser/{$plugin_slug}/'>" . __( 'Browse in Trac', 'wporg-plugins' ) . '</a></li>
		</ul>';

	}
}

