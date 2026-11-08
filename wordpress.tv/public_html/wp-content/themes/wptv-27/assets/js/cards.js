/**
 * Expand pointer activation without extra links, tab stops or player overlays.
 * Delegation also handles cards replaced by core Query enhanced pagination.
 * Without JavaScript, the native title links remain fully functional.
 */
(() => {
	'use strict';

	const selector = '.wptv-home__videos > .wp-block-post, ' +
		'.wptv-listing__videos > .wp-block-post, ' +
		'.wptv-taxonomy__secondary > .wp-block-post, ' +
		'.wptv-taxonomy__caption';
	const interactive = 'a, button, input, select, textarea, label, summary, video, audio, iframe, [contenteditable], [role="button"], [role="link"]';
	const markCards = (root) => {
		root.querySelectorAll(selector).forEach((card) => {
			if (card.querySelector('.wp-block-post-title a')) {
				card.classList.add('wptv-card--clickable');
			}
		});
	};
	markCards(document);
	const recent = document.querySelector('.wptv-home__recent');
	if (recent) {
		new MutationObserver(() => markCards(recent)).observe(recent, {
			childList: true,
			subtree: true,
		});
	}

	const cardFor = (event) => event.target instanceof Element ? event.target.closest(selector) : null;
	const activate = (event) => {
		if (event.defaultPrevented || event.altKey || !(event.target instanceof Element) ||
			event.target.closest(interactive) || window.getSelection()?.toString() ||
			(event.type === 'click' && event.button !== 0) ||
			(event.type === 'auxclick' && event.button !== 1)) {
			return;
		}
		const title = cardFor(event)?.querySelector('.wp-block-post-title a');
		if (!title) {
			return;
		}
		if (event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1) {
			event.preventDefault();
			window.open(title.href, '_blank', 'noopener');
			return;
		}
		title.click();
	};

	// Prevent middle-button autoscroll before auxclick opens the title URL.
	document.addEventListener('mousedown', (event) => {
		if (event.button === 1 && !event.defaultPrevented && cardFor(event) &&
			!event.target.closest(interactive) && !window.getSelection()?.toString()) {
			event.preventDefault();
		}
	});
	document.addEventListener('click', activate);
	document.addEventListener('auxclick', activate);
})();
