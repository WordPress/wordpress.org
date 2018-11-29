<?php

namespace CampTix\Badge_Generator;
defined( 'WPINC' ) || die();

/**
 * @var string $html_customizer_url
 * @var string $indesign_page_url
 * @var string $notify_tool_url
 */

?>

<p>
	<?php esc_html_e(
		'This tool will help you create personalized badges for attendees to wear during the event. There are two methods for this, depending on your preferences:',
		'wordcamporg'
	); ?>
</p>

<div id="cbg-method-overviews">
	<div id="html-badge-overview">
		<h2>
			<?php esc_html_e( 'HTML and CSS', 'wordcamporg' ); ?>
		</h2>

		<ul class="ul-disc">
			<li><?php esc_html_e( 'The Easiest method.',                                                                              'wordcamporg' ); ?></li>
			<li><?php esc_html_e( 'Can be as simple as using the default design and printing at home.',                               'wordcamporg' ); ?></li>
			<li><?php esc_html_e( 'Design is customizable by a designer or developer, but options are limited compared to InDesign.', 'wordcamporg' ); ?></li>
		</ul>

		<a class="button button-primary" href="<?php echo esc_url( $html_customizer_url ); ?>">
			<?php esc_html_e( 'Create Badges with HTML and CSS', 'wordcamporg' ); ?>
		</a>
	</div>

	<div id="indesign-badges-overview" class="cbg-method-overview">
		<h2>
			<?php esc_html_e( 'InDesign', 'wordcamporg' ); ?>
		</h2>

		<ul class="ul-disc">
			<li><?php esc_html_e( 'The best results, but requires more work.', 'wordcamporg' ); ?></li>
			<li><?php esc_html_e( 'Most flexible design options.',             'wordcamporg' ); ?></li>
			<li>
				<?php printf(
					wp_kses_post( __( 'Requires a designer and a copy of InDesign. <a href="%s">Free trial copies are available</a>.', 'wordcamporg' ) ),
					'https://www.adobe.com/products/indesign.html'
				); ?>
			</li>
		</ul>

		<a class="button button-primary" href="<?php echo esc_url( $indesign_page_url ); ?>">
			<?php esc_html_e( 'Create Badges with InDesign', 'wordcamporg' ); ?>
		</a>
	</div>
</div>

<p>
	<?php printf(
		// translators: 1: Gravatar.com URL, 2: Notify tool URL.
		wp_kses_post( __(
			'Regardless of which method you choose, you\'ll get the best results if you encourage attendees to create <a href="%1$s">Gravatar</a> accounts before you create the badges. You can use <a href="%2$s">the Notify tool</a> to e-mail everyone. Make sure to tell them to create their Gravatar account using the same e-mail address that provided when purchasing a ticket.',
			'wordcamporg'
		) ),
		'https://gravatar.com',
		esc_url( $notify_tool_url )
	); ?>
</p>
