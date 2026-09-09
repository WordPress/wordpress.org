<?php
/**
 * View showing the translation team of a single locale.
 *
 * @package WordPressdotorg\I18nTeams
 */

?>
<p><a href="<?php echo esc_url( get_permalink() ); ?>"><?php esc_html_e( '&larr; All locales', 'wporg' ); ?></a></p>
<div id="locale-header">
	<h1>
		<?php echo esc_html( $locale->native_name ); ?>

		<?php if ( $locale->native_name != $locale->english_name ) : ?>
			/ <?php echo esc_html( $locale->english_name ); ?>
		<?php endif; ?>
	</h1>

	<ul id="locale-details">
		<li>
			<strong><?php esc_html_e( 'Sites:', 'wporg' ); ?></strong>
			<?php
			if ( $locale_data['sites'] ) :
				echo implode( ', ', array_map( function( $site ) {
					return sprintf(
						'<a href="%s">%s (%s)</a>',
						esc_url( $site->home ),
						esc_html( $site->blogname ),
						esc_html( $site->domain . $site->path )
					);
				},  $locale_data['sites'] ) );
			else : ?>
				&mdash;
			<?php endif; ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Latest release:', 'wporg' ); ?></strong>
			<?php echo $locale_data['latest_release'] ? $locale_data['latest_release'] : '&mdash;'; ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'WordPress Locale:', 'wporg' ); ?></strong>
			<?php echo esc_html( $locale->wp_locale ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'GlotPress Locale Code:', 'wporg' ); ?></strong>
			<?php echo esc_html( $locale->slug ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Translation Projects:', 'wporg' ); ?></strong>
			<a href="https://translate.wordpress.org/locale/<?php echo $locale->slug; ?>">translate.wordpress.org/locale/<?php echo $locale->slug; ?></a>
		</li>
	</ul>

	<?php if ( $locale_data['localized_core_url'] ) : ?>
		<div id="locale-download" class="wp-block-buttons is-layout-flex wp-block-buttons-is-layout-flex">
			<div class="wp-block-button">
				<a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $locale_data['localized_core_url'] ); ?>">
					<?php
					// translators: %s is the english variant of the locale name.
					printf( esc_html__( 'Download WordPress in %s', 'wporg' ), esc_html( $locale->english_name ) );
					?>
				</a>
			</div>
			<?php if ( $locale_data['language_pack_url'] ) : ?>
				<div class="wp-block-button is-style-outline">
					<a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $locale_data['language_pack_url'] ); ?>" role="button">
						<?php
						// translators: %s is the latest version.
						printf( esc_html__( 'Download language pack (%s)', 'wporg' ), esc_html( $locale_data['language_pack_version'] ) );
						?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	<?php endif;  ?>
</div>

<?php if ( ! empty( $locale_data['locale_managers'] ) ) : ?>
	<?php /* translators: %s: Number of locale managers. */ ?>
	<h2><?php printf( esc_html__( 'Locale Managers (%s)', 'wporg' ), esc_html( number_format_i18n( count( $locale_data['locale_managers'] ) ) ) ); ?></h2>

	<ul class="validators">
		<?php foreach ( $locale_data['locale_managers'] as $locale_manager ) :
			?>
			<li>
				<a class="profile" href="https://profiles.wordpress.org/<?php echo esc_attr( $locale_manager['nice_name'] ); ?>/"><?php
					echo get_avatar( $locale_manager['email'], 60 );
					echo esc_html( $locale_manager['display_name'] );
					?></a>
				<?php
				if ( $locale_manager['slack'] ) {
					printf( '<span class="user-slack">@%s on <a href="%s">Slack</a></span>', esc_html( $locale_manager['slack'] ), 'https://make.wordpress.org/chat/' );
				}
				?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<?php if ( ! empty( $locale_data['validators'] ) ) : ?>
	<?php /* translators: %s: Number of general translation editors. */ ?>
	<h2><?php printf( esc_html__( 'General Translation Editors (%s)', 'wporg' ), esc_html( number_format_i18n( count( $locale_data['validators'] ) ) ) ); ?></h2>

	<ul class="validators">
		<?php foreach ( $locale_data['validators'] as $validator ) :
			?>
			<li>
				<a class="profile" href="https://profiles.wordpress.org/<?php echo esc_attr( $validator['nice_name'] ); ?>/"><?php
					echo get_avatar( $validator['email'], 60 );
					echo esc_html( $validator['display_name'] );
				?></a>
				<?php
				if ( $validator['slack'] ) {
					printf( '<span class="user-slack">@%s on <a href="%s">Slack</a></span>', esc_html( $validator['slack'] ), 'https://make.wordpress.org/chat/' );
				}
				?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<?php if ( ! empty( $locale_data['project_validators'] ) ) : ?>
	<?php /* translators: %s: Number of project translation editors. */ ?>
	<h2><?php printf( esc_html__( 'Project Translation Editors (%s)', 'wporg' ), esc_html( number_format_i18n( count( $locale_data['project_validators'] ) ) ) ); ?></h2>

	<ul class="validators project-validators">
		<?php foreach ( $locale_data['project_validators'] as $validator ) :
			?>
			<li>
				<a class="profile" href="https://profiles.wordpress.org/<?php echo esc_attr( $validator['nice_name'] ); ?>/"><?php
					echo get_avatar( $validator['email'], 40 );
					echo esc_html( $validator['display_name'] );
				?></a>
				<?php
				if ( $validator['slack'] ) {
					printf( '<span class="user-slack">@%s on <a href="%s">Slack</a></span>', esc_html( $validator['slack'] ), 'https://make.wordpress.org/chat/' );
				}
				?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<?php if ( ! empty( $locale_data['translators'] ) ) : ?>
	<?php /* translators: %s: Number of current contributors. */ ?>
	<h2><?php printf( esc_html__( 'Current Translation Contributors (%s)', 'wporg' ), esc_html( number_format_i18n( count( $locale_data['translators'] ) ) ) ); ?></h2>

	<p>
		<?php
		$translators = array();
		foreach ( $locale_data['translators'] as $translator ) {
			$translators[] = sprintf(
				'<a href="https://profiles.wordpress.org/%s/">%s</a>',
				esc_attr( $translator['nice_name'] ),
				esc_html( $translator['display_name'] )
			);
		}
		echo wp_sprintf( '%l.', $translators );
		?>
	</p>
<?php endif; ?>

<?php if ( ! empty( $locale_data['translators_past'] ) ) : ?>
	<?php /* translators: %s: Number of past contributors. */ ?>
	<h2><?php printf( esc_html__( 'Past Translation Contributors (%s)', 'wporg' ), esc_html( number_format_i18n( count( $locale_data['translators_past'] ) ) ) ); ?></h2>

	<p>
		<?php
		$translators = array();
		foreach ( $locale_data['translators_past'] as $translator ) {
			$translators[] = sprintf(
				'<a href="https://profiles.wordpress.org/%s/">%s</a>',
				esc_attr( $translator['nice_name'] ),
				esc_html( $translator['display_name'] )
			);
		}
		echo wp_sprintf( '%l.', $translators );
		?>
	</p>
<?php endif; ?>

<?php
$notice = sprintf(
	'%s <a href="https://translate.wordpress.org/locale/%s">%s</a>',
	__( 'Is this a language that you speak?', 'wporg' ),
	esc_attr( $locale->slug ),
	sprintf(
		/* translators: %s: language name in English */
		__( 'Join the WordPress translation team for %s!', 'wporg' ),
		esc_html( $locale->english_name )
	)
);
echo do_shortcode( "[info]{$notice}[/info]" );
