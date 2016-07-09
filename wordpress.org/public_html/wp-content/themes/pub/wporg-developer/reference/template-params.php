<?php
/**
 * Reference Template: Parameters
 *
 * @package wporg-developer
 * @subpackage Reference
 */

namespace DevHub;

if ( $params = get_params() ) :
	?>
	<hr />
	<section class="parameters">
		<h3><?php _e( 'Parameters', 'wporg' ); ?></h3>
		<dl>
			<?php foreach ( $params as $param ) : ?>
				<?php if ( ! empty( $param['variable'] ) ) : ?>
					<dt><?php echo esc_html( $param['variable'] ); ?></dt>
				<?php endif; ?>
				<dd>
					<p class="desc">
						<?php if ( ! empty( $param['types'] ) ) : ?>
							<span class="type"><?php printf( __( '(%s)', 'wporg' ), wp_kses_post( $param['types'] ) ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $param['required'] ) && 'wp-parser-hook' !== get_post_type() ) : ?>
							<span class="required"><?php printf( __( '(%s)', 'wporg' ), esc_html( $param['required'] ) ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $param['content'] ) ) : ?>
							<span class="description"><?php echo wp_kses_post( $param['content'] ); ?></span>
						<?php endif; ?>
					</p>
					<?php if ( ! empty( $param['default'] ) ) : ?>
						<p class="default"><?php _e( 'Default value:', 'wporg' );?> <?php echo htmlentities( $param['default'] ); ?></p>
					<?php endif; ?>
				</dd>
			<?php endforeach; ?>
		</dl>
	</section>
<?php endif; ?>
