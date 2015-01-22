jQuery( function( $ ) {

$('.locale-filters').on( 'click', '.i18n-filter', function() {
	$( '.current-filter' ).removeClass( 'current-filter' );
	$( '.translators-info' )[0].className = 'translators-info show-' + $( this ).data( 'filter' );
	$( this ).addClass( 'current-filter' );
	return false;
});

});
