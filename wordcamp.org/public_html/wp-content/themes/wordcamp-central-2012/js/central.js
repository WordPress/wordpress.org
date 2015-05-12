/**
 * @name MarkerClusterer for Google Maps v3
 * @version version 1.0
 * @author Luke Mahe
 *
 * The library creates and manages per-zoom-level clusters for large amounts of
 * markers.
 */
(function(){var d=null;function e(a){return function(b){this[a]=b}}function h(a){return function(){return this[a]}}var j;
	function k(a,b,c){this.extend(k,google.maps.OverlayView);this.c=a;this.a=[];this.f=[];this.ca=[53,56,66,78,90];this.j=[];this.A=!1;c=c||{};this.g=c.gridSize||60;this.l=c.minimumClusterSize||2;this.J=c.maxZoom||d;this.j=c.styles||[];this.X=c.imagePath||this.Q;this.W=c.imageExtension||this.P;this.O=!0;if(c.zoomOnClick!=void 0)this.O=c.zoomOnClick;this.r=!1;if(c.averageCenter!=void 0)this.r=c.averageCenter;l(this);this.setMap(a);this.K=this.c.getZoom();var f=this;google.maps.event.addListener(this.c,
		"zoom_changed",function(){var a=f.c.getZoom();if(f.K!=a)f.K=a,f.m()});google.maps.event.addListener(this.c,"idle",function(){f.i()});b&&b.length&&this.C(b,!1)}j=k.prototype;j.Q="http://google-maps-utility-library-v3.googlecode.com/svn/trunk/markerclusterer/images/m";j.P="png";j.extend=function(a,b){return function(a){for(var b in a.prototype)this.prototype[b]=a.prototype[b];return this}.apply(a,[b])};j.onAdd=function(){if(!this.A)this.A=!0,n(this)};j.draw=function(){};
	function l(a){if(!a.j.length)for(var b=0,c;c=a.ca[b];b++)a.j.push({url:a.X+(b+1)+"."+a.W,height:c,width:c})}j.S=function(){for(var a=this.o(),b=new google.maps.LatLngBounds,c=0,f;f=a[c];c++)b.extend(f.getPosition());this.c.fitBounds(b)};j.z=h("j");j.o=h("a");j.V=function(){return this.a.length};j.ba=e("J");j.I=h("J");j.G=function(a,b){for(var c=0,f=a.length,g=f;g!==0;)g=parseInt(g/10,10),c++;c=Math.min(c,b);return{text:f,index:c}};j.$=e("G");j.H=h("G");
	j.C=function(a,b){for(var c=0,f;f=a[c];c++)q(this,f);b||this.i()};function q(a,b){b.s=!1;b.draggable&&google.maps.event.addListener(b,"dragend",function(){b.s=!1;a.L()});a.a.push(b)}j.q=function(a,b){q(this,a);b||this.i()};function r(a,b){var c=-1;if(a.a.indexOf)c=a.a.indexOf(b);else for(var f=0,g;g=a.a[f];f++)if(g==b){c=f;break}if(c==-1)return!1;b.setMap(d);a.a.splice(c,1);return!0}j.Y=function(a,b){var c=r(this,a);return!b&&c?(this.m(),this.i(),!0):!1};
	j.Z=function(a,b){for(var c=!1,f=0,g;g=a[f];f++)g=r(this,g),c=c||g;if(!b&&c)return this.m(),this.i(),!0};j.U=function(){return this.f.length};j.getMap=h("c");j.setMap=e("c");j.w=h("g");j.aa=e("g");
	j.v=function(a){var b=this.getProjection(),c=new google.maps.LatLng(a.getNorthEast().lat(),a.getNorthEast().lng()),f=new google.maps.LatLng(a.getSouthWest().lat(),a.getSouthWest().lng()),c=b.fromLatLngToDivPixel(c);c.x+=this.g;c.y-=this.g;f=b.fromLatLngToDivPixel(f);f.x-=this.g;f.y+=this.g;c=b.fromDivPixelToLatLng(c);b=b.fromDivPixelToLatLng(f);a.extend(c);a.extend(b);return a};j.R=function(){this.m(!0);this.a=[]};
	j.m=function(a){for(var b=0,c;c=this.f[b];b++)c.remove();for(b=0;c=this.a[b];b++)c.s=!1,a&&c.setMap(d);this.f=[]};j.L=function(){var a=this.f.slice();this.f.length=0;this.m();this.i();window.setTimeout(function(){for(var b=0,c;c=a[b];b++)c.remove()},0)};j.i=function(){n(this)};
	function n(a){if(a.A)for(var b=a.v(new google.maps.LatLngBounds(a.c.getBounds().getSouthWest(),a.c.getBounds().getNorthEast())),c=0,f;f=a.a[c];c++)if(!f.s&&b.contains(f.getPosition())){for(var g=a,u=4E4,o=d,v=0,m=void 0;m=g.f[v];v++){var i=m.getCenter();if(i){var p=f.getPosition();if(!i||!p)i=0;else var w=(p.lat()-i.lat())*Math.PI/180,x=(p.lng()-i.lng())*Math.PI/180,i=Math.sin(w/2)*Math.sin(w/2)+Math.cos(i.lat()*Math.PI/180)*Math.cos(p.lat()*Math.PI/180)*Math.sin(x/2)*Math.sin(x/2),i=6371*2*Math.atan2(Math.sqrt(i),
			Math.sqrt(1-i));i<u&&(u=i,o=m)}}o&&o.F.contains(f.getPosition())?o.q(f):(m=new s(g),m.q(f),g.f.push(m))}}function s(a){this.k=a;this.c=a.getMap();this.g=a.w();this.l=a.l;this.r=a.r;this.d=d;this.a=[];this.F=d;this.n=new t(this,a.z(),a.w())}j=s.prototype;
	j.q=function(a){var b;a:if(this.a.indexOf)b=this.a.indexOf(a)!=-1;else{b=0;for(var c;c=this.a[b];b++)if(c==a){b=!0;break a}b=!1}if(b)return!1;if(this.d){if(this.r)c=this.a.length+1,b=(this.d.lat()*(c-1)+a.getPosition().lat())/c,c=(this.d.lng()*(c-1)+a.getPosition().lng())/c,this.d=new google.maps.LatLng(b,c),y(this)}else this.d=a.getPosition(),y(this);a.s=!0;this.a.push(a);b=this.a.length;b<this.l&&a.getMap()!=this.c&&a.setMap(this.c);if(b==this.l)for(c=0;c<b;c++)this.a[c].setMap(d);b>=this.l&&a.setMap(d);
		a=this.c.getZoom();if((b=this.k.I())&&a>b)for(a=0;b=this.a[a];a++)b.setMap(this.c);else if(this.a.length<this.l)z(this.n);else{b=this.k.H()(this.a,this.k.z().length);this.n.setCenter(this.d);a=this.n;a.B=b;a.ga=b.text;a.ea=b.index;if(a.b)a.b.innerHTML=b.text;b=Math.max(0,a.B.index-1);b=Math.min(a.j.length-1,b);b=a.j[b];a.da=b.url;a.h=b.height;a.p=b.width;a.M=b.textColor;a.e=b.anchor;a.N=b.textSize;a.D=b.backgroundPosition;this.n.show()}return!0};
	j.getBounds=function(){for(var a=new google.maps.LatLngBounds(this.d,this.d),b=this.o(),c=0,f;f=b[c];c++)a.extend(f.getPosition());return a};j.remove=function(){this.n.remove();this.a.length=0;delete this.a};j.T=function(){return this.a.length};j.o=h("a");j.getCenter=h("d");function y(a){a.F=a.k.v(new google.maps.LatLngBounds(a.d,a.d))}j.getMap=h("c");
	function t(a,b,c){a.k.extend(t,google.maps.OverlayView);this.j=b;this.fa=c||0;this.u=a;this.d=d;this.c=a.getMap();this.B=this.b=d;this.t=!1;this.setMap(this.c)}j=t.prototype;
	j.onAdd=function(){this.b=document.createElement("DIV");if(this.t)this.b.style.cssText=A(this,B(this,this.d)),this.b.innerHTML=this.B.text;this.getPanes().overlayMouseTarget.appendChild(this.b);var a=this;google.maps.event.addDomListener(this.b,"click",function(){var b=a.u.k;google.maps.event.trigger(b,"clusterclick",a.u);b.O&&a.c.fitBounds(a.u.getBounds())})};function B(a,b){var c=a.getProjection().fromLatLngToDivPixel(b);c.x-=parseInt(a.p/2,10);c.y-=parseInt(a.h/2,10);return c}
	j.draw=function(){if(this.t){var a=B(this,this.d);this.b.style.top=a.y+"px";this.b.style.left=a.x+"px"}};function z(a){if(a.b)a.b.style.display="none";a.t=!1}j.show=function(){if(this.b)this.b.style.cssText=A(this,B(this,this.d)),this.b.style.display="";this.t=!0};j.remove=function(){this.setMap(d)};j.onRemove=function(){if(this.b&&this.b.parentNode)z(this),this.b.parentNode.removeChild(this.b),this.b=d};j.setCenter=e("d");
	function A(a,b){var c=[];c.push("background-image:url("+a.da+");");c.push("background-position:"+(a.D?a.D:"0 0")+";");typeof a.e==="object"?(typeof a.e[0]==="number"&&a.e[0]>0&&a.e[0]<a.h?c.push("height:"+(a.h-a.e[0])+"px; padding-top:"+a.e[0]+"px;"):c.push("height:"+a.h+"px; line-height:"+a.h+"px;"),typeof a.e[1]==="number"&&a.e[1]>0&&a.e[1]<a.p?c.push("width:"+(a.p-a.e[1])+"px; padding-left:"+a.e[1]+"px;"):c.push("width:"+a.p+"px; text-align:center;")):c.push("height:"+a.h+"px; line-height:"+a.h+
	"px; width:"+a.p+"px; text-align:center;");c.push("cursor:pointer; top:"+b.y+"px; left:"+b.x+"px; color:"+(a.M?a.M:"black")+"; position:absolute; font-size:"+(a.N?a.N:11)+"px; font-family:Arial,sans-serif; font-weight:bold");return c.join("")}window.MarkerClusterer=k;k.prototype.addMarker=k.prototype.q;k.prototype.addMarkers=k.prototype.C;k.prototype.clearMarkers=k.prototype.R;k.prototype.fitMapToMarkers=k.prototype.S;k.prototype.getCalculator=k.prototype.H;k.prototype.getGridSize=k.prototype.w;
	k.prototype.getExtendedBounds=k.prototype.v;k.prototype.getMap=k.prototype.getMap;k.prototype.getMarkers=k.prototype.o;k.prototype.getMaxZoom=k.prototype.I;k.prototype.getStyles=k.prototype.z;k.prototype.getTotalClusters=k.prototype.U;k.prototype.getTotalMarkers=k.prototype.V;k.prototype.redraw=k.prototype.i;k.prototype.removeMarker=k.prototype.Y;k.prototype.removeMarkers=k.prototype.Z;k.prototype.resetViewport=k.prototype.m;k.prototype.repaint=k.prototype.L;k.prototype.setCalculator=k.prototype.$;
	k.prototype.setGridSize=k.prototype.aa;k.prototype.setMaxZoom=k.prototype.ba;k.prototype.onAdd=k.prototype.onAdd;k.prototype.draw=k.prototype.draw;s.prototype.getCenter=s.prototype.getCenter;s.prototype.getSize=s.prototype.T;s.prototype.getMarkers=s.prototype.o;t.prototype.onAdd=t.prototype.onAdd;t.prototype.draw=t.prototype.draw;t.prototype.onRemove=t.prototype.onRemove;
})();


/**
 * WordCampCentral
 *
 * Custom client-side behaviors for the Central theme
 */
var WordCampCentral = ( function( $ ) {
	// templateOptions is copied from Core in order to avoid an extra HTTP request just to get wp.template
	var options,
		templateOptions = {
			evaluate:    /<#([\s\S]+?)#>/g,
			interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
			escape:      /\{\{([^\}]+?)\}\}(?!\})/g
		};

	/**
	 * Initialization that runs as soon as this file has loaded
	 *
	 * @param {object} initOptions
	 */
	function immediateInit( initOptions ) {
		options = initOptions;
		initOptions = null;

		try {
			toggleNavigation();
			populateLatestTweets();
		} catch ( exception ) {
			log( exception );
		}
	}

	/**
	 * Initialization that runs when the document has fully loaded
	 */
	function documentReadyInit() {
		try {
			if ( options.hasOwnProperty( 'mapContainer' ) && options.hasOwnProperty( 'mapMarkers' ) ) {
				loadMap( options.mapContainer, options.mapMarkers );
			}
		} catch ( exception ) {
			log( exception );
		}
	}

	/**
	 * Toggle the navigation menu for small screens.
	 */
	function toggleNavigation() {
		var container, button, menu;

		container = document.getElementById( 'access' );
		if ( ! container ) {
			return;
		}

		button = container.getElementsByTagName( 'button' )[0];
		if ( 'undefined' === typeof button ) {
			return;
		}

		menu = container.getElementsByTagName( 'ul' )[0];

		// Hide menu toggle button if menu is empty and return early.
		if ( 'undefined' === typeof menu ) {
			button.style.display = 'none';
			return;
		}

		if ( -1 === menu.className.indexOf( 'nav-menu' ) ) {
			menu.className += ' nav-menu';
		}

		button.onclick = function() {
			if ( -1 !== container.className.indexOf( 'toggled' ) ) {
				container.className = container.className.replace( ' toggled', '' );
			} else {
				container.className += ' toggled';
			}
		};
	}

	/**
	 * Fetch the latest tweets and inject them into the DOM
	 */
	function populateLatestTweets() {
		var tweetsContainer = $( '#wc-tweets-container' );

		if ( ! tweetsContainer.length ) {
			return;
		}

		$.getJSON(
			options.ajaxURL,
			{ action: 'get_latest_wordcamp_tweets' },
			function( response ) {
				var index, tweets,
					spinner         = $( '#wc-tweets-spinner' ),
					error           = $( '#wc-tweets-error' ),
					tweetTemplate   = _.template( $( '#tmpl-wc-tweet' ).html(), null, templateOptions );

				// Check for success
				if ( response.hasOwnProperty( 'data' ) && response.data.hasOwnProperty( 'tweets' ) && response.data.tweets instanceof Array ) {
					tweets = response.data.tweets;
				} else {
					spinner.addClass(  'hidden' );
					error.removeClass( 'hidden' );
					error.removeAttr(  'hidden' );
					return;
				}

				// Populate and reveal the container
				for ( index in tweets ) {
					if ( tweets.hasOwnProperty( index ) ) {
						tweetsContainer.append( tweetTemplate( { 'tweet': tweets[ index ] } ) );
					}
				}

				spinner.addClass( 'hidden' );
				tweetsContainer.removeClass( 'transparent' );
			}
		);
	}

	/**
	 * Build a Google Map in the given container with the given marker data
	 *
	 * @param {string} container
	 * @param {object} markers
	 */
	function loadMap( container, markers ) {
		if ( ! $( '#' + container ).length ) {
			throw "Map container element isn't present in the DOM.";
		}

		if ( 'undefined' === typeof( google ) || ! google.hasOwnProperty( 'maps' ) ) {
			throw 'Google Maps library is not loaded.';
		}

		var map, markerCluster,
			mapOptions = {
				center            : new google.maps.LatLng( 15.000, 7.000 ),
				zoom              : 2,
				zoomControl       : true,
				mapTypeControl    : false,
				streetViewControl : false
		};

		map     = new google.maps.Map( document.getElementById( container ), mapOptions );
		markers = createMarkers(  map, markers );

		/*
		 * The About map contains all camps, past and present, so there will be camps from different years that
		 * are located in the same venue, and their markers will overlap.
		 */
		if ( 'wcc-map-about' == container ) {
			markers = repositionOverlappingMarkers( markers );
		}

		markerCluster = clusterMarkers( map, markers );
	}

	/**
	 * Create markers on a map with the given marker data
	 *
	 * Normally the markers would be assigned to the map at this point, but we'll run them through MarkerClusterer
	 * later on, so adding them to the map now is unnecessary and negatively affects performance.
	 *
	 * @param {google.maps.Map} map
	 * @param {object}          markers
	 *
	 * @return {object}
	 */
	function createMarkers( map, markers ) {
		var markerID,
			infoWindowTemplate = _.template( $( '#tmpl-wcc-map-marker' ).html(), null, templateOptions ),
			infoWindow         = new google.maps.InfoWindow( {
				pixelOffset: new google.maps.Size( -options.markerIconAnchorXOffset, 0 )
			} );

		for ( markerID in markers ) {
			if ( ! markers.hasOwnProperty( markerID ) ) {
				continue;
			}

			markers[ markerID ] = new google.maps.Marker( {
				wccID     : markerID,
				wccURL    : markers[ markerID ].url,
				wccDates  : markers[ markerID ].dates,
				location  : markers[ markerID ].location,
				venueName : markers[ markerID ].venueName,
				title     : markers[ markerID ].name,

				icon : {
					url        : options.markerIconBaseURL + markers[ markerID ].iconURL,
					size       : new google.maps.Size(  options.markerIconHeight,        options.markerIconWidth ),
					anchor     : new google.maps.Point( options.markerIconAnchorXOffset, options.markerIconWidth / 2 ),
					scaledSize : new google.maps.Size(  options.markerIconHeight / 2,    options.markerIconWidth / 2 )
				},

				position : new google.maps.LatLng(
					markers[ markerID ].latitude,
					markers[ markerID ].longitude
				)
			} );

			google.maps.event.addListener( markers[ markerID ], 'click', function() {
				try {
					infoWindow.setContent( infoWindowTemplate( { wordcamp: markers[ this.wccID ] } ) );
					infoWindow.open( map, markers[ this.wccID ] );
				} catch ( exception ) {
					log( exception );
				}
			} );
		}

		return markers;
	}

	/**
	 * Offset the position of overlapping markers
	 *
	 * Often camps will use the same venue for multiple years, and those map pins would be placed in the exact same
	 * spot by default, making it impossible to open the overlaid pins, or even know that they're there. This
	 * function will spread overlapping pins out in a circle around the original point.
	 *
	 * @todo It'd be nice to adjust the distance on the fly based on the zoom level, so that you could always see
	 *       that there are multiple markers at a given location, rather than having to zoom in to the neighborhood
	 *       level to know that.
	 *
	 * @param {object} markers
	 *
	 * @returns {object}
	 */
	function repositionOverlappingMarkers( markers ) {
		var groupedMarkers = groupMarkersByCoordinates( markers );

		_.each( groupedMarkers, function( markerGroup ) {
			var markerGroupSize = _.size( markerGroup );

			if ( markerGroupSize > 1 ) {
				var currentMarkerIndex = 1,
					distance           = markerGroupSize == 2 ? .1 : .5;  // when there are only 2 markers, it's not as obvious that they're centered around a point in the middle

				_.each( markerGroup, function( marker, markerID ) {
					var bearing     = currentMarkerIndex / markerGroupSize * 360 + 90,
						newPosition = calculateDestinationPoint( marker.getPosition(), bearing, distance );

					markers[ markerID ].setPosition( newPosition );
					currentMarkerIndex++;
				} );
			}
		} );

		return markers;
	}

	/**
	 * Group markers by their coordinates
	 *
	 * @param {object} markers
	 *
	 * @returns {object}
	 */
	function groupMarkersByCoordinates( markers ) {
		var groupedMarkers = {};

		_.each( markers, function( marker, markerID ) {
			var position    = marker.getPosition(),
				coordinates = position.lat() + '|' + position.lng();

			if ( ! groupedMarkers.hasOwnProperty( coordinates ) ) {
				groupedMarkers[ coordinates ] = {};
			}

			groupedMarkers[ coordinates ][ markerID ] = marker;
		} );

		return groupedMarkers;
	}

	/**
	 * Calculate the destination point after moving a distance with a bearing from a starting point
	 *
	 * Based on http://www.movable-type.co.uk/scripts/latlong.html
	 *
	 * @param {google.maps.LatLng} startPosition
	 * @param {number}             bearing in degrees
	 * @param {number}             distance in kilometers
	 *
	 * @returns {google.maps.LatLng}
	 */
	function calculateDestinationPoint( startPosition, bearing, distance ) {
		var startLatitude, startLongitude, newLatitude, newLongitude,
			earthRadius = 6371; // in kilometers

		startLatitude  = startPosition.lat().toRadians();
		startLongitude = startPosition.lng().toRadians();
		bearing        = bearing.toRadians();

		newLatitude = Math.asin(
			Math.sin( startLatitude )          *
			Math.cos( distance / earthRadius ) +
			Math.cos( startLatitude )          *
			Math.sin( distance / earthRadius ) *
			Math.cos( bearing )
		);

		newLongitude = startLongitude + Math.atan2(
			Math.sin( bearing )                *
			Math.sin( distance / earthRadius ) *
			Math.cos( startLatitude ),

			Math.cos( distance / earthRadius ) -
			Math.sin( startLatitude )          *
			Math.sin( newLatitude )
		);

		return new google.maps.LatLng( newLatitude.toDegrees(), newLongitude.toDegrees() );
	}

	/**
	 * Cluster the markers into groups for improved performance and UX
	 *
	 * options.markerClusterIcon is just 1x size, because MarkerClusterer doesn't support retina images.
	 * MarkerClusterer Plus does, but it doesn't seem as official, so I'm not as confident that it's secure,
	 * stable, etc.
	 *
	 * @todo the location of clustered pins is shifted off the center of where it should be when zoomed out,
	 *       and shifts closer to the actual location each time you zoom in
	 *
	 * @param {google.maps.Map} map
	 * @param {object}          markers
	 *
	 * @return MarkerClusterer
	 */
	function clusterMarkers( map, markers ) {
		var clusterOptions,
			markersArray = [];

		/*
		 * We're storing markers in an object so that they can be accessed directly by ID, rather than having to
		 * loop through them to find one. MarkerClusterer requires them to be passed in as an object, though, so
		 * we need to convert them here.
		 */
		for ( var m in markers ) {
			markersArray.push( markers[ m ] );
		}

		clusterOptions = {
			maxZoom:  11,
			gridSize: 30,
			styles:   [
				{
					url:       options.markerIconBaseURL + options.markerClusterIcon,
					height:    options.markerIconWidth  / 2,
					width:     options.markerIconHeight / 2,
					anchor:    [ 5, -0 ],
					textColor: '#ffffff',
					textSize:  22
				},

				{
					url:       options.markerIconBaseURL + options.markerClusterIcon,
					height:    options.markerIconWidth  / 2,
					width:     options.markerIconHeight / 2,
					anchor:    [ 5, -5 ],
					textColor: '#ffffff',
					textSize:  18
				},

				{
					url:       options.markerIconBaseURL + options.markerClusterIcon,
					height:    options.markerIconWidth  / 2,
					width:     options.markerIconHeight / 2,
					anchor:    [ 5, -5 ],
					textColor: '#ffffff',
					textSize:  18
				}
			]
		};

		return new MarkerClusterer( map, markersArray, clusterOptions );
	}

	/**
	 * Log a message to the console
	 *
	 * @param {*} message
	 */
	function log( message ) {
		if ( ! window.console ) {
			return;
		}

		if ( 'string' == typeof( message ) ) {
			console.log( 'WordCampCentral: ' + message );
		} else {
			console.log( 'WordCampCentral: ', message );
		}
	}

	return {
		immediateInit:     immediateInit,
		documentReadyInit: documentReadyInit
	};
} )( jQuery );

if ( 'undefined' === typeof( Number.prototype.toRadians ) ) {
	/**
	 * Convert degrees to radians
	 *
	 * @returns {number}
	 */
	Number.prototype.toRadians = function() {
		return this * Math.PI / 180;
	};
}

if ( 'undefined' === typeof( Number.prototype.toDegrees ) ) {
	/**
	 * Convert radians to degrees
	 *
	 * @returns {number}
	 */
	Number.prototype.toDegrees = function() {
		return this * 180 / Math.PI;
	};
}

WordCampCentral.immediateInit( wordcampCentralOptions );
jQuery( document ).ready( WordCampCentral.documentReadyInit );
