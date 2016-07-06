<?php
/**
 * Reference Template: Changelog
 *
 * @package wporg-developer
 * @subpackage Reference
 */

namespace DevHub;

$changelog_data = get_changelog_data();
if ( ! empty( $changelog_data ) ) :
	?>
	<hr/>
	<section class="changelog">
		<h3><?php _e( 'Changelog', 'wporg' ); ?></h3>
		<ul>
			<?php foreach ( $changelog_data as $version => $data ) : ?>
				<li>
					<strong><?php _e( 'Since:', 'wporg' ); ?></strong>
					<?php printf(
					/* translators: %s: WordPress version */
						__( 'WordPress %s', 'wporg' ),
						sprintf( '<a href="%1$s">%2$s</a>', esc_url( $data['since_url'] ), esc_html( $version ) )
					); ?>
					<?php echo $data['description']; // escaped in get_changelog_data() ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

