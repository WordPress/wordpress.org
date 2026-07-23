<?php
namespace Wporg\TranslationEvents\Theme_2024;

use GP_Locales;
use Wporg\TranslationEvents\Project\Project_Repository;




register_block_type(
	'wporg-translate-events-2024/event-projects',
	array(
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( array $attributes ) {
			if ( ! isset( $attributes['id'] ) ) {
				return '';
			}
			$event_id = $attributes['id'];
			$projects = ( new Project_Repository() )->get_for_event( $event_id );
			if ( empty( $projects ) ) {
				return '';
			}

			ob_start();
			?>
			<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"medium","fontFamily":"inter"} -->
			<h4 class="wp-block-heading has-inter-font-family has-medium-font-size" style="font-style:normal;font-weight:700"><?php echo esc_html( __( 'Projects', 'wporg-translate-events-2024' ) ); ?></h4>
			<!-- /wp:heading -->
			<ul>
				<?php foreach ( $projects as $project_name => $row ) : ?>
				<li class="event-project" title="<?php echo esc_html( str_replace( ',', ', ', $row->locales ) ); ?>">
					<?php
					$row_locales = array();
					foreach ( explode( ',', $row->locales ) as $_locale ) {
						$_locale       = GP_Locales::by_slug( $_locale );
						$row_locales[] = '<a href="' . esc_url( gp_url_project_locale( $row->project, $_locale->slug, 'default' ) ) . '">' . esc_html( $_locale->english_name ) . '</a>';
					}
					echo wp_kses_post(
						wp_sprintf(
							// translators: 1: Project translated. 2: List of languages. 3: Number of contributors.
							_n(
								'%1$s to %2$l by %3$d contributor',
								'%1$s to %2$l by %3$d contributors',
								$row->users,
								'wporg-translate-events-2024'
							),
							'<a href="' . esc_url( gp_url_project( $row->project ) ) . '">' . esc_html( $project_name ) . '</a>',
							$row_locales,
							$row->users
						)
					);
					?>
				</li>
			<?php endforeach; ?>
			</ul>
				<?php
				return ob_get_clean();
		},
	)
);
