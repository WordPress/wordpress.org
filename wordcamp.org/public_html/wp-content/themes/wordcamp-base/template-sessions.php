<?php
/**
 * @deprecated (template name was Sessions)
 *
 * A custom page template that provides a list of sessions.
 */

$structure = wcb_get('structure');
$structure->full_width_content();

$sessions = wcb_session_query();

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

				<div class="cpt-loop sessions"><?php

				$half_id = wcb_optimal_column_split( $sessions, 200 );
				// Open the first column
				echo '<div class="grid_6 alpha">';

				while ( wcb_have_sessions() ):
					wcb_the_session();

					// Close the first column, open the second.
					if ( get_the_ID() == $half_id )
						echo '</div><div class="grid_6 omega">';

					?>
					<div id="post-<?php the_ID(); ?>" <?php post_class( 'session' ); ?>>
						<h3 class="entry-title session-title"><a href="<?php the_permalink(); ?>" title="<?php printf( esc_attr__( 'Permalink to %s', 'wordcampbase' ), the_title_attribute( 'echo=0' ) ); ?>" rel="bookmark"><?php the_title(); ?></a></h3>
						<div class="entry-meta session-speakers">
							<?php wcb_entry_meta(); ?>
						</div>
						<div class="entry-content session-description"><?php
							if ( has_post_thumbnail() )
								the_post_thumbnail();
							the_content(); ?>
						</div>
					</div>
					<?php
				endwhile;

				// Close the second column
				echo '</div>'; ?>
				</div>
				<?php wcb_print_sharing(); ?>
			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>