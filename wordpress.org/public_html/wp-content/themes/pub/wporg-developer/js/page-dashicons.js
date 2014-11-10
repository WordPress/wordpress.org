(function( wp, $, window, undefined ) {

	var dashicons = {
		copy: function( text, copyMode ) {
			if ( copyMode == "css" ) {
				window.prompt( "Copy this, then paste in your CSS :before selector.", text );
			} else if ( copyMode == "html" ) {
				window.prompt( "Copy this, then paste in your HTML.", text );
			} else {
				window.prompt( "Copy this, then paste in your Photoshop textfield.", text );
			}
		},

		random: function() {
			var divs = jQuery("#iconlist div").get().sort(function(){
					return Math.round(Math.random())-0.5;
				}).slice(0,1);

			attr = jQuery(divs).attr('alt');
			cssClass = jQuery(divs).attr('class');
			dashicons.display( attr, cssClass );
		},

		display: function( attr, cssClass ){
			// set permalink
			var permalink = cssClass.split(' dashicons-')[1];
			window.location.hash = permalink;

			// html copy string
			htmltext = '<span class="' + cssClass + '"></span>';

			// glyph copy string
			glyphtemp = "&#x" + attr + ";";
			jQuery('#temp').html( glyphtemp );
			glyphtext = jQuery('#temp').text();

			var tmpl = wp.template( 'glyphs' );

			jQuery( '#glyph' ).html( tmpl({
				cssClass: cssClass,
				attr: attr,
				html: htmltext,
				glyph: glyphtext
			}) );
		}
	};

	window.dashicons = dashicons;

	jQuery(document).ready(function() {

		// pick random icon if no permalink, otherwise go to permalink
		if ( window.location.hash ) {
			permalink = "dashicons-" + window.location.hash.split('#')[1];

			// sanitize
			if ( !/^dashicons-[a-z-]+$/.test( permalink ) ) {
				permalink = "";
				dashicons.random();
			}

			attr = jQuery( '.' + permalink ).attr( 'alt' );
			cssClass = jQuery( '.' + permalink ).attr('class');
			dashicons.display( attr, cssClass );
		} else {
			dashicons.random();
		}

		jQuery( '#iconlist div' ).click(function() {

			attr = jQuery( this ).attr( 'alt' );
			cssClass = jQuery( this ).attr( 'class' );

			dashicons.display( attr, cssClass );
			$(window).scrollTop( $("#glyph").offset().top );

		});

		var $rows = jQuery('#iconlist div');
		jQuery('#search').keyup(function() {

			// remove update text when using search
			jQuery('body').addClass('searching');

			var val = jQuery.trim(jQuery(this).val()).replace(/ +/g, ' ').toLowerCase();

			if ( val == '' ) {
				jQuery('body').removeClass('searching');
			}

			$rows.show().filter(function() {
				var text = jQuery(this).text().replace(/\s+/g, ' ').toLowerCase();
				return !~text.indexOf(val);
			}).hide();
		});

	});

})( wp, jQuery, window );
