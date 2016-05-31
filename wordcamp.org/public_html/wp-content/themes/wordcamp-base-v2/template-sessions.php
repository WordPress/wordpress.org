<?php
/**
 * @deprecated (template name was Sessions)
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site will use a
 * different template.
 *
 * @package WCBS
 * @since WCBS 1.0
 */
$sessions = wcb_session_query();
get_header(); ?>

		<div id="primary" class="site-content">
			<div id="content" role="main">

				<div class="callout lead"><?php
				if ( have_posts() ):
					the_post();
					the_content();
				endif; ?>
				</div>

				<div class="cpt-loop sessions"><?php
				while ( wcb_have_sessions() ):
					wcb_the_session();
					?>
					<div id="post-<?php the_ID(); ?>" <?php post_class( 'session' ); ?>>
						<h3 class="entry-title session-title"><a href="<?php the_permalink(); ?>" title="<?php printf( esc_attr__( 'Permalink to %s', 'wordcamporg' ), the_title_attribute( 'echo=0' ) ); ?>" rel="bookmark"><?php the_title(); ?></a></h3>
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
				endwhile; ?>
				</div><!-- .cpt-loop -->

			</div><!-- #content -->
		</div><!-- #primary .site-content -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>