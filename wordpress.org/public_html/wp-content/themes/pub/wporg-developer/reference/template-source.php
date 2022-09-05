<?php
/**
 * Reference Template: Source Information
 *
 * @package wporg-developer
 * @subpackage Reference
 */

namespace DevHub;

$source_file = get_source_file();
if ( ! empty( $source_file ) ) :
	$source_code = post_type_has_source_code() ? get_source_code() : '';
	?>
	<hr />
	<section class="source-content">
		<h2><?php _e( 'Source', 'wporg' ); ?></h2>
		<p>
			<?php
			printf(
				__( 'File: %s.', 'wporg' ),
				'<code>' . esc_html( $source_file ) . '</code>'
			);
			?>

			<?php
			printf(
				'<a href="%s">%s</a>',
				esc_url( get_source_file_archive_link( $source_file ) ),
				__( 'View all references', 'wporg' )
			);
			?>
		</p>

		<?php if ( ! empty( $source_code ) ) : ?>
			<?php
				echo do_blocks(
					sprintf(
						'<!-- wp:code {"lineNumbers":true} --><pre class="wp-block-code" data-start="%1$s" aria-label="%2$s"><code lang="php" class="language-php line-numbers">%3$s</code></pre><!-- /wp:code -->',
						esc_attr( get_post_meta( get_the_ID(), '_wp-parser_line_num', true ) ),
						__( 'Function source code', 'wporg' ),
						htmlentities( $source_code )
					)
				);
			?>

			<p class="source-code-links">
				<span><a href="<?php echo get_source_file_link(); ?>"><?php _e( 'View on Trac', 'wporg' ); ?></a></span>
				<span><a href="<?php echo get_github_source_file_link(); ?>"><?php _e( 'View on GitHub', 'wporg' ); ?></a></span>
			</p>
		<?php else : ?>
			<p>
				<a href="<?php echo get_source_file_link(); ?>"><?php _e( 'View on Trac', 'wporg' ); ?></a>
			</p>
		<?php endif; ?>
	</section>
<?php endif; ?>
