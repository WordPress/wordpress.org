document.addEventListener( 'DOMContentLoaded', () => {
	const photoFavoriteHeart = document.querySelector('.photo-favorite-heart');

	if (photoFavoriteHeart) {
		photoFavoriteHeart.addEventListener('click', function(event) {
			event.preventDefault();

			// Update the link based on the presence or absence of the 'favorited' class.
			const url = this.getAttribute('href');
			let newUrl = '';
			if (this.classList.contains('favorited')) {
				newUrl = url.replace('favorite=1', 'unfavorite=1');
			} else {
				newUrl = url.replace('unfavorite=1', 'favorite=1');
			}
			this.setAttribute('href', newUrl);

			this.classList.toggle('favorited');

			// Make an asynchronous REST request to the URL.
			const xhr = new XMLHttpRequest();
			xhr.open('GET', url, true);
			xhr.send();
		});
	}
} );
