<?php namespace DevHub; ?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

	<h1><a href="<?php the_permalink() ?>"><?php echo get_signature(); ?></a></h1>

	<section class="description">
		<?php the_excerpt(); ?>
	</section>

<?php if ( is_single() ) : ?>

	<section class="long-description">
		<?php the_content(); ?>
	</section>
	<section class="return"><p><strong>Return:</strong> <?php echo get_return(); ?></p></section>

	<?php
	$since = get_since();
if ( ! empty( $since ) ) : ?>
	<section class="since">
		<p><strong>Since:</strong> WordPress <a href="<?php echo get_since_link( $since ); ?>"><?php echo esc_html( $since ); ?></a></p>
	</section>
<?php endif; ?>

	<?php
	$source_file = get_source_file();
	if ( ! empty( $source_file ) ) : ?>
	<section class="source">
		<p><strong><?php _e( 'Source:', 'wporg' ); ?></strong> <a href="<?php echo get_source_file_link( $source_file ); ?>"><?php echo esc_html( $source_file ); ?></a></p>
	</section>
	<?php endif; ?>

<?php /* if ( is_archive() ) : ?>
	<section class="meta">Used by TODO | Uses TODO | TODO Examples</section>
<?php endif; */ ?>

	<!--
	<hr/>
	<section class="explanation">
		<h2><?php _e( 'Explanation', 'wporg' ); ?></h2>
	</section>
	-->
	
	<?php if ( $params = get_params() ) : ?>
	<hr/>
	<section class="parameters">
		<h2><?php _e( 'Parameters', 'wporg-developer' ); ?></h2>
		<dl>
			<?php foreach ( $params as $param ) : ?>
			<?php if ( ! empty( $param['variable'] ) ) : ?>
			<dt><?php echo esc_html( $param['variable'] ); ?></dt>
			<?php endif; ?>
			<dd>
				<p class="desc">
					<?php if ( ! empty( $param['types'] ) ) : ?>
					<span class="type">(<?php echo wp_kses_post( $param['types'] ); ?>)</span>
					<?php endif; ?>
					<?php if ( ! empty( $param['required'] ) ) : ?>
					<span class="required">(<?php echo esc_html( $param['required'] ); ?>)</span>
					<?php endif; ?>
					<?php if ( ! empty( $param['content'] ) ) : ?>
					<span class="description"><?php echo wp_kses_post( $param['content'] ); ?></span>
					<?php endif; ?>
				</p>
				<?php if ( ! empty( $param['default'] ) ) : ?>
				<p class="default"><?php _e( 'Default value:', 'wporg-developer' );?> <?php echo esc_html( $param['default'] ); ?></p>
				<?php endif; ?>
			</dd>
			<?php endforeach; ?>
		</dl>
	</section>
	<?php endif; ?>
	
	<?php if ( $arguments = get_arguments() ) : //todo: output arg data ?>
	<hr/>
	<section class="arguments">
		<h2><?php _e( 'Arguments', 'wporg' ); ?></h2>
	</section>
	<?php endif; ?>
	<!--
	<hr/>
	<section class="learn-more">
		<h2><?php _e( 'Learn More', 'wporg' ); ?></h2>
	</section>
	<hr/>
	<section class="examples">
		<h2><?php _e( 'Examples', 'wporg' ); ?></h2>
	</section>
	-->

	<?php if ( 'wp-parser-class' === get_post_type() ) :
		if ( $children = get_children( array( 'post_parent' => get_the_ID(), 'post_status' => 'publish' ) ) ) :
			usort( $children, __NAMESPACE__ . '\\compare_objects_by_name' );
	?>
		<hr/>
		<section class="class-methods">
		<h2><?php _e( 'Methods', 'wporg' ); ?></h2>
		<ul>
			<?php foreach ( $children as $child ) : ?>
				<li><a href="<?php echo get_permalink( $child->ID ); ?>">
				<?php
					$title = get_the_title( $child );
					$pos = ( $i = strrpos( $title, ':' ) ) ? $i + 1 : 0;
					echo substr( $title, $pos );
				?></a>
				<?php if ( $excerpt = apply_filters( 'get_the_excerpt', $child->post_excerpt ) ) {
					echo '&mdash; ' . sanitize_text_field( $excerpt );
				} ?>
				</li>
			<?php endforeach; ?>
		</ul>
		</section>
		<?php endif;
	endif; ?>

<?php endif; ?>

</article>
