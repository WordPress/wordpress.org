<h1 class="discussion-heading"><?php echo esc_html( $original->singular ); ?></h1>

<?php if ( $string_translation ) : ?>
	<h2>Translation: <?php echo esc_html( $string_translation ); ?></h2>
<?php endif; ?>

<?php if ( $original_translation_permalink ) : ?>
<a href="<?php echo esc_url( $original_translation_permalink ); ?>">View translation</a>
<?php endif; ?>

<div class="discussion-wrapper">
	<?php if ( $number = count( $comments ) ) : ?>
		<h4>
			<?php
			/* translators: number of comments. */
			printf( _n( '%s Comment', '%s Comments', $number ), number_format_i18n( $number ) );
			?>
		<?php if ( $original_translation_permalink ) : ?>
			<span class="comments-selector">
				<a href="<?php echo esc_html( $original_permalink ); ?>">Original Permalink page</a>
				<?php foreach ( $locales_with_comments as $locale_with_comments ) : ?>
					
					<a class="<?php echo esc_attr( $locale_with_comments == $locale_slug ? 'active-locale-link' : '' ); ?>" href="<?php echo esc_attr( $args['original_permalink'] . $locale_with_comments . '/default' ); ?>">
						| <?php echo esc_html( $locale_with_comments ); ?>
					</a>
				<?php endforeach; ?>
			</span>
		<?php endif; ?>
		</h4>
	<?php endif; ?>
	<?php gp_tmpl_load( 'comment-section', get_defined_vars() ); ?>
</div>
