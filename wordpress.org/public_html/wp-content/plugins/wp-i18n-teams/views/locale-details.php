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
			<strong><?php _e( 'Version:', 'wporg' ); ?></strong>
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
						<?php // translators: %s is the latest version ?>
						<?php printf( __( 'Download language pack (%s)', 'wporg' ), $locale_data['latest_release'] ); ?>
					</a>
				</li>
			<?php endif; ?>
		</ul>
	<?php endif;  ?>
</div>


<h2><?php _e( 'Translation Editors', 'wporg' ); ?></h2>

<?php if ( empty( $locale_data['validators'] ) ) : ?>
	<p><?php printf( __( '%s does not have any validators yet.', 'wporg' ), $locale->english_name ); ?></p>
<?php else : ?>
	<ul class="validators">
		<?php foreach ( $locale_data['validators'] as $validator ) : ?>
			<li>
				<a href="https://profiles.wordpress.org/<?php echo esc_attr( $validator[2] ); ?>">
					<img src="https://secure.gravatar.com/avatar/<?php echo esc_attr( $validator[1] ); ?>?size=60" class="gravatar" alt="<?php echo esc_attr( $validator[0] ); ?>" />
					<?php echo esc_html( $validator[0] ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>


<h2><?php _e( 'Translation Contributors', 'wporg' ); ?></h2>

<?php if ( empty( $locale_data['translators'] ) ) : ?>
	<p><?php printf( __( '%s does not have any translators yet.', 'wporg' ), $locale->english_name ); ?></p>
<?php else : ?>
	<ul>
		<?php foreach ( $locale_data['translators'] as $username => $name ) : ?>
			<li>
				<a href="https://profiles.wordpress.org/<?php echo esc_attr( $username ); ?>">
					<?php echo esc_html( $name ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<p class="alert alert-info" role="alert">
	<a href="https://translate.wordpress.org/languages/<?php echo esc_attr( $locale->slug ); ?>">
		<?php printf( __( 'Become a translator yourself, check if %s needs some help!', 'wporg' ), $locale->english_name ); ?>
	</a>
</p>
