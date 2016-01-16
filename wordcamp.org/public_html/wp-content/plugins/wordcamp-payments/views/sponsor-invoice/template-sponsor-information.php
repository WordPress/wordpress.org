<?php

namespace WordCamp\Budgets\Sponsor_Invoices;
defined( 'WPINC' ) or die();

?>

<script type="text/html" id="tmpl-wcbsi-sponsor-information">

	<div id="wcbsi-billing-information">
		<div id="wcbsi-billing-contact">
			<h4><?php _e( 'Billing Contact', 'wordcamporg' ); ?></h4>

			<p>
				{{data.firstName}} {{data.lastName}}<br />
				{{data.companyName}}<br />
				{{data.emailAddress}}<br />
				{{data.phoneNumber}}
			</p>

			<p>
				<?php _e( 'Tax Resale Number:', 'wordcamporg' ); ?> {{data.taxResaleNumber}}
			</p>
		</div>

		<div id="wcbsi-billing-address">
			<h4><?php _e( 'Address', 'wordcamporg' ); ?></h4>

			<address>
				{{data.streetAddress1}}<br />

				<# if ( data.streetAddress2 ) { #>
					{{data.streetAddress2}}<br />
				<# } #>

				{{data.city}}, {{data.state}} {{data.zipCode}}<br />
				{{data.country}}
			</address>
		</div>
	</div>

	<p>
		<a href="{{data.editUrl}}" target="_blank">
			<?php _e( 'Edit sponsor contact information', 'wordcamporg' ); ?>
		</a>
	</p>

</script>
