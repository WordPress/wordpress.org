<?php
/**
 * The job form partials the Jobs plugin loads with get_template_part(), which a block theme can't supply.
 *
 * @package wporg-jobs-blocks
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Prints the plugin's partial when the theme has none.
 *
 * @param string      $slug Template slug ("content").
 * @param string|null $name Template name.
 */
function wporg_jobs_blocks_template_part_fallback( string $slug, ?string $name ): void {
	$partials = array( 'post-job', 'post-job-success', 'single', 'remove-job', 'remove-job-notice' );
	if ( ! in_array( $name, $partials, true ) || locate_template( "content-{$name}.php" ) ) {
		return;
	}

	ob_start();
	include WPORG_JOBS_BLOCKS_DIR . "templates/content-{$name}.php";
	$output = ob_get_clean();
	if ( '' === trim( $output ) ) {
		return;
	}

	echo '<div class="alignwide">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The partials escape their own output.
}
add_action( 'get_template_part_content', 'wporg_jobs_blocks_template_part_fallback', 10, 2 );

/**
 * Gives the feedback form's submit input the button element class.
 *
 * @param mixed  $output Shortcode output.
 * @param string $tag    Shortcode name.
 * @return mixed
 */
function wporg_jobs_blocks_contact_form_button( mixed $output, string $tag ): mixed {
	if ( 'jobswp-contact-form' !== $tag || ! is_string( $output ) ) {
		return $output;
	}

	$tags = new WP_HTML_Tag_Processor( $output );
	while ( $tags->next_tag( 'input' ) ) {
		if ( 'submit' === $tags->get_attribute( 'type' ) ) {
			$tags->add_class( 'wp-element-button' );
		}
	}

	return $tags->get_updated_html();
}
add_filter( 'do_shortcode_tag', 'wporg_jobs_blocks_contact_form_button', 10, 2 );

/**
 * Renders the submitted job's preview from the single-job template, without its parts and `main` wrapper.
 *
 * @return string Rendered markup, or an empty string when the theme has no such template.
 */
function wporg_jobs_blocks_single_job_preview(): string {
	if ( ! wp_is_block_theme() ) {
		return '';
	}

	$template = get_block_template( get_stylesheet() . '//single-job' );
	if ( ! $template || empty( $template->content ) ) {
		return '';
	}

	// Core's date block prints nothing for a job awaiting moderation, and its permalink isn't public.
	$submission_date = static function ( array $parsed_block ): array {
		if ( 'core/post-date' === $parsed_block['blockName'] && empty( $parsed_block['attrs']['datetime'] ) ) {
			$parsed_block['attrs']['datetime'] = get_the_date( 'c' );
			$parsed_block['attrs']['isLink']   = false;
		}
		return $parsed_block;
	};
	add_filter( 'render_block_data', $submission_date );

	$output = '';
	foreach ( parse_blocks( $template->content ) as $block ) {
		if ( 'core/group' !== $block['blockName'] || 'main' !== ( $block['attrs']['tagName'] ?? '' ) ) {
			continue;
		}
		foreach ( $block['innerBlocks'] as $inner_block ) {
			$output .= render_block( $inner_block );
		}
	}

	remove_filter( 'render_block_data', $submission_date );

	return $output;
}

/**
 * Adds the Remove a Job notice before that page's content and the form after it.
 *
 * @param string $content Page content.
 * @return string
 */
function wporg_jobs_blocks_remove_job_form( string $content ): string {
	static $done = false;
	if ( $done || ! wp_is_block_theme() || ! is_page( 'remove-a-job' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$done = true;

	ob_start();
	get_template_part( 'content', 'remove-job-notice' );
	$notice = ob_get_clean();

	ob_start();
	get_template_part( 'content', 'remove-job' );
	return $notice . $content . ob_get_clean();
}
add_filter( 'the_content', 'wporg_jobs_blocks_remove_job_form', 12 );

/**
 * Starts capturing the Post a Job form, which the Jobs plugin echoes and a block theme would print before the document.
 *
 * @param string $content Page content.
 * @return string
 */
function wporg_jobs_blocks_post_job_form_start( string $content ): string {
	if ( wp_is_block_theme() && is_page( 'post-a-job' ) ) {
		ob_start();
		$GLOBALS['wporg_jobs_blocks_post_job_buffers'] = ( $GLOBALS['wporg_jobs_blocks_post_job_buffers'] ?? 0 ) + 1;
	}
	return $content;
}
add_filter( 'the_content', 'wporg_jobs_blocks_post_job_form_start', 11 );

/**
 * Appends the captured form after every other the_content filter, so submitted values skip them.
 *
 * @param string $content Page content.
 * @return string
 */
function wporg_jobs_blocks_post_job_form_end( string $content ): string {
	if ( empty( $GLOBALS['wporg_jobs_blocks_post_job_buffers'] ) ) {
		return $content;
	}
	--$GLOBALS['wporg_jobs_blocks_post_job_buffers'];
	return $content . ob_get_clean();
}
add_filter( 'the_content', 'wporg_jobs_blocks_post_job_form_end', PHP_INT_MAX );

/**
 * Keeps wptexturize() out of textareas, so a submitted description comes back as typed.
 *
 * @param string[] $tags Elements wptexturize() skips.
 * @return string[]
 */
function wporg_jobs_blocks_no_texturize_textarea( array $tags ): array {
	$tags[] = 'textarea';
	return $tags;
}
add_filter( 'no_texturize_tags', 'wporg_jobs_blocks_no_texturize_textarea' );
