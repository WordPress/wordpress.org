<?php

namespace WordCamp\Budgets\Sponsor_Invoices;
defined( 'WPINC' ) or die();

?>

<script type="text/html" id="tmpl-wcbsi-required-fields-incomplete">

	<div class="wcbsi-inline-notice notice notice-warning">
		<p>
			<?php echo sprintf(
				__(
					'Warning: Sponsor contact information is not complete. Please save your invoice as a draft, then <a href="%s">fill out all sponsor contact information</a>, and then return here to send the invoice.',
					'wordcamporg'
				),
				'{{data.editUrl}}'
			); ?>
		</p>
	</div>

</script>
