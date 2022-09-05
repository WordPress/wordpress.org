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
		<h2><?php _e( 'Parameters', 'wporg' ); ?></h2>
		<dl>
			<?php foreach ( $params as $param ) : ?>
				<?php if ( ! empty( $param['variable'] ) ) : ?>
					<dt>
						<code><?php echo esc_html( $param['variable'] ); ?></code>
						<?php if ( ! empty( $param['types'] ) ) : ?>
							<span class="type"><?php echo wp_kses_post( $param['types'] ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $param['required'] ) && 'wp-parser-hook' !== get_post_type() ) : ?>
							<span class="required"><?php echo esc_html( $param['required'] ); ?></span>
						<?php endif; ?>
					</dt>
				<?php endif; ?>
				<dd>
					<div class="desc">
						<?php if ( ! empty( $param['content'] ) ) : ?>
							<?php if ( $extra = get_param_reference( $param ) ) : ?>
								<span class="description"><?php echo wp_kses_post( $param['content'] ); ?></span>
								<details class="extended-description">
									<summary>
										<?php echo esc_html( sprintf( __( 'More Arguments from %s( ... %s )', 'wporg' ), $extra[ 'parent' ], $extra['parent_var'] ) ); ?>
									</summary>
									<span class="description"><?php echo wp_kses_post( $extra['content'] ); ?></span>
								</details>
							<?php else : ?>
								<span class="description"><?php echo wp_kses_post( $param['content'] ); ?></span>
							<?php endif; ?>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $param['default'] ) ) : ?>
						<p class="default"><?php _e( 'Default:', 'wporg' );?> <code><?php echo htmlentities( $param['default'] ); ?></code></p>
					<?php endif; ?>
				</dd>
			<?php endforeach; ?>
		</dl>
	</section>
<?php endif; ?>
