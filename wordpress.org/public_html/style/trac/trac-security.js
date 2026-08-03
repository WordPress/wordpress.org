window.wp = window.wp || {};

( function ( $ ) {
	if ( $( document.body ).hasClass( 'security' ) ) {
		return;
	}

	const propertyform = $( '#propertyform' ),
		submit = propertyform.find( 'input[type="submit"][name="submit"]' );

	wp.trac_security = {
		badwords: [
			'sql',
			'trojan',
			'rce',
			'permissions',
			'exploit',
			'exploits',
			'csrf',
			'xss',
			'sqli',
			'scripting',
			'vulnerability',
			'vulnerabilities',
			'capability',
			'capabilities',
			'intrusion',
			'intrusions',
			'cve',
			'disclosure',
			'hash',
			'security',
			'leakage',
			'privilege',
			'privileges',
			'escape',
			'unescape',
			'escaped',
			'unescaped',
			'escapes',
			'escaping',
			'unescaping',
			'esc_',
			'sanitize',
			'unsanitize',
			'sanitizes',
			'unsanitizes',
			'sanitized',
			'unsanitized',
			'sanitization',
			'valid',
			'invalid',
			'validate',
			'validates',
			'validation',
			'hack',
			'vulnerable',
			'attack',
			'compromise',
			'escalation',
			'injection',
			'forgery',
			'password',
			'passwords',
			'cross-site',
			'secure',
			'private',
		],

		intersect( a, b ) {
			return $.grep( a, function ( i ) {
				return $.inArray( i, b ) > -1;
			} );
		},

		has_overlap( str, arr ) {
			const words = str
					.toLowerCase()
					.replace( /[^a-z|\s]/g, '' )
					.split( ' ' ),
				overlap = this.intersect( words, arr );

			return overlap.length !== 0;
		},

		seems_like_pentest( str ) {
			return (
				str.toLowerCase().indexOf( 'onerror=' ) !== -1 ||
				str.toLowerCase().indexOf( 'onload=' ) !== -1 ||
				str.toLowerCase().indexOf( '<script' ) !== -1
			);
		},
	};

	function showBox() {
		// Disable submit only if the box isn't already checked.
		if ( false === $( '#security-question input' ).is( ':checked' ) ) {
			submit.prop( 'disabled', true );
		}

		if ( $( '#security-question' ).length !== 0 ) {
			// We've already created the checkbox
			$( '#security-question' ).show();
		} else {
			// We need to add the checkbox
			$( '.buttons' ).before(
				`<p id="security-question">
				<label><input type="checkbox" name="sec_question" />&nbsp;I am <strong>not</strong> reporting a security issue</label>
				&mdash; report <a href="http://make.wordpress.org/core/handbook/reporting-security-vulnerabilities/">security issues</a>
				to the <a href="https://hackerone.com/wordpress">WordPress HackerOne program</a></p>`
			);
		}
	}

	function showPentestNotice() {
		if ( ! $( '#security-pentest-notice' ).length ) {
			// Add a notice
			$( '.buttons' ).before(
				`<div class="newticket-not-here wp-notice" style="background-color: #ffe6e6; border-color: red;">
				<p id="security-pentest-notice" class="security"><span class="dashicons dashicons-lock"></span><strong>Please Note:</strong>
				Performing penetration testing against our trac instances without prior approval is strictly forbidden and will result in any vulnerabilities found being ineligible for bounties per our guidelines.</p></div>`
			);
		}
	}

	function hideBox() {
		submit.prop( 'disabled', false );
		// Continue to ask the question, just don't require it to submit the ticket.
		// $( '#security-question' ).hide();
	}

	function checkFieldValue( $el ) {
		const entry = $el.val();

		if ( wp.trac_security.seems_like_pentest( entry ) ) {
			showBox();
			showPentestNotice();
		} else if ( wp.trac_security.has_overlap( entry, wp.trac_security.badwords ) ) {
			showBox();
		} else {
			hideBox();
		}
	}

	// Check the field value upon keyup
	window.jQuery( '#field-summary, #field-description, #field-keywords' ).on( 'keyup', function () {
		return checkFieldValue( $( this ) );
	} );

	// Trigger on pageload too, ie. upon Preview
	window.jQuery( '#field-summary, #field-description, #field-keywords' ).each( function ( i, el ) {
		const $el = $( el );
		if ( $el.val() !== '' ) {
			checkFieldValue( $el );
		}
	} );

	propertyform.on( 'change', '#security-question input', function () {
		submit.prop( 'disabled', ! $( this ).is( ':checked' ) );
	} );
} )( window.jQuery );
