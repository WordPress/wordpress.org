/**
 * Mirror the core dialog's state for the design's disclosure attributes.
 * Core owns opening, closing, focus management and keyboard behavior.
 */
(() => {
	'use strict';

	document.querySelectorAll('.wptv-header nav.wp-block-navigation').forEach((navigation) => {
		const toggle = navigation.querySelector('.wp-block-navigation__responsive-container-open');
		const overlay = navigation.querySelector('.wp-block-navigation__responsive-container');
		if (!toggle || !overlay || !overlay.id) {
			return;
		}

		toggle.setAttribute('aria-controls', overlay.id);
		const syncState = () => {
			toggle.setAttribute('aria-expanded', String(overlay.classList.contains('is-menu-open')));
		};
		syncState();
		new MutationObserver(syncState).observe(overlay, {
			attributes: true,
			attributeFilter: ['class'],
		});
	});
})();
