( function( $ ) {
	$('dialog.slug-change').on('submit', function( e ) {
		e.preventDefault();
		var $form = $(e.target),
			$errorNotice = $form.find('.notice-error p'),
			pluginId = $form.find('input[name="id"]').val(),
			slug = $form.find('input[name="post_name"]').val(),
			restEndpoint = 'plugins/v1/upload/' + pluginId + '/slug';

		$form.find('input').prop('disabled', true);

		wp.apiRequest( {
			path: restEndpoint,
			type: 'PATCH',
			data: {
				post_name: slug
			}
		} )
		.done( function() {
			window.location.reload();
		} )
		.fail( function( response, statusText ) {
			var errorHtml = response?.responseJSON?.message || statusText;

			$errorNotice.html( errorHtml ).parent().removeClass('hidden');

			$form.find('input').prop('disabled', false);
		} );
	} );

	// Show the filename on the button when a file is selected.
	$( 'input.plugin-file' )
		.on( 'change', function( e ) {
			var $span = $(this).parent().find('span'),
				fileName = e.target.value.split( '\\' ).pop();

			if ( ! $span.data( 'defaultText' ) ) {
				$span.data( 'defaultText', $span.text() );
			}

			$span.text( fileName || $span.data( 'defaultText' ) );
		} )
		.on( 'focus', function() { $(this).parent().addClass( 'focus' ); } )
		.on( 'blur', function() { $(this).parent().removeClass( 'focus' ); } );

	$( 'a.show-upload-additional').on( 'click', function( e ) {
		e.preventDefault();

		$(this).hide().parents('ul').find('.plugin-upload-form.hidden').removeClass( 'hidden' );
	} );

	// Pre-submission Plugin Check, in an embedded WordPress Playground.
	var $uploadForm     = $( '#upload_form' ),
		$fileInput      = $uploadForm.find( 'input.plugin-file' ),
		$preview        = $( '#plugin-check-preview' ),
		$previewButton  = $( '#plugin-check-preview-button' ),
		$frameContainer = $preview.find( '.plugin-check-preview-frame' );

	// Runs in Playground after boot: return the basename (e.g. "my-plugin/my-plugin.php")
	// of the just-installed plugin, so we can preselect it in the Plugin Check dropdown.
	// Plugin Check matches ?plugin= against the full basename, not just the folder name.
	var detectPluginBasename = [
		'<?php',
		'require "/wordpress/wp-load.php";',
		'require_once ABSPATH . "wp-admin/includes/plugin.php";',
		'$exclude = array( "akismet/akismet.php", "hello.php" );',
		'$targets = array();',
		'foreach ( array_keys( get_plugins() ) as $basename ) {',
		'	if ( in_array( $basename, $exclude, true ) || false !== strpos( $basename, "plugin-check" ) ) {',
		'		continue;',
		'	}',
		'	$targets[] = $basename;',
		'}',
		'echo 1 === count( $targets ) ? $targets[0] : "";'
	].join( '\n' );

	if ( $uploadForm.length && $preview.length ) {
		$fileInput.on( 'change', function() {
			// Reveal the button once a zip is selected; reset any earlier Playground run.
			$preview.prop( 'hidden', ! this.files.length );
			$frameContainer.prop( 'hidden', true ).empty();
			$previewButton.prop( 'disabled', false );
		} );

		$previewButton.on( 'click', async function() {
			var file = $fileInput[0].files[0],
				buttonText = $previewButton.text();

			if ( ! file ) {
				return;
			}

			$previewButton.prop( 'disabled', true ).text( wp.i18n ? wp.i18n.__( 'Loading Playground…', 'wporg-plugins' ) : 'Loading Playground…' );

			try {
				var playgroundApi = await import( /* webpackIgnore: true */ 'https://playground.wordpress.net/client/index.js' ),
					zipBytes      = new Uint8Array( await file.arrayBuffer() ),
					iframe        = document.createElement( 'iframe' );

				iframe.className = 'plugin-check-preview-iframe';
				iframe.title = wp.i18n ? wp.i18n.__( 'Plugin Check preview (WordPress Playground)', 'wporg-plugins' ) : 'Plugin Check preview (WordPress Playground)';
				iframe.style.cssText = 'width: 100%; height: 80vh; border: 1px solid #c3c4c7; border-radius: 2px';

				$frameContainer.prop( 'hidden', false ).empty().append( iframe );
				iframe.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );

				var client = await playgroundApi.startPlaygroundWeb( {
					iframe: iframe,
					remoteUrl: 'https://playground.wordpress.net/remote.html',
					blueprint: {
						landingPage: '/wp-admin/admin.php?page=plugin-check',
						preferredVersions: {
							php: '7.4', // Minimum recommended PHP, as with the server-generated blueprints.
							wp: 'latest'
						},
						features: {
							networking: true
						},
						steps: [
							{
								step: 'login',
								username: 'admin',
								password: 'password'
							},
							{
								step: 'installPlugin',
								pluginData: {
									resource: 'wordpress.org/plugins',
									slug: 'plugin-check'
								}
							},
							{
								step: 'installPlugin',
								pluginData: {
									resource: 'literal',
									name: file.name,
									contents: zipBytes
								},
								options: {
									activate: false
								}
							}
						]
					}
				} );

				// Preselect the uploaded plugin in the Plugin Check dropdown by its basename.
				// The user still clicks "Check it!" themselves.
				try {
					var result   = await client.run( { code: detectPluginBasename } ),
						basename = ( result.text || '' ).trim();

					if ( basename ) {
						await client.goTo( '/wp-admin/admin.php?page=plugin-check&plugin=' + encodeURIComponent( basename ) );
					}
				} catch ( selectError ) {
					// Preselection is best-effort; the dropdown still lets the user pick manually.
				}

				$previewButton.prop( 'disabled', false ).text( buttonText );
			} catch ( error ) {
				$frameContainer.prop( 'hidden', true ).empty();
				$previewButton.prop( 'disabled', false ).text( buttonText );
				window.alert( 'WordPress Playground could not be loaded: ' + ( error.message || error ) );
			}
		} );
	}

})( jQuery );
