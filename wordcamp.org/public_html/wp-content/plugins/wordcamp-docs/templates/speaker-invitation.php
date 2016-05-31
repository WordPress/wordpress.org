<?php
/**
 * Speakers Invitation Template
 *
 * @see classes/class-wordcamp-docs.php for implementation details.
 */
class WordCamp_Docs_Template_Speaker_Invitation implements WordCamp_Docs_Template {
	public function form( $data ) {

		/**
		 * @todo Grab defaults from central and/or options, cpt, etc.
		 */
		$data = wp_parse_args( $data, array(
			'name' => '',
			'gender' => '',
			'dates' => '',
			'city' => '',
			'country' => '',
			'venue_name' => '',
			'venue_address' => '',
			'signature' => '',
		) );
		?>
		<style>
		.wcorg-speaker-invitation-form label {
			display: block;
			clear: both;
			margin-top: 12px;
		}

		.wcorg-speaker-invitation-form input,
		.wcorg-speaker-invitation-form textarea,
		.wcorg-speaker-invitation-form select {
			width: 240px;
		}

		.wcorg-speaker-invitation-form textarea {
			height: 120px;
		}

		.wcorg-speaker-invitation-form .description {
			display: block;
			clear: both;
		}
		</style>

		<div class="wcorg-speaker-invitation-form">
			<label><?php _e( 'Speaker Name:', 'wordcamporg' ); ?></label>
			<input name="name" value="<?php echo esc_attr( $data['name'] ); ?>" />

			<label><?php _e( 'Gender:', 'wordcamporg' ); ?></label>
			<select name="gender">
				<option value="male" <?php selected( $data['gender'], 'male' ); ?>><?php _e( 'Male', 'wordcamporg' ); ?></option>
				<option value="female" <?php selected( $data['gender'], 'female' ); ?>><?php _e( 'Female', 'wordcamporg' ); ?></option>
			</select>

			<label><?php _e( 'Travel Dates:', 'wordcamporg' ); ?></label>
			<input name="dates" value="<?php echo esc_attr( $data['dates'] ); ?>" />
			<span class="description"><?php _e( 'Ex: January 15-16, 2019', 'wordcamporg' ); ?></span>

			<label><?php _e( 'City:', 'wordcamporg' ); ?></label>
			<input name="city" value="<?php echo esc_attr( $data['city'] ); ?>" />

			<label><?php _e( 'Country:', 'wordcamporg' ); ?></label>
			<input name="country" value="<?php echo esc_attr( $data['country'] ); ?>" />

			<label><?php _e( 'Venue Name:', 'wordcamporg' ); ?></label>
			<input name="venue_name" value="<?php echo esc_attr( $data['venue_name'] ); ?>" />

			<label><?php _e( 'Venue Address:', 'wordcamporg' ); ?></label>
			<input name="venue_address" value="<?php echo esc_attr( $data['venue_address'] ); ?>" />

			<label><?php _e( 'Signature:', 'wordcamporg' ); ?></label>
			<textarea name="signature"><?php echo esc_textarea( $data['signature'] ); ?></textarea>
			<span class="description"><?php _e( 'Name, title, phone, e-mail address', 'wordcamporg' ); ?></span>
		</div>

		<?php
	}

	public function render( $data ) {
		ob_start();
		?>
<html>
<head>
<meta charset="UTF-8">
<link href="http://fonts.googleapis.com/css?family=Open+Sans:300,400,700" rel="stylesheet" type="text/css" />
<style type="text/css">
body {
	margin: 0;
	padding: 0;
	font-family: 'Open Sans', sans-serif;
	font-size: 16px;
	line-height: 1.5;
	font-weight: 300;
	color: #444;
}

p {
	margin: 0 0 1.5em;
}

h1, h2, h3, h4, div, p, span {
	font-family: 'Open Sans', sans-serif;
}

.wrap {
	clear: both;
	margin: 40px 40px;
}

.main {
	float: left;
	width: 100%;
	margin-top: 40px;
}

.logo {
	width: 300px;
	height: 150px;
	margin-bottom: 40px;
}

#footer {
	position: absolute;
	bottom: 0px;
	left: 40px;
	right: 40px;
}
</style>
</head>

<body>

<div class="wrap">
	<div class="main">
		<img class="logo" src="logo.png" alt="WordPress Foundation">

		<p><?php echo date( 'F j, Y' ); ?></p>
		<p>To whom it may concern:</p>

		<p>This letter is to confirm that <?php echo esc_html( $data['name'] ); ?> has been invited to speak at an international WordCamp conference in <?php echo esc_html( $data['city'] ) ; ?>, <?php echo esc_html( $data['country'] ) ; ?>. <?php echo $data['gender'] == 'male' ? 'His' : 'Her'; ?> participation in this conference will require <?php echo $data['gender'] == 'male' ? 'him' : 'her'; ?> to be in <?php echo esc_html( $data['city'] ); ?> from <?php echo esc_html( $data['dates'] ); ?>.<p>

		<p>The conference will be held at <?php echo esc_html( $data['venue_name'] ); ?>, <?php echo esc_html( $data['venue_address'] ); ?>.</p>

		<p>Please don't hesitate to contact me for any additional information regarding this invitation.</p>

		<p>Sincerely,</p>
		<p><?php echo nl2br( esc_html( $data['signature'] ) ); ?></p>

	</div>

	<div id="footer">
		<p>WordPress Foundation, 200 Brannan Street Apt 239, San Francisco, CA 94107-6008, USA</p>
	</div>
</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	public function sanitize( $input ) {
		$output = array();
		$input = wp_parse_args( $input, array(
			'name' => '',
			'gender' => '',
			'dates' => '',
			'city' => '',
			'country' => '',
			'venue_name' => '',
			'venue_address' => '',
			'signature' => '',
		) );

		foreach ( array( 'name', 'dates', 'city', 'country', 'venue_name', 'venue_address' ) as $field )
			$output[ $field ] = sanitize_text_field( wp_strip_all_tags( $input[ $field ] ) );

		$output['gender'] = 'male';
		if ( in_array( $input['gender'], array( 'male', 'female' ) ) )
			$output['gender'] = $input['gender'];

		$output['signature'] = wp_strip_all_tags( $input['signature'] );
		return $output;
	}

	public function get_name() {
		return __( 'Speaker Invitation', 'wordcamporg' );
	}

	public function get_filename() {
		return 'speaker-invitation.pdf';
	}

	public function get_assets() {
		return array(
			dirname( __FILE__ ) . '/assets/logo.png',
		);
	}
}