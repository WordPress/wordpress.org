<?php
/**
 * @deprecated (template name was Speakers)
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site will use a
 * different template.
 *
 * @package WCBS
 * @since WCBS 1.0
 */
$speakers = wcb_speaker_query();
get_header(); ?>

		<div id="primary" class="site-content">
			<div id="content" role="main">

				<div class="callout lead"><?php
				if ( have_posts() ):
					the_post();
					the_content();
				endif; ?>
				</div>

				<div class="cpt-loop speaker-gravatar-list clearfix"><?php
					while ( wcb_have_speakers() ):
						wcb_the_speaker();

						$href  = '#' . esc_attr( wcb_get_speaker_slug() );
						$title = esc_attr( get_the_title() );
						echo "<a href='$href' title='$title'>";
						echo wcb_get_speaker_gravatar( 48 );
						echo '</a>';
					endwhile;
					wcb_rewind_speakers();
				?></div>
				
				<div class="cpt-loop speakers"><?php
				while ( wcb_have_speakers() ):
					wcb_the_speaker();
					?>
					<div id="<?php echo esc_attr( wcb_get_speaker_slug() ); ?>" <?php post_class( 'speaker clearfix' ); ?>>
						<h3 class="entry-title speaker-name"><?php the_title(); ?></h3>
						<div class="entry-content speaker-bio"><?php
							echo wcb_get_speaker_gravatar( 102 );
							the_content(); ?>
						</div>
					</div>
					<?php
				endwhile;
				?>
				</div><!-- .cpt-loop -->

			</div><!-- #content -->
		</div><!-- #primary .site-content -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>