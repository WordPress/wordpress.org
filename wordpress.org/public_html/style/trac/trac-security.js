(function($) {
	var badwords, intersect;
	badwords = [
		'sql', 'trojan', 'rce', 'permissions', 'exploit', 'exploits', 'csrf', 'xss', 'sqli',
		'scripting', 'vulnerability', 'vulnerabilities', 'capability', 'capabilities', 'intrusion',
		'intrusions', 'cve', 'disclosure', 'hash', 'security', 'leakage', 'privilege', 'privileges',
		'compromise', 'escalation', 'injection', 'forgery', 'password', 'passwords'
	];

	intersect = function(a, b) {
		return $.grep(a, function(i) {
			return $.inArray(i, b) > -1; 
		}); 
	};

	$(document).ready( function() {
		var submit = $( 'input[type="submit"]' );
		$( '#field-summary, #field-description' ).on( 'keyup', function() {
			var words, overlap;
			words = $(this).val().toLowerCase().split( /[^a-z]/ );
			overlap = intersect( badwords, words );
		 
			if ( overlap.length === 0 ) {
				submit.prop( 'disabled', false );
				$( '#security-question' ).hide();
				return;
			}

			// We have a potential problem here
			submit.prop( 'disabled', true );
			if ( $( '#security-question' ).length !== 0 ){ 
				// We've already created the checkbox
				$( '#security-question' ).show();
			} else {
				// We need to add the checkbox
				$( '.buttons' ).before( '<p id="security-question"><label><input type="checkbox" name="sec_question" />' +
					'&nbsp;I am <strong>not</strong> reporting a security issue</label>' +
					' &mdash; <a href="http://make.wordpress.org/core/handbook/reporting-security-vulnerabilities/">report security issues to security@wordpress.org</a></p>' );
			}
		});
		$( '#propertyform' ).on( 'change', '#security-question input', function() {
			submit.prop( 'disabled', ! $(this).is( ':checked' ) );
		});
	});
})(jQuery);
	
