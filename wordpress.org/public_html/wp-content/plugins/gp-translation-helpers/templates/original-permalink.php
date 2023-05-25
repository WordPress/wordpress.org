<?php
$breadcrumbs = array(
	gp_project_links_from_root( $project ),
);

if ( $translation_set ) {
	/* translators: 1: Translation set name, 2: Project name. */
	gp_title( sprintf( __( 'Discussion &lt; %1$s &lt; %2$s &lt; GlotPress', 'glotpress' ), $translation_set->name, $project->name ) );
	$breadcrumbs[] = $translation_set->name;
} else {
	/* translators: Project name. */
	gp_title( sprintf( __( 'Discussion &lt; %s &lt; GlotPress', 'glotpress' ), $project->name ) );
}

gp_breadcrumb( $breadcrumbs );
gp_enqueue_scripts( array( 'gp-editor', 'gp-translations-page', 'gp-translation-discussion-js' ) );
wp_localize_script(
	'gp-translations-page',
	'$gp_translations_options',
	array(
		'sort'   => __( 'Sort', 'glotpress' ),
		'filter' => __(
			'Filter',
			'glotpress'
		),
	)
);
gp_enqueue_style( 'gp-discussion-css' );
gp_tmpl_header();

?>

<div id="original" class="clear">
<h1>
	<?php
	if ( $original->plural ) {
		esc_html_e( 'Singular: ' );
	}

	echo esc_translation( $original->singular );
	if ( $original->plural ) {
		echo '<br />';
		esc_html_e( 'Plural: ' );
		echo esc_translation( $original->plural );
	}
	?>
</h1>
<?php
		$generate_permalink = function ( $translation_id ) use ( $project, $locale_slug, $translation_set_slug, $original_id ) {
			return GP_Route_Translation_Helpers::get_translation_permalink(
				$project,
				$locale_slug,
				$translation_set_slug,
				$original_id,
				$translation_id
			);
		}
		?>
<?php if ( $translation ) : ?>
	<?php $translation_permalink = $generate_permalink( $translation->id ); ?>
	<p>
	
		<?php echo esc_html( ucfirst( $translation->status ) ); ?> translation:
		<?php
		if ( ( '' == $translation->translation_1 ) && ( '' == $translation->translation_2 ) &&
				   ( '' == $translation->translation_3 ) && ( '' == $translation->translation_4 ) &&
				   ( '' == $translation->translation_5 ) ) :
			?>
			<strong><?php echo $translation_permalink ? gp_link( $translation_permalink, esc_translation( $translation->translation_0 ) ) : esc_translation( $translation->translation_0 ); ?></strong>
		<?php else : ?>
			<ul id="translation-list">
			<?php for ( $i = 0; $i <= 5; $i++ ) : ?>
				<?php if ( '' != $translation->{'translation_' . $i} ) : ?>
					<li>
						<?php echo $translation_permalink ? gp_link( $translation_permalink, esc_translation( $translation->{'translation_' . $i} ) ) : esc_translation( $translation->{'translation_' . $i} ); ?>
					</li>
				<?php endif ?>
			<?php endfor ?>
			</ul>
		<?php endif ?>
	</p>
<?php elseif ( $existing_translations ) : ?>
	<?php foreach ( $existing_translations as $e ) : ?>
		<p>
		<?php $translation_permalink = $generate_permalink( $e->id ); ?>
			<?php echo esc_html( ucfirst( $e->status ) ); ?> translation:
			<?php
			if ( ( '' == $e->translation_1 ) && ( '' == $e->translation_2 ) &&
					   ( '' == $e->translation_3 ) && ( '' == $e->translation_4 ) &&
					   ( '' == $e->translation_5 ) ) :
				?>
				<strong><?php echo $translation_permalink ? gp_link( $translation_permalink, esc_translation( $e->translation_0 ) ) : esc_translation( $e->translation_0 ); ?></strong>
			<?php else : ?>
				<ul id="translation-list">
					<?php for ( $i = 0; $i <= 5; $i++ ) : ?>
						<?php if ( '' != $e->{'translation_' . $i} ) : ?>
							<li>
								<?php echo $translation_permalink ? gp_link( $translation_permalink, esc_translation( $e->{'translation_' . $i} ) ) : esc_translation( $e->{'translation_' . $i} ); ?>
							</li>
						<?php endif ?>
					<?php endfor ?>
				</ul>
			<?php endif ?>
		</p>
	<?php endforeach; ?>
<?php elseif ( $translation_set ) : ?>
	<?php
	$translate_url = GP_Route_Translation_Helpers::get_translation_permalink(
		$project,
		$locale_slug,
		$translation_set_slug,
		$original_id
	);
	?>
	<p>
		<a href="<?php echo esc_url( $translate_url ); ?>"><?php esc_html_e( 'This string has no translation in this language.' ); ?></a>
	</p>
<?php endif; ?>
<div class="translations" row="<?php echo esc_attr( $row_id . ( $translation ? '-' . $translation->id : '' ) ); ?>" replytocom="<?php echo esc_attr( gp_get( 'replytocom' ) ); ?>" >
<div class="translation-helpers">
	<nav>
		<ul class="helpers-tabs">
			<?php
			$is_first_class = 'current';
			foreach ( $sections as $section ) {
				// TODO: printf.
				echo "<li class='{$is_first_class}' data-tab='{$section['id']}'>" . esc_html( $section['title'] ) . '<span class="count">' . esc_html( $section['count'] ? ( '(' . $section['count'] . ')' ) : '' ) . '</span></li>'; // phpcs:ignore: XSS OK.
				$is_first_class = '';
			}
			?>
		</ul>
	</nav>
	<?php
	$is_first_class = 'current';
	foreach ( $sections as $section ) {
		printf( '<div class="%s helper %s %s" id="%s" data-helper="%s">', esc_attr( $section['classname'] ), esc_attr( $is_first_class ), $section['load_inline'] ? 'loaded' : '', esc_attr( $section['id'] ), esc_attr( $section['helper'] ) );
		if ( $section['has_async_content'] ) {
			echo '<div class="async-content">';
		}

		echo $section['content']; // phpcs:ignore XSS OK.
		if ( $section['has_async_content'] ) {
			echo '</div>';
		}
		echo '</div>';
		$is_first_class = '';
	}
	?>
</div>
</div>
</div>
<?php

gp_tmpl_footer();
