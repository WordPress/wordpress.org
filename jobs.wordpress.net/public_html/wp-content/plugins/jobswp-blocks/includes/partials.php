<?php
/**
 * The job form partials.
 *
 * The jobswp plugin prints its Post a Job form, the success notice and the
 * submitted job's preview with get_template_part( 'content', … ), which looks
 * in the theme. A block theme has no such files, so this plugin supplies
 * them. It also prints the Remove a Job form and its notice on that page. A theme that ships
 * its own partial still wins.
 *
 * @package jobswp-blocks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prints the plugin's partial when the theme has none.
 *
 * A partial that prints nothing (a notice with no state to report) gets no
 * wrapper, so it leaves no empty block in the page's layout.
 *
 * @param string $slug Template slug ("content").
 * @param string $name Template name.
 */
function jobswp_blocks_template_part_fallback( $slug, $name ) {
	$partials = array( 'post-job', 'post-job-success', 'single', 'remove-job', 'remove-job-notice' );
	if ( ! in_array( $name, $partials, true ) || locate_template( "content-{$name}.php" ) ) {
		return;
	}

	ob_start();
	include JOBSWP_BLOCKS_DIR . "templates/content-{$name}.php";
	$output = ob_get_clean();
	if ( '' === trim( $output ) ) {
		return;
	}

	echo '<div class="alignwide">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The partials escape their own output.
}
add_action( 'get_template_part_content', 'jobswp_blocks_template_part_fallback', 10, 2 );

/**
 * Gives the feedback form's submit input the button element class.
 *
 * The jobswp plugin's [jobswp-contact-form] prints a bare submit input. With
 * `wp-element-button` the theme's button element styles it, as it does the
 * Post a Job and Remove a Job submits.
 *
 * @param string $output Shortcode output.
 * @param string $tag    Shortcode name.
 * @return string
 */
function jobswp_blocks_contact_form_button( $output, $tag ) {
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
add_filter( 'do_shortcode_tag', 'jobswp_blocks_contact_form_button', 10, 2 );

/**
 * Renders the submitted job's preview with the block theme's single-job design.
 *
 * After a job is submitted, the jobswp plugin prints content-single.php for
 * it below the thank-you notice. In a block theme the single job's design is
 * the `single-job` template, so its main content is rendered here for the
 * current post. The template's parts and its `main` wrapper are left out.
 *
 * @return string Rendered markup, or an empty string when the theme has no such template.
 */
function jobswp_blocks_single_job_preview() {
	if ( ! wp_is_block_theme() ) {
		return '';
	}

	$template = get_block_template( get_stylesheet() . '//single-job' );
	if ( ! $template || empty( $template->content ) ) {
		return '';
	}

	/*
	 * The job is still awaiting moderation, and core's post date binding
	 * returns nothing for posts the visitor can't read yet. Give the date
	 * block the submission date directly, unlinked, as the draft's
	 * permalink isn't public.
	 */
	$submission_date = static function ( $parsed_block ) {
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
function jobswp_blocks_remove_job_form( $content ) {
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
add_filter( 'the_content', 'jobswp_blocks_remove_job_form', 12 );

/**
 * Starts capturing the Post a Job form.
 *
 * The jobswp plugin echoes the form from its the_content filter at priority 12,
 * so the submitted values never pass through later the_content filters. A classic
 * theme prints as it renders, but a block theme renders the page into a
 * string first, so the echoed form would land before the document. This
 * buffers around jobswp's filter and appends the form after every other
 * the_content filter, which keeps jobswp's guarantee.
 *
 * @param string $content Page content.
 * @return string
 */
function jobswp_blocks_post_job_form_start( $content ) {
	if ( wp_is_block_theme() && is_page( 'post-a-job' ) ) {
		ob_start();
		$GLOBALS['jobswp_blocks_post_job_buffers'] = ( $GLOBALS['jobswp_blocks_post_job_buffers'] ?? 0 ) + 1;
	}
	return $content;
}
add_filter( 'the_content', 'jobswp_blocks_post_job_form_start', 11 );

/**
 * Appends the captured Post a Job form to the content.
 *
 * @param string $content Page content.
 * @return string
 */
function jobswp_blocks_post_job_form_end( $content ) {
	if ( empty( $GLOBALS['jobswp_blocks_post_job_buffers'] ) ) {
		return $content;
	}
	--$GLOBALS['jobswp_blocks_post_job_buffers'];
	return $content . ob_get_clean();
}
add_filter( 'the_content', 'jobswp_blocks_post_job_form_end', PHP_INT_MAX );

if ( ! function_exists( 'jobswp_posted_on' ) ) {
	/**
	 * "Posted <date>" line used by the submitted job's preview. The classic
	 * theme defined it; kept under its name for the partials.
	 */
	function jobswp_posted_on() {
		$date_link = sprintf(
			'<a href="%1$s" rel="bookmark"><time class="entry-date published" datetime="%2$s">%3$s</time></a>',
			esc_url( get_permalink() ),
			esc_attr( get_the_date( 'c' ) ),
			esc_html( get_the_date() )
		);

		echo '<span class="posted-on">';
		printf(
			/* translators: %s: Linked post date. */
			esc_html__( 'Posted %s', 'jobswp' ),
			$date_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from escaped parts.
		);
		echo '</span>';
	}
}
