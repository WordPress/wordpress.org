<p><a href="<?php echo esc_url( get_permalink() ); ?>"><?php _e( '&larr; All locales', 'wporg' ); ?></a></p>
<div id="locale-header">
	<h1>
		<?php echo esc_html( $locale->native_name ); ?>

		<?php if ( $locale->native_name != $locale->english_name ) : ?>
			/ <?php echo esc_html( $locale->english_name ); ?>
		<?php endif; ?>
	</h1>

	<ul id="locale-details">
		<li>
			<strong><?php _e( 'Locale site:', 'wporg' ); ?></strong>
			<?php if ( $locale_data['rosetta_site_url'] ) : ?>
				<a href="<?php echo esc_url( $locale_data['rosetta_site_url'] ); ?>"><?php echo parse_url( $locale_data['rosetta_site_url'], PHP_URL_HOST ); ?></a>
			<?php else : ?>
				&mdash;
			<?php endif; ?>
		</li>
		<li>
			<strong><?php _e( 'Team site:', 'wporg' ); ?></strong>
			<?php if ( $locale_data['team_url'] ) : ?>
				<a href="<?php echo esc_url( $locale_data['team_url'] ); ?>"><?php
					$url = parse_url( $locale_data['team_url'] );
					printf( '%s%s', $url['host'], $url['path'] );
				?></a>
			<?php else : ?>
				&mdash;
			<?php endif; ?>
		</li>
		<li>
			<strong><?php _e( 'Forums:', 'wporg' ); ?></strong>
			<?php if ( $locale_data['forums_url'] ) : ?>
				<a href="<?php echo esc_url( $locale_data['forums_url'] ); ?>"><?php
					$url = parse_url( $locale_data['forums_url'] );
					printf( '%s%s', $url['host'], $url['path'] );
				?></a>
			<?php else : ?>
				&mdash;
			<?php endif; ?>
		</li>
		<li>
			<strong><?php _e( 'Latest release:', 'wporg' ); ?></strong>
			<?php echo $locale_data['latest_release'] ? $locale_data['latest_release'] : '&mdash;'; ?>
		</li>
		<li>
			<strong><?php _e( 'WordPress Locale:', 'wporg' ); ?></strong>
			<?php echo esc_html( $locale->wp_locale ); ?>
		</li>
		<li>
			<strong><?php _e( 'GlotPress Locale Code:', 'wporg' ); ?></strong>
			<?php echo esc_html( $locale->slug ); ?>
		</li>
		<li>
			<strong><?php _e( 'Translation Projects:', 'wporg' ); ?></strong>
			<a href="https://translate.wordpress.org/locale/<?php echo $locale->slug; ?>">translate.wordpress.org/locale/<?php echo $locale->slug; ?></a>
		</li>
	</ul>

	<?php if ( $locale_data['localized_core_url'] ) : ?>
		<ul id="locale-download">
			<li class="button download-button">
				<a href="<?php echo esc_url( $locale_data['localized_core_url'] ); ?>" role="button">
					<?php printf( __( 'Download WordPress in %s', 'wporg' ), $locale->english_name ); ?>
				</a>
			</li>

			<?php if ( $locale_data['language_pack_url'] ) : ?>
				<li class="button download-button">
					<a href="<?php echo esc_url( $locale_data['language_pack_url'] ); ?>" role="button">
						<?php
						// translators: %s is the latest version
						printf( __( 'Download language pack (%s)', 'wporg' ), $locale_data['language_pack_version'] );
						?>
					</a>
				</li>
			<?php endif; ?>
		</ul>
	<?php endif;  ?>
</div>


<h2><?php printf( __( 'General Translation Editors (%s)', 'wporg' ), number_format_i18n( count( $locale_data['validators'] ) ) ); ?></h2>

<?php if ( empty( $locale_data['validators'] ) ) : ?>
	<p><?php printf( __( '%s does not have any validators yet.', 'wporg' ), $locale->english_name ); ?></p>
<?php else : ?>
	<ul class="validators">
		<?php foreach ( $locale_data['validators'] as $validator ) :
			?>
			<li>
				<a class="profile" href="https://profiles.wordpress.org/<?php echo esc_attr( $validator['nice_name'] ); ?>"><?php
					echo get_avatar( $validator['email'], 60 );
					echo esc_html( $validator['display_name'] );
				?></a>
				<?php
				if ( $validator['slack'] ) {
					printf( '<span class="user-slack">@%s on <a href="%s">Slack</a></span>', $validator['slack'], 'https://make.wordpress.org/chat/' );
				}
				?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>


<?php if ( ! empty( $locale_data['project_validators'] ) ) : ?>
	<h2><?php printf( __( 'Project Translation Editors (%s)', 'wporg' ), number_format_i18n( count( $locale_data['project_validators'] ) ) ); ?></h2>

	<ul class="validators project-validators">
		<?php foreach ( $locale_data['project_validators'] as $validator ) :
			?>
			<li>
				<a class="profile" href="https://profiles.wordpress.org/<?php echo esc_attr( $validator['nice_name'] ); ?>"><?php
					echo get_avatar( $validator['email'], 40 );
					echo esc_html( $validator['display_name'] );
				?></a>
				<?php
				if ( $validator['slack'] ) {
					printf( '<span class="user-slack">@%s on <a href="%s">Slack</a></span>', $validator['slack'], 'https://make.wordpress.org/chat/' );
				}
				?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<h2><?php printf( __( 'All Translation Contributors (%s)', 'wporg' ), number_format_i18n( count( $locale_data['translators'] ) ) ); ?></h2>

<?php if ( empty( $locale_data['translators'] ) ) : ?>
	<p><?php printf( __( '%s does not have any translators yet.', 'wporg' ), $locale->english_name ); ?></p>
<?php else :?>
	<p>
		<?php
		$translators = array();
		foreach ( $locale_data['translators'] as $translator ) {
			$translators[] = sprintf(
				'<a href="https://profiles.wordpress.org/%s">%s</a>',
				esc_attr( $translator['nice_name'] ),
				esc_html( $translator['display_name'] )
			);
		}
		echo wp_sprintf( '%l.', $translators );
		?>
	</p>
<?php endif; ?>

<p class="alert alert-info" role="alert">
	<a href="https://translate.wordpress.org/locale/<?php echo esc_attr( $locale->slug ); ?>">
		<?php printf( __( 'Become a translator yourself, check if %s needs some help!', 'wporg' ), $locale->english_name ); ?>
	</a>
</p>
