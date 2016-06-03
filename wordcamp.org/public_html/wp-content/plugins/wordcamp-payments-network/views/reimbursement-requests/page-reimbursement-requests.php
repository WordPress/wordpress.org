<?php

namespace WordCamp\Budgets_Dashboard\Reimbursement_Requests;
defined( 'WPINC' ) or die();

?>

<div class="wrap">
	<h1>Reimbursement Requests</h1>

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

	<div id="wcp-list-table">
		<form id="posts-filter" action="" method="get">
			<input type="hidden" name="page"    value="reimbursement-requests-dashboard" />
			<input type="hidden" name="section" value="<?php echo esc_attr( get_current_section() ); ?>" />
			
			<?php $list_table->search_box( __( 'Search Reimbursements', 'wordcamporg' ), 'wcp' ); ?>
			<?php $list_table->display(); ?>
		</form>
	</div>
</div>
