( function( $ ) {

	$(function() {
		var $projects = $( 'input.project' );

		if ( $projects.length ) {
			var $allProjects = $( '#project-all' ),
				checked = [];

			// Deselect "All" if a project is checked.
			$projects.on( 'change', function() {
				$allProjects.prop( 'checked', false );
				checked = [];
			} );

			// (De)select projects if "All" is (de)selected.
			$allProjects.on( 'change', function() {
				if ( this.checked ) {
					$projects.each( function( index, checkbox ) {
						var $cb = $( checkbox );
						if ( $cb.prop( 'checked' ) ) {
							checked.push( $cb.attr( 'id' ) );
							$cb.prop( 'checked', false );
						}
					} );
				} else {
					for ( i = 0; i < checked.length; i++ ) {
						$( '#' +  checked[ i ] ).prop( 'checked', true );
					}
					checked = [];
				}
			} );

			// Deselect all checkboxes.
			$( '#clear-all' ).on( 'click', function( event ) {
				event.preventDefault();

				checked = [];
				$allProjects.prop( 'checked', false );
				$projects.prop( 'checked', false );
			} );
		}
	} );
} )( jQuery );
