<?php
/**
 * Sponsorship Agreement Template
 */
class WordCamp_Docs_Template_Sponsorship_Agreement implements WordCamp_Docs_Template {
	public function form( $data ) {
		$data = wp_parse_args( $data, array(
			'sponsor_name' => '',
			'sponsor_rep_name' => '',
			'sponsor_rep_title' => '',

			'agreement_date' => '',
			'wordcamp_location' => '',
			'wordcamp_date' => '',

			'sponsorship_amount' => '',
			'sponsorship_amount_num' => '',
			'sponsorship_benefits' => '',
		) );
		?>
		<style>
		.wcorg-sponsorship-agreement-form label {
			display: block;
			clear: both;
			margin-top: 12px;
		}

		.wcorg-sponsorship-agreement-form input,
		.wcorg-sponsorship-agreement-form textarea,
		.wcorg-sponsorship-agreement-form select {
			width: 240px;
		}

		.wcorg-sponsorship-agreement-form textarea {
			height: 120px;
		}

		.wcorg-sponsorship-agreement-form .description {
			display: block;
			clear: both;
		}
		</style>

		<div class="wcorg-sponsorship-agreement-form">
			<label><?php _e( 'Sponsor Name:', 'wordcamporg' ); ?></label>
			<input name="sponsor_name" value="<?php echo esc_attr( $data['sponsor_name'] ); ?>" />

			<label><?php _e( 'Sponsor Representative Name:', 'wordcamporg' ); ?></label>
			<input name="sponsor_rep_name" value="<?php echo esc_attr( $data['sponsor_rep_name'] ); ?>" />

			<label><?php _e( 'Sponsor Representative Title:', 'wordcamporg' ); ?></label>
			<input name="sponsor_rep_title" value="<?php echo esc_attr( $data['sponsor_rep_title'] ); ?>" />

			<label><?php _e( 'Agreement Date:', 'wordcamporg' ); ?></label>
			<input name="agreement_date" value="<?php echo esc_attr( $data['agreement_date'] ); ?>" />

			<label><?php _e( 'WordCamp Date:', 'wordcamporg' ); ?></label>
			<input name="wordcamp_date" value="<?php echo esc_attr( $data['wordcamp_date'] ); ?>" />

			<label><?php _e( 'WordCamp Location:', 'wordcamporg' ); ?></label>
			<input name="wordcamp_location" value="<?php echo esc_attr( $data['wordcamp_location'] ); ?>" />

			<label><?php _e( 'Sponsorship Amount (in words):', 'wordcamporg' ); ?></label>
			<input name="sponsorship_amount" value="<?php echo esc_attr( $data['sponsorship_amount'] ); ?>" />

			<label><?php _e( 'Sponsorship Amount (in numbers):', 'wordcamporg' ); ?></label>
			<input name="sponsorship_amount_num" value="<?php echo esc_attr( $data['sponsorship_amount_num'] ); ?>" />

			<label><?php _e( 'Sponsorship Benefits:', 'wordcamporg' ); ?></label>
			<textarea name="sponsorship_benefits"><?php echo esc_textarea( $data['sponsorship_benefits'] ); ?></textarea>
			<span class="description"><?php _e( 'Use multiple lines.', 'wordcamporg' ); ?></span>
		</div>

		<?php
	}

	public function render( $data ) {
		ob_start();
		?>
<html>
<head>
<meta charset="UTF-8">
<link href="http://fonts.googleapis.com/css?family=Open+Sans:300,600,700" rel="stylesheet" type="text/css" />
<style type="text/css">
body {
	margin: 0;
	padding: 0;
	font-family: 'Open Sans', sans-serif;
	font-size: 15px;
	line-height: 1.5;
	font-weight: 300;
	color: #444;
}

p {
	margin: 0 0 1.5em;
}

h1, h2, h3, h4, div, p, span, table {
	font-family: 'Open Sans', sans-serif;
}

strong {
	font-weight: 600;
}

table {
	table-layout: fixed;
	width: 100%;
	margin: 0;
	padding: 0;
	border: none;
	border-collapse: collapse;

	font-family: 'Open Sans', sans-serif;
	font-size: 15px;
	line-height: 1.5;
	font-weight: 300;
	color: #444;
}

h2 {
	font-size: 18px;
}

.wrap {
	clear: both;
	margin: 40px 20px;
}

.main {
	float: left;
	width: 100%;
	margin-top: 40px;
}
</style>
</head>

<body>

<div class="wrap">
	<div class="main">

		<h2>WordCamp Sponsorship Agreement</h2>

		<p>This Sponsorship Agreement is made by and between WordPress Community Support, a Public Benefit Corporation (the "WPCS"), and <?php echo esc_html( $data['sponsor_name'] ); ?> (the "Sponsor"). This Agreement is effective as of <?php echo esc_html( $data['agreement_date'] ); ?>.</p>

		<p>1. Sponsored events. With assistance from its local organizers, WPCS hosts WordCamp conferences throughout the world. This Agreement pertains to a WordCamp event hosted in the following location and time period:</p>

		<p>Location: <?php echo esc_html( $data['wordcamp_location'] ); ?><br />
		Time Period: <?php echo esc_html( $data['wordcamp_date'] ); ?></p>

		<p>2. Sponsorship Amount. Within 30 days of this Agreement or before the start of the first Sponsored WordCamp, whichever is sooner, the Sponsor agrees to pay <?php echo esc_html( $data['sponsorship_amount'] ); ?> (US$ <?php echo esc_html( $data['sponsorship_amount_num'] ); ?>) to WPCS (the "Sponsorship").</p>

		<p>3. Use of Funds. WPCS will use the Sponsorship to cover its costs and the costs of its volunteers and agents in connection with organizing, promoting, and operating the Sponsored WordCamp(s). Any excess remaining after these costs are paid may be used by WPCS for its unrestricted general support.</p>

		<p>4. Recognition of Sponsor at the Sponsored WordCamps.  In recognition of its support through the Sponsorship, WPCS will provide the following benefits to the Sponsor:</p>

		<p><?php echo nl2br( esc_html( $data['sponsorship_benefits'] ) ); ?></p>

		<p>The Sponsor is responsible for providing to WPCS in a timely manner the links referenced above as well as any name or logo artwork for use in the above acknowledgments. The Sponsor agrees, however, that the specific format of the above acknowledgment (e.g., time-length of slide displays and relative size of the Sponsor’s logo) will be in WPCS’s discretion.</p>

		<p>Notwithstanding anything else in this Agreement, the Sponsor understands and agrees that any acknowledgment by WPCS of the Sponsor is limited to the terms described in Exhibit A. The Sponsor also understands and agrees that WPCS will not endorse the Sponsor or any product or service offered by the Sponsor, and that nothing in this Agreement provides any right to the Sponsor or its representatives to speak at a Sponsored WordCamp or meetup.</p>

		<p>5. Sponsor Conduct. The Sponsor recognizes that, in associating itself with WPCS and the Sponsored WordCamps, the Sponsor expected to support the WordPress project and its principles. Accordingly, the Sponsor agrees to comply with the Sponsor Guidelines attached as Exhibit A in conducting any activities at or in connection with the Sponsored WordCamps.</p>

		<p>6. Use of WordCamp names. The Sponsor may in its reasonable discretion use the name and logo of each Sponsored WordCamp, and may refer or link to each Sponsored WordCamp, in any press release, website, advertisement, or other public document or announcement, including without limitation in a general list of the Sponsor's supported organizations and as otherwise required by law; provided, however, that any such use must be in compliance with the Sponsor Guidelines attached as Exhibit A (including but not limited to the prohibition on the use of WPCS's name to imply any endorsement of the Sponsor's products or services).  Any breach by the Sponsor of Section 5 or Section 6 will constitute a material breach of this Agreement, as a result of which WPCS may terminate this Agreement and retain the Sponsorship for its unrestricted use if the Sponsor does not cure such breach to the reasonable satisfaction of WPCS in a reasonably prompt timeframe under the circumstances (and in any event immediately, if such breach occurs during a WordCamp).</p>

		<p>7. Trademarks. The Sponsor and WPCS hereby grant each other permission to use the other party's name, logo, and other trademarks in connection with the activities contemplated above. These permissions are, however, revocable, non-exclusive, and non-transferable, and each party agrees to use the other party's logo or trademark only in accordance with any trademark usage guidelines that the other party may provide from time to time. Neither party will hold the other party liable for any incidental or consequential damages arising from that other party's use of its trademarks in connection with this Agreement. Except as expressly provided above, any use of the WordPress trademarks is subject to the WordPress Trademark Policy listed at http://wordpressfoundation.org/trademark-policy.</p>

		<p>8. Relationship of the Parties. This Agreement is not to be construed as creating any agency, partnership, joint venture, or any other form of association, for tax purposes or otherwise, between the parties, and neither party will make any such representation to anyone. Neither party will have any right or authority, express or implied, to assume or create any obligation of any kind, or to make any representation or warranty, on behalf of the other party or to bind the other party in any respect.</p>

		<p>9. Governing Law. This Agreement will be governed by and construed in accordance with the laws of the State of California, USA, without reference to its conflict of laws provisions.</p>

		<p>10. Severability. If any provision of this Agreement is held to be invalid, void, or otherwise unenforceable, that provision will be enforced to the maximum extent possible so as to effect the intent of the parties, and the remainder of this Agreement will remain in full force and effect.</p>

		<p>11. Assignment. Neither WPCS nor the Sponsor will have the right to assign this Agreement without the prior written consent of the other party, and any purported assignment without such consent will be void. WPCS may delegate its duties under this Agreement to its volunteers and local WordCamp organizers.</p>

		<p>12. Refund and Cancellation Policy. WordCamp Sponsors will not be acknowledged until payment is received in full. Sponsors may request a refund and cancel their sponsorship within 5 business days of payment of the sponsorship invoice. 5 business days after the sponsorship invoice is paid, refunds are no longer available. If a WordCamp is cancelled, sponsors will be refunded their sponsorship fees in full.</p>

		<p>13. Entire Agreement; Amendment. This Agreement (including Exhibit A) constitutes the entire agreement of WPCS and the Sponsor with respect to the subject matter set forth herein, and this Agreement supersedes any prior or contemporaneous oral or written agreements, understandings, or communications or past courses of dealing between the Sponsor and WPCS with respect to that subject matter. This Agreement may not be amended or modified, except in a written amendment signed by duly authorized representatives of both parties.</p>

		<p>14. Counterparts. This Agreement may be executed in one or more counterparts, each of which will be deemed an original, but all of which together will constitute one and the same agreement.</p>

		<p>The parties have executed this Agreement as of date set forth above.</p>

		<table>
			<tr>
				<td style="width: 50%;">
					<p><strong>Sponsor</strong><br /><br />
					Signature:<br /><br />
					Representative name: <?php echo esc_html( $data['sponsor_rep_name'] ); ?><br /><br />
					Title: <?php echo esc_html( $data['sponsor_rep_title'] ); ?><br /><br />
					Company name: <?php echo esc_html( $data['sponsor_name'] ); ?><br /></p>
				</td>
				<td style="width: 50%;">
					<p><strong>Recipient</strong><br /><br />
					Signature:<br /><br />
					Representative name:<br /><br />
					Title:<br /><br />
					Company name:<br /></p>
				</td>
			</tr>
		</table>

	</div>

	<div style="page-break-after:always;">&nbsp;</div>

	<div class="main">

		<h2>Exhibit A: WordPress Community Event Sponsor Guidelines</h2>

		<p>1. Sponsor may provide:</p>
		<ul>
			<li>The sponsor's name and logo</li>
			<li>Slogans that are an established part of the sponsor's image</li>
			<li>The sponsor's brands and trade names</li>
			<li>Sponsor contact information (such as telephone numbers, email addresses, and URLs)</li>
			<li>Factual (value-neutral) displays of actual products</li>
			<li>Displays or handout materials (such as brochures) with factual, non-comparative descriptions or listings of products or services</li>
			<li>Price information, or other indications of savings or value, if factual and provable</li>
			<li>Inducements to purchase or use the Sponsor's products or services, for example by providing coupons or discount purchase codes (subject to approval)</li>
			<li>Calls to action, such as "visit this site for details", "call now for a special offer", "join our league of savings", etc.</li>
		</ul>

		<p>2. Sponsors may not provide:</p>
		<ul>
			<li>Promotional or marketing material containing comparative messages about the Sponsor, its products or services, such as "the first name in WordPress hosting", "the easiest way to launch your site", or "the best e-commerce plugin"</li>
			<li>Claims that WordPress, the WordPress Foundation, WordPress Community Support, meetup organizers, WordCamps, or WordCamp organizers endorse or favor a Sponsor or its products or services (such as "certified WordPress training" or "WordCamp's favorite plugin")</li>
		</ul>

		<p>3. Sponsors agree that the WordPress Community Support, any subsidiary or related entity of the WordPress Community Support, and WordCamp organizers have the right to request and review sponsor materials in advance of an event, to require changes to any materials in advance, and to require that any materials that do not meet the above expectations be taken down or that any practices that do not meet the above expectations be discontinued during a WordCamp or event. The above restrictions also apply to material placed on any self-serve swag tables reserved for sponsor use.</p>

		<p>4. All sponsors are expected to support the WordPress project and its principles, including:</p>
		<ul>
			<li>No discrimination on the basis of economic or social status, race, color, ethnic origin, national origin, creed, religion, political belief, sex, sexual orientation, marital status, age, or disability.</li>
			<li>No incitement to violence or promotion of hate</li>
			<li>No spammers</li>
			<li>No jerks</li>
			<li>Respect the WordPress trademark.</li>
			<li>Embrace the WordPress license; If distributing WordPress-derivative works (themes, plugins, WP distros), any person or business officially associated with WordCamp should give their users the same freedoms that WordPress itself provides: 100% GPL or compatible, the same guidelines we follow on WordPress.org.</li>
			<li>Don't promote companies or people that violate the trademark or distribute WordPress derivative works which aren't 100% GPL compatible.</li>
		</ul>

		<p>5. Sponsorship is in no way connected to the opportunity to speak at an official WordPress event and does not alter the WordPress or WordCamp trademark usage policy found at http://wordpressfoundation.org/. The WordPress Foundation and any subsidiary or related entity of the Foundation reserve the right to modify the above requirements and expectations at any time by providing written notice to the sponsor.</p>

	</div>

	<!--<div id="footer">
		<p>WordPress Foundation, 200 Brannan Street Apt 239, San Francisco, CA 94107-6008, USA</p>
	</div>-->
</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	public function sanitize( $input ) {
		$output = array();
		foreach ( array(
			'sponsor_name',
			'sponsor_rep_name',
			'sponsor_rep_title',
			'agreement_date',
			'wordcamp_location',
			'wordcamp_date',
			'sponsorship_amount',
			'sponsorship_amount_num',
		) as $field )
			$output[ $field ] = sanitize_text_field( wp_strip_all_tags( $input[ $field ] ) );

		$output['sponsorship_benefits'] = wp_strip_all_tags( $input['sponsorship_benefits'] );
		return $output;
	}

	public function get_name() {
		return __( 'Sponsorship Agreement', 'wordcamporg' );
	}

	public function get_filename() {
		return 'sponsorship-agreement.pdf';
	}

	public function get_assets() {
		return array();
	}
}
