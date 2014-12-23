jQuery( function( $ ) {

$('.locale-filters').on( 'click', '.i18n-filter', function() {
	$( '.translators-info' )[0].className = 'translators-info show-' + $( this ).data( 'filter' );
	return false;
});

});
