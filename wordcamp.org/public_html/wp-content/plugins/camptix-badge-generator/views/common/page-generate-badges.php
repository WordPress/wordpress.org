<?php

namespace CampTix\Badge_Generator;
defined( 'WPINC' ) or die();

?>

<p>
	<?php _e(
		'This tool will help you create personalized badges for attendees to wear during the event.
		There are two methods for this, depending on your preferences:',
		'wordcamporg'
	); ?>
</p>

<div id="cbg-method-overviews">
	<div id="html-badge-overview">
		<h2><?php _e( 'HTML and CSS', 'wordcamporg' ); ?></h2>

		<ul class="ul-disc">
			<li><?php _e( 'The Easiest method.',                                                                              'wordcamporg' ); ?></li>
			<li><?php _e( 'Can be as simple as using the default design and printing at home.',                               'wordcamporg' ); ?></li>
			<li><?php _e( 'Design is customizable by a designer or developer, but options are limited compared to InDesign.', 'wordcamporg' ); ?></li>
		</ul>

		<a class="button button-primary" href="<?php echo esc_url( $html_customizer_url ); ?>">
			<?php _e( 'Create Badges with HTML and CSS', 'wordcamporg' ); ?>
		</a>
	</div>

	<div id="indesign-badges-overview" class="cbg-method-overview">
		<h2><?php _e( 'InDesign', 'wordcamporg' ); ?></h2>

		<ul class="ul-disc">
			<li><?php _e( 'The best results, but requires more work.', 'wordcamporg' ); ?></li>
			<li><?php _e( 'Most flexible design options.',             'wordcamporg' ); ?></li>
			<li>
				<?php printf(
					__( 'Requires a designer and a copy of InDesign. <a href="%s">Free trial copies are available</a>.', 'wordcamporg' ),
					'https://www.adobe.com/products/indesign.html'
				); ?>
			</li>
		</ul>

		<a class="button button-primary" href="<?php echo esc_url( $indesign_page_url ); ?>">
			<?php _e( 'Create Badges with InDesign', 'wordcamporg' ); ?>
		</a>
	</div>
</div>

<p>
	<?php printf(
		__(
			'Regardless of which method you choose, you\'ll get the best results if you encourage attendees to create <a href="%s">Gravatar</a> accounts before you create the badges.
			You can use <a href="%s">the Notify tool</a> to e-mail everyone.
			Make sure to tell them to create their Gravatar account using the same e-mail address that provided when purchasing a ticket.',
			'wordcamporg'
		),
		'https://gravatar.com',
		esc_url( $notify_tool_url )
	); ?>
</p>
