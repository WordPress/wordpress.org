<?php
/**
 * Template part for displaying the plugin administration sections.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;
?>

<div id="admin" class="section">
    <?php
    $status  = get_post_status();
    if ( in_array( $status, array( 'closed', 'disabled' ) ) ) {
	    echo '<div class="plugin-notice notice notice-error notice-alt"><p>' . __( 'This plugin has been closed and is no longer available for download.', 'wporg-plugins' ) . '</p></div>';
	    if ( get_current_user_id() == get_post()->post_author ) {
		    echo '<div class="plugin-notice notice notice-info notice-alt"><p>' .
		                        sprintf(
		                        /* translators: 1: plugins@wordpress.org */
			                        __( 'If you did not request this change, please contact <a href="mailto:%1$s">%1$s</a> for a status. All developers with commit access are contacted when a plugin is closed, with the reasons why, so check your spam email too.', 'wporg-plugins' ),
			                        'plugins@wordpress.org'
		                        ) . '</p></div><!-- .plugin-notice -->';
	    }
    }

    ?>
	<h2><?php _e( 'Plugin Stats', 'wporg-plugins' ); ?></h2>

	<h4><?php _e( 'Active versions', 'wporg-plugins' ); ?></h4>
	<div id="plugin-version-stats" class="chart version-stats"></div>

	<h4><?php _e( 'Downloads Per Day', 'wporg-plugins' ); ?></h4>
	<div id="plugin-download-stats" class="chart download-stats"></div>

	<h4><?php _e( 'Active Install Growth', 'wporg-plugins' ); ?></h4>
	<div id="plugin-growth-stats" class="chart download-stats"></div>

	<h5><?php _e( 'Downloads history', 'wporg-plugins' ); ?></h5>
	<table id="plugin-download-history-stats" class="download-history-stats">
		<tbody></tbody>
	</table>

	<?php

		$tags = (array) get_post_meta( $post->ID, 'tagged_versions', true );
		// Sort the versions by version
		usort( $tags, 'version_compare' );
		// We'll want to add a Development Version if it exists
		$tags[] = 'trunk';

		// Remove the current version, this may be trunk.
		$tags = array_diff( $tags, array( get_post_meta( $post->ID, 'stable_tag', true ) ) );

		// List Trunk, followed by the most recent non-stable release.
		$tags = array_reverse( $tags );

		if ( $tags && 'publish' === get_post_status() ) {
			echo '<h5>' . __( 'Previous Versions', 'wporg-plugins' ) . '</h5>';

			echo '<div class="plugin-notice notice notice-info notice-alt"><p>' . __( 'Previous versions of this plugin may not be secure or stable and are available for testing purposes only.', 'wporg-plugins' ) . '</p></div>';

			echo '<select class="previous-versions" onchange="getElementById(\'download-previous-link\').href=this.value;">';
			foreach ( $tags as $version ) {
				$text = ( 'trunk' == $version ? __( 'Development Version', 'wporg-plugins' ) : $version );
				printf( '<option value="%s">%s</option>', esc_attr( Template::download_link( $post, $version ) ), esc_html( $text ) );
			}
			echo '</select> ';

			printf(
				'<a href="%s" id="download-previous-link" class="button">%s</a>',
				esc_url( Template::download_link( $post, reset( $tags ) ) ),
				__( 'Download', 'wporg-plugins' )
			);
		}

	?>
</div>
