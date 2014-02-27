<?php get_header(); ?>

	<div class="homebox grid_12">

		<?php if (have_posts()) : while (have_posts()) : the_post(); ?>

			<div class="post">

				<h3 id="post-<?php the_ID(); ?>"><?php the_title(); ?></h3>

				<?php the_content(); ?>

				<?php
					// Components
					$terms = get_terms( 'component' );
					$count = count( $terms );

					if ( $count > 0 ) {
						echo '<div style="width: 24%; float: left;">';
						echo '<h2>Components</h2>';
						echo '<ul>';
						foreach ( $terms as $term ) {
							echo '<li><a href="' . esc_url( 'http://codex.bbpress.org/component/' . $term->slug ) . '" title="' . esc_attr( sprintf( __( 'View all pages for: %s' ), $term->name ) ) . '">' . esc_html( $term->name ) . '</a></li>';
						}
						echo '</ul>';
						echo '</div>';
					}

				?>

				<?php
					// Versions
					$terms = get_terms( 'version', array( 'order' => 'DESC' ) );
					$count = count( $terms );

					if ( $count > 0 ) {
						echo '<div style="width: 24%; float: left;">';
						echo '<h2>Versions</h2>';
						echo '<ul>';
						foreach ( $terms as $term ) {
							echo '<li><a href="' . esc_url( 'http://codex.bbpress.org/version/' . $term->slug ) . '" title="' . esc_attr( sprintf( __( 'View all pages for: %s' ), $term->name ) ) . '">' . esc_html( $term->name ) . '</a></li>';
						}
						echo '</ul>';
						echo '</div>';
					}

				?>

				<?php
					// Type
					$terms = get_terms( 'type' );
					$count = count( $terms );

					if ( $count > 0 ) {
						echo '<div style="width: 24%; float: left;">';
						echo '<h2>Types</h2>';
						echo '<ul>';
						foreach ( $terms as $term ) {
							echo '<li><a href="' . esc_url( 'http://codex.bbpress.org/type/' . $term->slug ) . '" title="' . esc_attr( sprintf( __( 'View all pages for: %s' ), $term->name ) ) . '">' . esc_html( $term->name ) . '</a></li>';
						}
						echo '</ul>';
						echo '</div>';
					}

				?>

				<?php
					// Context
					$terms = get_terms( 'context' );
					$count = count( $terms );

					if ( $count > 0 ) {
						echo '<div style="width: 24%; float: left;">';
						echo '<h2>Contexts</h2>';
						echo '<ul>';
						foreach ( $terms as $term ) {
							echo '<li><a href="' . esc_url( 'http://codex.bbpress.org/context/' . $term->slug ) . '" title="' . esc_attr( sprintf( __( 'View all pages for: %s' ), $term->name ) ) . '">' . esc_html( $term->name ) . '</a></li>';
						}
						echo '</ul>';
						echo '</div>';
					}

				?>

			</div>

		<?php endwhile; endif; ?>

	</div>

		<?php
			global $post;

			$args         = array( 'order' => 'ASC', );
			$revisions    = wp_get_post_revisions( get_queried_object_id(), $args );	
			$post_authors = array( $post->post_author => 1 );
			foreach( (array)$revisions as $revision ) {
				$post_authors[$revision->post_author] += 1;
			}
			asort( $post_authors, SORT_NUMERIC );

			global $codex_contributors;
			$codex_contributors = array_reverse( $post_authors, true );

		?>

		<?php locate_template( array( 'sidebar.php' ), true ); ?>

	</div>

<?php get_footer();