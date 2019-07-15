<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;

use WordPressdotorg\Plugin_Directory\Plugin_I18n;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * A Widget to display meta information about a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Meta extends \WP_Widget {

	/**
	 * Meta constructor.
	 */
	public function __construct() {
		parent::__construct( 'plugin_meta', __( 'Plugin Meta', 'wporg-plugins' ), array(
			'classname'   => 'plugin-meta',
			'description' => __( 'Displays plugin meta information.', 'wporg-plugins' ),
		) );
	}

	/**
	 * Outputs the content of the widget.
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$post = get_post();

		echo $args['before_widget'];
		?>

		<h3 class="screen-reader-text"><?php echo apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Meta', 'wporg-plugins' ) : $instance['title'], $instance, $this->id_base ); ?></h3>

		<ul>
			<?php if ( $built_for = get_the_term_list( $post->ID, 'plugin_built_for', '', ', ' ) ) : ?>
				<li>
					<?php
					printf(
						/* translators: %s: term list */
						__( 'Designed to work with: %s', 'wporg-plugins' ),
						esc_html( $built_for )
					);
					?>
				</li>
			<?php endif; ?>

			<li>
				<?php
				printf(
					/* translators: %s: version number */
					__( 'Version: %s', 'wporg-plugins' ),
					'<strong>' . esc_html( get_post_meta( $post->ID, 'version', true ) ) . '</strong>'
				);
				?>
			</li>

			<li>
				<?php
				$modified_time = get_post_modified_time();

				// Fallback for approved plugins that are not published yet.
				if ( $modified_time < 0 ) {
					$modified_time = get_post_time();
				}

				printf(
					/* translators: %s: time since the last update */
					__( 'Last updated: %s', 'wporg-plugins' ),
					/* translators: %s: time since the last update */
					'<strong>' . wp_kses( sprintf( __( '%s ago', 'wporg-plugins' ), '<span>' . human_time_diff( $modified_time ) . '</span>' ), array( 'span' => true ) ) . '</strong>'
				);
				?>
			</li>
			<li>
				<?php
				printf(
					/* translators: %s: active installations count */
					__( 'Active installations: %s', 'wporg-plugins' ),
					'<strong>' . esc_html( Template::active_installs( false ) ) . '</strong>'
				);
				?>
			</li>

			<?php if ( $requires = (string) get_post_meta( $post->ID, 'requires', true ) ) : ?>
				<li>
					<?php esc_html_e( 'WordPress Version:', 'wporg-plugins' ); ?>
					<strong>
						<?php
						printf(
							/* translators: Minimum version number required. */
							esc_html__( '%s or higher', 'wporg-plugins' ),
							esc_html( $requires )
						);
						?>
					</strong>
				</li>
			<?php endif; ?>

			<?php if ( $tested_up_to = (string) get_post_meta( $post->ID, 'tested', true ) ) : ?>
				<li>
					<?php
					printf(
						/* translators: %s: version number */
						__( 'Tested up to: %s', 'wporg-plugins' ),
						'<strong>' . esc_html( $tested_up_to ) . '</strong>'
					);
					?>
				</li>
			<?php endif; ?>

			<?php if ( $requires_php = (string) get_post_meta( $post->ID, 'requires_php', true ) ) : ?>
				<li>
					<?php esc_html_e( 'PHP Version:', 'wporg-plugins' ); ?>
					<strong>
						<?php
						printf(
							/* translators: Minimum version number required. */
							esc_html__( '%s or higher', 'wporg-plugins' ),
							esc_html( $requires_php )
						);
						?>
					</strong>
				</li>
			<?php endif; ?>

			<?php
			$available_languages = $this->available_languages();
			if ( $available_languages ) :
				$available_languages_count = count( $available_languages );
				?>
				<li>
					<?php
					if ( 1 === $available_languages_count ) {
						esc_html_e( 'Language:', 'wporg-plugins' );
					} else {
						esc_html_e( 'Languages:', 'wporg-plugins' );
					}

					echo '<div class="languages">';

					if ( $available_languages_count > 1 ) :
						?>
						<button type="button" class="button-link popover-trigger" aria-expanded="false" data-target="popover-languages">
							<?php
							printf(
								/* translators: %s: Number of available languages */
								_nx( 'See all %s', 'See all %s', $available_languages_count, 'languages', 'wporg-plugins' ),
								$available_languages_count
							);
							?>
						</button>
						<div id="popover-languages" class="popover is-top-right">
							<div class="popover-arrow"></div>

							<button type="button" class="button-link popover-close" aria-label="<?php esc_attr_e( 'Close this popover', 'wporg-plugins' ); ?>">
								<?php _e( 'Close', 'wporg-plugins' ); ?>
							</button>

							<div class="popover-inner">
								<p><?php echo wp_sprintf( '%l.', $available_languages ); ?></p>
								<p>
								<?php
									printf(
										'<a href="%s">%s</a>',
										esc_url( 'https://translate.wordpress.org/projects/wp-plugins/' . $post->post_name ),
										__( 'Translate into your language', 'wporg-plugins' )
									);
								?>
								</p>
							</div>
						</div>
						<?php
					else :
						echo current( $available_languages );
					endif;

					echo '</div>';
					?>
				</li>
				<?php
			endif;
			?>

			<?php if ( empty( $args['hide_tags'] ) ) {
				$terms = get_the_terms( $post, 'plugin_tags' );
				if ( ! $terms || is_wp_error( $terms ) ) {
					$terms = array();
				}
				// Trim it to tags with more than 1 plugin.
				$terms = array_filter( $terms, function( $term ) {
					return $term->count > 1;
				} );

				// If we have some terms still, generate the sidebar entry.
				if ( $terms ) {
					$term_links = array_filter( array_map( function( $term ) {
						$link = get_term_link( $term, 'plugin_tags' );
						if ( is_wp_error( $link ) ) {
							return '';
						}
						return '<a href="' . esc_url( $link ) . '" rel="tag">' . $term->name . '</a>';
					}, $terms ) );

					echo '<li class="clear">';
					printf(
						/* translators: %s: tag list */
						_n( 'Tag: %s', 'Tags: %s', count( $term_links ), 'wporg-plugins' ),
						'<div class="tags">' . implode( $term_links ) . '</div>'
					);
					echo '</li>';
				}
			} ?>

			<?php if ( ! get_query_var( 'plugin_advanced' ) ) : ?>
				<li class="hide-if-no-js">
					<?php
					printf(
						'<strong><a class="plugin-admin" href="%s">%s</a></strong>',
						esc_url( get_permalink() . 'advanced/' ),
						__( 'Advanced View', 'wporg-plugins' )
					);
					?>
				</li>
			<?php endif; ?>
		</ul>

		<?php
		echo $args['after_widget'];
	}

	/**
	 * Retrieves available languages.
	 *
	 * @return array List of available languages.
	 */
	private function available_languages() {
		$post      = get_post();
		$slug      = $post->post_name;
		$locales   = Plugin_I18n::instance()->get_translations( $slug );
		$languages = [];

		if ( ! empty( $locales ) ) {
			$locale_names = wp_list_pluck( $locales, 'name', 'wp_locale' );
			$wp_locales   = wp_list_pluck( $locales, 'wp_locale' );

			$sites = get_sites( [
				'network_id' => WPORG_GLOBAL_NETWORK_ID,
				'public'     => 1,
				'path'       => '/',
				'locale__in' => $wp_locales,
				'number'     => '',
			] );

			if ( $sites ) {
				foreach ( $sites as $site ) {
					$languages[ $locale_names[ $site->locale ] ] = sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( "{$site->home}/plugins/{$slug}/" ),
						$locale_names[ $site->locale ]
					);
				}
			}
		}

		// We assume that the main language is English US, even though this
		// is not true for all plugins.
		if ( $languages || 'en_US' !== get_locale() ) {
			$languages['English (US)'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( "https://wordpress.org/plugins/{$slug}/" ),
				'English (US)'
			);
		}

		ksort( $languages, SORT_NATURAL );

		return $languages;
	}
}
