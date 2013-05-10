<?php
/**
 * @deprecated (template name was Sponsors)
 *
 * A custom page template that provides a list of sponsors.
 */

wcb_suppress_sharing();

get_header(); ?>

		<div id="container">
			<div id="content" role="main">
				<div class="sponsor-intro"><?php
				if ( have_posts() ):
					the_post(); ?>
					<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
						<h1 class="entry-title"><?php the_title(); ?></h1>
						<div class="entry-content"><?php the_content(); ?></div>
					</div>
				<?php endif; ?>
				</div>

				<div class="sponsors"><?php

				$terms = wcb_get_option('sponsor_level_order');

				foreach ( $terms as $term ):
					$sponsors = wcb_sponsor_query( array(
						'taxonomy' => $term->taxonomy,
						'term'     => $term->slug,
					) );

					if ( ! wcb_have_sponsors() )
						continue;

					// Open sponsor level ?>
					<div <?php wcb_sponsor_level_class( $term ); ?>>
					<h2 class="sponsor-level-title"><?php echo esc_html( $term->name ); ?></h2><?php

					while ( wcb_have_sponsors() ):
						wcb_the_sponsor();
						?>
						<div id="post-<?php the_ID(); ?>" <?php post_class( 'sponsor' ); ?>>
							<h3 class="entry-title sponsor-title"><a href="<?php the_permalink(); ?>" title="<?php printf( esc_attr__( 'Permalink to %s', 'wordcampbase' ), the_title_attribute( 'echo=0' ) ); ?>" rel="bookmark"><?php
								if ( has_post_thumbnail() )
									the_post_thumbnail();
								else
									the_title();
							?></a></h3>
							<div class="entry-content sponsor-description"><?php
								the_content(); ?>
							</div>
						</div>
						<?php
					endwhile;

					// Close sponsor level. ?>
					</div><?php
				endforeach; ?>
				</div>
				<?php wcb_print_sharing(); ?>
			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>