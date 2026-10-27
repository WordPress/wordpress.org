// WordPress Jobs — Main JavaScript
document.addEventListener('DOMContentLoaded', function () {

	// ---- Mobile Menu Toggle ----
	var menuToggle = document.querySelector('.mobile-menu-toggle');
	var nav = document.querySelector('.site-header__nav');

	if (menuToggle && nav) {
		menuToggle.addEventListener('click', function () {
			nav.classList.toggle('is-open');
		});
	}

	// ---- Category Filter Pills ----
	var pills = document.querySelectorAll('.filter-pill');
	var cards = document.querySelectorAll('.job-card');

	pills.forEach(function (pill) {
		pill.addEventListener('click', function () {
			var category = pill.dataset.category;

			pills.forEach(function (p) {
				p.classList.remove('active');
			});
			pill.classList.add('active');

			var visibleCount = 0;
			cards.forEach(function (card) {
				if (category === 'all' || card.dataset.category === category) {
					card.style.display = '';
					visibleCount++;
				} else {
					card.style.display = 'none';
				}
			});

			// Show empty state if no cards match
			var emptyState = document.querySelector('.jobs-empty');
			if (emptyState) {
				emptyState.style.display = visibleCount === 0 ? '' : 'none';
			}
		});
	});

});
