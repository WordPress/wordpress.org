<?php namespace DevHub;
/**
 * @package wporg-developer
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<h2 class="entry-title"><a href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?></a></h2>
	</header><!-- .entry-header -->

	<div class="entry-content">
		<?php the_excerpt();
		$cmd_slug = str_replace( 'wp ', '', get_the_title() );
		$github_issues = 'https://github.com/issues?q=is%3Aopen+label%3A' . urlencode( 'command:' . str_replace( ' ', '-', $cmd_slug ) ) . '+sort%3Aupdated-desc+org%3Awp-cli';
		?>
		<p><a class="button" href="<?php echo esc_url( $github_issues ); ?>"><?php esc_html_e( 'GitHub Issues', 'wporg' ); ?></a></p>
		<?php the_content( __( 'Continue reading <span class="meta-nav">&rarr;</span>', 'wporg' ) ); ?>
		<?php
			wp_link_pages( array(
				'before' => '<div class="page-links">' . __( 'Pages:', 'wporg' ),
				'after'  => '</div>',
			) );
		?>
		<?php
		$children = get_children( array(
			'post_parent'    => get_the_ID(),
			'post_type'      => 'command',
			'posts_per_page' => 250,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		if ( $children ) : ?>
			<h3>SUBCOMMANDS</h3>
			<table>
				<thead>
				<tr>
					<th>Name</th>
					<th>Description</th>
				</tr>
				</thead>
				<tbody>
					<?php foreach( $children as $child ) : ?>
						<tr>
							<td><a href="<?php echo apply_filters( 'the_permalink', get_permalink( $child->ID ) ); ?>"><?php echo apply_filters( 'the_title', $child->post_title ); ?></a></td>
							<td><?php echo apply_filters( 'the_excerpt', $child->post_excerpt ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div><!-- .entry-content -->

	<footer class="entry-meta">
		<p><em>Command documentation is regenerated at every release. To add or update an example, please submit a pull request against the corresponding part of the codebase.</em></p>

		<?php edit_post_link( __( 'Edit', 'wporg' ), '<span class="edit-link">', '</span>' ); ?>
	</footer><!-- .entry-meta -->
</article><!-- #post-## -->
