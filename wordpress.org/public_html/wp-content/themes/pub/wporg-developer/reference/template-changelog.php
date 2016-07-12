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
	<hr />
	<section class="changelog">
		<h3><?php _e( 'Changelog', 'wporg' ); ?></h3>

		<table>
			<caption class="screen-reader-text"><?php _e( 'Changelog', 'wporg' ); ?></caption>
			<thead>
				<tr>
					<th class="changelog-version"><?php _e( 'Version', 'wporg' ); ?></th>
					<th class="changelog-desc"><?php _e( 'Description', 'wporg' ); ?></th>
				</tr>
			</thead>

			<tbody>
				<?php
				$count = count( $changelog_data );
				$i = 0;

				$changelog_data = array_reverse( $changelog_data );

				foreach ( $changelog_data as $version => $data ) : ?>
					<?php
					// Add "Introduced." for the initial version description, last since the array is reversed.
					$data['description'] = ( $i == ( $count - 1 ) ) ? __( 'Introduced.', 'wporg' ) : $data['description'];

					$version_link = sprintf( '<a href="%1$s" alt="%2$s">%3$s</a>',
						esc_url( $data['since_url'] ),
						esc_attr( "WordPress {$version}" ),
						esc_html( $version )
					);

					$i++;
					?>

					<tr>
						<td><?php echo $version_link; ?></td>
						<td><?php echo $data['description']; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</section>
<?php endif; ?>

