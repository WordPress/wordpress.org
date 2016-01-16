<?php

namespace WordCamp\Budgets_Dashboard\Sponsor_Invoices;
defined( 'WPINC' ) or die();

?>

<div class="wrap">
	<h1>Sponsor Invoices</h1>

	<?php settings_errors(); ?>

	<p>
		<?php echo esc_html( $section_explanation ); ?>
	</p>

	<h3 class="nav-tab-wrapper">
		<?php foreach ( $sections as $section ) : ?>
			<a
				class="<?php echo esc_attr( $section['classes'] ); ?>"
				href="<?php  echo esc_attr( $section['href']    ); ?>"
			>
				<?php echo esc_html( $section['text'] ); ?>
			</a>
		<?php endforeach; ?>
	</h3>

	<?php $list_table->display(); ?>
</div>
