if ( typeof wp_tour !== 'undefined' ) {
	window.tour = wp_tour;
}
var tourSelectorActive = false;
var tourId;
var dialogOpen = false;

var setTourCookie = function( tourId ) {
	document.cookie = 'tour=' + escape( tourId ) + ';path=/';
	enableTourCreation();
}

var deleteTourCookie = function() {
	document.cookie = 'tour=;path=/;expires=Thu, 01 Jan 1970 00:00:01 GMT;';
	enableTourCreation();
}

document.addEventListener('click', function( event ) {
	if ( ! event.target.dataset.addMoreStepsText || ! event.target.dataset.tourId ) {
		return;
	}

	event.preventDefault();
	if ( event.target.textContent === event.target.dataset.finishTourCreationText ) {
		event.target.textContent = event.target.dataset.addMoreStepsText;
		deleteTourCookie();
		return;
	}
	setTourCookie( event.target.dataset.tourId );
	event.target.textContent = event.target.dataset.finishTourCreationText;
} );

function enableTourCreation() {
	tourId = document.cookie.indexOf('tour=') > -1 ? unescape(document.cookie.split('tour=')[1].split(';')[0]) : '';
	if ( tourId && document.getElementById('tour-launcher') ) {
		if ( typeof tour_plugin !== 'undefined' && typeof tour_plugin.tours[ tourId ] !== 'undefined' ) {
			document.getElementById('tour-launcher').style.display = 'block';
			document.getElementById('tour-title').textContent = tour_plugin.tours[ tourId ][0].title;
			document.getElementById('tour-steps').textContent = (tour_plugin.tours[ tourId ].length - 1) + ' step' + (tour_plugin.tours[ tourId ].length > 2 ? 's' : '');
			for ( var i = 1; i < tour_plugin.tours[ tourId ].length; i++ ) {
				el = document.querySelector(tour_plugin.tours[ tourId ][i].selector);
				if ( el ) {
					el.style.outline = '1px dashed ' + tour_plugin.tours[ tourId ][0].color;
				} else {
					reportMissingSelector( tour_plugin.tours[ tourId ][0].title, i, tour_plugin.tours[ tourId ][i].selector );
				}
			}
		}
	} else if ( document.getElementById('tour-launcher') ) {
		document.getElementById('tour-launcher').style.display = 'none';
	}
}
enableTourCreation();

function reportMissingSelector( tourTitle, step, selector ) {
	var xhr = new XMLHttpRequest();
	xhr.open('POST', tour_plugin.rest_url + 'tour/v1/report-missing');
	xhr.setRequestHeader('Content-Type', 'application/json');
	xhr.setRequestHeader('X-WP-Nonce', tour_plugin.nonce);
	xhr.send(JSON.stringify({
		tour: tourId,
		selector: selector,
		step: step,
		url: location.href,
	}));
}

function toggleTourSelector( event ) {
	event.stopPropagation();
	if ( event.target.tagName.toLowerCase() === 'a' ) {
		deleteTourCookie();
		return false;
	}

	tourSelectorActive = ! tourSelectorActive;

	document.getElementById('tour-launcher').style.color = tourSelectorActive ? tour_plugin.tours[ tourId ][0].color : '';
	return false;
}

document.getElementById('tour-launcher').addEventListener('click', toggleTourSelector);
var clearHighlight = function( event ) {
	if ( typeof tour_plugin.tours[ tourId ] !== 'undefined' ) {
		for ( var i = 1; i < tour_plugin.tours[ tourId ].length; i++ ) {
			if ( event.target.matches(tour_plugin.tours[ tourId ][i].selector) ) {
				document.querySelector(tour_plugin.tours[ tourId ][i].selector).style.outline = '1px dashed ' + tour_plugin.tours[ tourId ][0].color;
				return;
			}
		}
	}
	event.target.style.outline = '';
	event.target.style.cursor = '';
}

var tourStepHighlighter = function(event) {
	var target = event.target;
	if ( ! tourSelectorActive || target.closest('#tour-launcher') ) {
		clearHighlight( event );
		return;
	}
	// Highlight the element on hover
	target.style.outline = '2px solid ' + tour_plugin.tours[ tourId ][0].color;
	target.style.cursor = 'pointer';
};

var filter_selectors = function( c ) {
	return c.indexOf( 'wp-' ) > -1
	|| c.indexOf( 'page' ) > -1
	|| c.indexOf( 'post' ) > -1
	|| c.indexOf( 'column' ) > -1;
}

var tourStepSelector = function(event) {
	if ( ! tourSelectorActive ) {
		return;
	}

	function getSelectors(elem) {
		var selectors = [];

		while ( elem.parentElement ) {
			var currentElement = elem.parentElement;
			var tagName = elem.tagName.toLowerCase();
			var classes = [];

			if ( elem.id ) {
				selectors.push( tagName + '#' + elem.id );
				break;
			}

			elem.classList.forEach( function( c ) {
				if ( ! filter_selectors( c ) ) {
					return;
				}
				classes.push( c );
			})

			if ( classes.length ) {
				selectors.push( tagName + '.' + classes.join( '.') );
			} else {
				var index = Array.prototype.indexOf.call(currentElement.children, elem) + 1;
				selectors.push(tagName + ':nth-child(' + index + ')');
			}

			elem = currentElement;
		}

		return selectors.reverse();
	}


	event.preventDefault();

	var selectors = getSelectors(event.target);

	dialogOpen = true;
	var stepName = prompt( 'Enter description for step ' + tour_plugin.tours[ tourId ].length );
	if ( ! stepName ) {
		event.target.style.outline = '';
		return false;
	}

	tour_plugin.tours[ tourId ].push({
		element: selectors.join(' '),
		popover: {
			title: tour_plugin.tours[ tourId ][0].title,
			description: stepName,
		},
	});

	event.target.style.outline = '1px dashed ' + tour_plugin.tours[ tourId ][0].color;

	if ( tour_plugin.tours[ tourId ].length > 1 ) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', tour_plugin.rest_url + 'tour/v1/save');
		xhr.setRequestHeader('Content-Type', 'application/json');
		xhr.setRequestHeader('X-WP-Nonce', tour_plugin.nonce);
		xhr.send(JSON.stringify({
			tour: tourId,
			steps: JSON.stringify(tour_plugin.tours[ tourId ]),
		}));

		document.getElementById('tour-steps').textContent = (tour_plugin.tours[ tourId ].length - 1) + ' step' + (tour_plugin.tours[ tourId ].length > 2 ? 's' : '');
		document.getElementById('tour-title').textContent = 'Saved!';

		window.loadTour();
		setTimeout(function() {
			document.getElementById('tour-title').textContent = tour_plugin.tours[ tourId ][0].title;
		}, 1000);
		return false;
	}

	return false;
};

document.addEventListener('keyup', function(event) {
	if ( event.keyCode === 27 ) {
		if ( dialogOpen ) {
			dialogOpen = false;
			return;
		}
		tourSelectorActive = false;
		document.getElementById('tour-launcher').style.color = '';
	}
});
document.addEventListener('mouseover', tourStepHighlighter);
document.addEventListener('mouseout', clearHighlight);
document.addEventListener('click', tourStepSelector);
