( function( $ ) {

	/**
	 * 100% width/height div script
	 */
	function changeDivHeight() {
		// Set window height to a variable since we're using twice
		// Else it will fetch it from the DOM twice & height() is a CPU intensive function
		var windowHeight   = $( window ).height(),
		    adminbarHeight = $( 'body' ).is( '.admin-bar' ) ? $( '#wpadminbar' ).height() : 0,
			mastheadHeight = $( '#masthead' ).height(),
			contentHeight  = parseInt( $( '.grid-page .entry-content' ).css( 'height' ) ),
			imageHeight    = $( '.intro-image' ).height();

		// If all the visible content is larger than the screen height, adapt the content height
		// so that the viewport scrolls.
		if( ( adminbarHeight + mastheadHeight + contentHeight + imageHeight ) >= windowHeight ) {
			$( '.grid-page' ).css( 'height', 'auto' );
		} else {
			$('.grid-page').css({ 'height': windowHeight - adminbarHeight - mastheadHeight + 'px' });
		}

		// so that the viewport scrolls.
		$(".intro-image").addClass("animated fadeIn");
	}



	/**
	 * Responsive menu toggle
	 */
	function menuToggle() {
		$('body').addClass('js');

		var $menu = $('.menu-main-nav-container'),
			$menulink = $('.menu-toggle'),
			$menuTrigger = $('.menu-item-has-children > a');

		$menulink.click( function(e) {
			e.preventDefault();

			$menulink.toggleClass('toggled');
			$menu.toggleClass('toggled');
		} );

		$menuTrigger.click( function(e) {
			e.preventDefault();

			var $this = $(this);
			$this.toggleClass('toggled').next('ul').toggleClass('toggled');
		} );
	}

	/**
	 * Typer.js — http://cosmos.layervault.com/typer-js.html
	 */
	String.prototype.rightChars = function(n){
		if (n <= 0) {
			return "";
		}
		else if (n > this.length) {
			return this;
		}
		else {
			return this.substring(this.length, this.length - n);
		}
	};


	var options = {
			highlightSpeed    : 50,
			typeSpeed         : 100,
			clearDelay        : 600,
			typeDelay         : 500,
			clearOnHighlight  : true,
			typerDataAttr     : 'data-typer-targets',
			typerInterval     : 1500
	},
		highlight,
		clearText,
		backspace,
		type,
		spanWithColor,
		clearDelay,
		typeDelay,
		clearData,
		isNumber,
		typeWithAttribute,
		getHighlightInterval,
		getTypeInterval,
		typerInterval;

	spanWithColor = function(color, backgroundColor) {
		if (color === 'rgba(0, 0, 0, 0)') {
			color = 'rgb(255, 255, 255)';
		}

		return $('<span></span>').css({
			'color'            : color,
			'background-color' : backgroundColor
		});
	};

	isNumber = function (n) {
		return !isNaN(parseFloat(n)) && isFinite(n);
	};

	clearData = function ($e) {
		$e.removeData([
			'typePosition',
			'highlightPosition',
			'leftStop',
			'rightStop',
			'primaryColor',
			'backgroundColor',
			'text',
			'typing'
		]);
	};

	type = function ($e) {
		var // position = $e.data('typePosition'),
			text = $e.data('text'),
			oldLeft = $e.data('oldLeft'),
			oldRight = $e.data('oldRight');

		// if (!isNumber(position)) {
		// position = $e.data('leftStop');
		// }

		if (!text || text.length === 0) {
			clearData($e);
			return;
		}


		$e.text(
			oldLeft +
			text.charAt(0) +
			oldRight
		).data({
			oldLeft: oldLeft + text.charAt(0),
			text: text.substring(1)
		});

		// $e.text($e.text() + text.substring(position, position + 1));

		// $e.data('typePosition', position + 1);

		setTimeout(function () {
			type($e);
		}, getTypeInterval());
	};

	clearText = function ($e) {
		$e.find('span').remove();

		setTimeout(function () {
			type($e);
		}, typeDelay());
	};

	highlight = function ($e) {
		var position = $e.data('highlightPosition'),
			leftText,
			highlightedText,
			rightText;

		if (!isNumber(position)) {
			position = $e.data('rightStop') + 1;
		}

		if (position <= $e.data('leftStop')) {
			setTimeout(function () {
				clearText($e);
			}, clearDelay());
			return;
		}

		leftText = $e.text().substring(0, position - 1);
		highlightedText = $e.text().substring(position - 1, $e.data('rightStop') + 1);
		rightText = $e.text().substring($e.data('rightStop') + 1);

		$e.html(leftText)
		.append(
			spanWithColor(
				$e.data('backgroundColor'),
				$e.data('primaryColor')
			)
			.append(highlightedText)
		)
		.append(rightText);

		$e.data('highlightPosition', position - 1);

		setTimeout(function () {
			return highlight($e);
		}, getHighlightInterval());
	};

	typeWithAttribute = function ($e) {
		var targets;

		if ($e.data('typing')) {
			return;
		}

		try {
			targets = JSON.parse($e.attr($.typer.options.typerDataAttr)).targets;
		} catch (e) {}

		if (typeof targets === "undefined") {
			targets = $.map($e.attr($.typer.options.typerDataAttr).split(','), function (e) {
				return $.trim(e);
			});
		}

		$e.typeTo(targets[Math.floor(Math.random()*targets.length)]);
	};

	// Expose our options to the world.
	$.typer = (function () {
		return { options: options };
	})();

	$.extend($.typer, {
		options: options
	});

	//-- Methods to attach to jQuery sets
	$.fn.typer = function() {
		var $elements = $(this);

		return $elements.each(function () {
			var $e = $(this);

			if (typeof $e.attr($.typer.options.typerDataAttr) === "undefined") {
				return;
			}

			typeWithAttribute($e);

			setInterval(function () {
				typeWithAttribute($e);
			}, typerInterval());
		});
	};

	$.fn.typeTo = function (newString) {
		var $e = $(this),
			currentText = $e.text(),
			i = 0,
			j = 0;

		if (currentText === newString) {
			console.log("Our strings our equal, nothing to type");
			return $e;
		}

		if (currentText !== $e.html()) {
			console.error("Typer does not work on elements with child elements.");
			return $e;
		}

		$e.data('typing', true);

		while (currentText.charAt(i) === newString.charAt(i)) {
			i++;
		}

		while (currentText.rightChars(j) === newString.rightChars(j)) {
			j++;
		}

		newString = newString.substring(i, newString.length - j + 1);

		$e.data({
			oldLeft: currentText.substring(0, i),
			oldRight: currentText.rightChars(j - 1),
			leftStop: i,
			rightStop: currentText.length - j,
			primaryColor: $e.css('color'),
			backgroundColor: $e.css('background-color'),
			text: newString
		});

		highlight($e);

		return $e;
	};

	//-- Helper methods. These can one day be customized further to include things like ranges of delays.
	getHighlightInterval = function () {
		return $.typer.options.highlightSpeed;
	};

	getTypeInterval = function () {
		return $.typer.options.typeSpeed;
	},

	clearDelay = function () {
		return $.typer.options.clearDelay;
	},

	typeDelay = function () {
		return $.typer.options.typeDelay;
	};

	typerInterval = function () {
		return $.typer.options.typerInterval;
	};

	/*
	 * Fire off all of those functions
	 * -100% width/height div script
	 * -Responsive menu toggle
	 * -Typer.js
	 */
	$( window ).load( function() {
		changeDivHeight();
		menuToggle();
		jQuery('[data-typer-targets]').typer();
	} );


	//Update div height on window resize
	var resizable = true;
	$( window ).resize( function() {
		if (resizable) {
			changeDivHeight();

			resizable = false;
			setTimeout( function () {
				resizable = true;
			}, 300);
		}
	} );
} )( jQuery );