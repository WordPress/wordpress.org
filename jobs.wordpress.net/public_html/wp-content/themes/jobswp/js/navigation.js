/**
 * navigation.js
 *
 * Handles toggling the two navigation menus for small screens.
 *
 */
jQuery(document).ready(function(){

	jQuery('.menu-toggle').click(function () {
		jQuery('.menu').slideToggle(400, function () {
			jQuery('.menu').toggleClass('mobile-pop').css('display', '');
		});
	});

	jQuery('.menu-jobs-toggle').click(function () {
		jQuery('.menu-jobs').slideToggle(400, function () {
			jQuery('.menu-jobs').toggleClass('mobile-pop').css('display', '');
		});
	});

});
