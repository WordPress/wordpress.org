<?php
/**
 * @deprecated (template name was speakers)
 *
 * A custom page template that provides a list of speakers.
 */

$structure = wcb_get('structure');
$structure->full_width_content();

$speakers = wcb_speaker_query();

wcb_suppress_sharing();

get_header(); ?>

		<div id="container">
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

				$half_id = wcb_optimal_column_split( $speakers, 200, 200 );
				// Open the first column
				echo '<div class="grid_6 alpha">';

				while ( wcb_have_speakers() ):
					wcb_the_speaker();

					// Close the first column, open the second.
					if ( get_the_ID() == $half_id )
						echo '</div><div class="grid_6 omega">';

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

				// Close the second column
				echo '</div>';

				wcb_print_sharing();
				?>
				</div>
			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>