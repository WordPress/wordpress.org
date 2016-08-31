import React from "react";

const MIN_INTERVAL = 500;

function throttle( func, wait ) {
	let context, args, result;
	let timeout  = null;
	let previous = 0;

	let later = function () {
		previous = new Date().getTime();
		timeout  = null;
		result   = func.apply( context, args );

		if ( ! timeout ) {
			context = args = null;
		}
	};

	return function () {
		let now       = new Date().getTime();
		let remaining = wait - (now - previous);

		context = this;
		args = arguments;

		if ( remaining <= 0 || remaining > wait ) {
			if ( timeout ) {
				clearTimeout( timeout );
				timeout = null;
			}

			previous = now;
			result   = func.apply( context, args );

			if ( ! timeout ) {
				context = args = null;
			}
		} else if ( !timeout ) {
			timeout = setTimeout( later, remaining );
		}
		return result;
	};

 }

// This is to handle accessing event properties in an asynchronous way
// https://facebook.github.io/react/docs/events.html#syntheticevent
function debounceEventHandler( ...args ) {
	const throttled = throttle( ...args );
	return function ( event ) {
		if ( event ) {
			event.persist();
			return throttled( event );
		}

		return throttled();
	};
 }


export default class ImageGallery extends React.Component {

	constructor( props ) {
		super( props );
		this.state = {
			currentIndex: props.startIndex,
			thumbsTranslateX: 0,
			offsetPercentage: 0,
			galleryWidth: 0,
			thumbnailWidth: 0
		};
	}

	componentWillReceiveProps( nextProps ) {
		if ( this.props.disableArrowKeys !== nextProps.disableArrowKeys ) {
			if ( nextProps.disableArrowKeys ) {
				window.removeEventListener( "keydown", this._handleKeyDown );
			} else {
				window.addEventListener( "keydown", this._handleKeyDown );
			}
		}
	}

	componentDidUpdate( prevProps, prevState ) {
		if ( prevState.thumbnailWidth !== this.state.thumbnailWidth ||
			prevProps.showThumbnails !== this.props.showThumbnails ) {

			// Adjust thumbnail container when thumbnail width is adjusted.
			this._setThumbsTranslateX( -this._getThumbsTranslateX( this.state.currentIndex > 0 ? 1 : 0 ) * this.state.currentIndex );
		}

		if ( prevState.currentIndex !== this.state.currentIndex ) {
			this._updateThumbnailTranslateX( prevState );
		}
	}

	componentWillMount() {
		this._slideLeft  = debounceEventHandler( this._slideLeft.bind( this ),  MIN_INTERVAL, true );
		this._slideRight = debounceEventHandler( this._slideRight.bind( this ), MIN_INTERVAL, true );

		this._handleResize   = this._handleResize.bind( this );
		this._handleKeyDown  = this._handleKeyDown.bind( this );
		this._thumbnailDelay = 300;
	}

	componentDidMount() {

		// / Delay initial resize to get the accurate this._imageGallery.offsetWidth.
		window.setTimeout( () => this._handleResize(), 500 );

		if ( ! this.props.disableArrowKeys ) {
			window.addEventListener( "keydown", this._handleKeyDown );
		}

		window.addEventListener( "resize", this._handleResize );
	}

	componentWillUnmount() {
		if ( ! this.props.disableArrowKeys ) {
			window.removeEventListener( "keydown", this._handleKeyDown );
		}

		window.removeEventListener( "resize", this._handleResize );

		if ( this._intervalId ) {
			window.clearInterval( this._intervalId );
			this._intervalId = null;
		}
	}

	fullScreen() {
		const gallery = this._imageGallery;

		if ( gallery.requestFullscreen ) {
			gallery.requestFullscreen();
		} else if ( gallery.msRequestFullscreen ) {
			gallery.msRequestFullscreen();
		} else if ( gallery.mozRequestFullScreen ) {
			gallery.mozRequestFullScreen();
		} else if ( gallery.webkitRequestFullscreen ) {
			gallery.webkitRequestFullscreen();
		}
	}

	slideToIndex( index, event ) {
		if ( event ) {
			event.preventDefault();
		}

		let slideCount   = this.props.items.length - 1;
		let currentIndex = index;

		if ( index < 0 ) {
			currentIndex = slideCount;
		} else if ( index > slideCount ) {
			currentIndex = 0;
		}

		this.setState( {
			previousIndex: this.state.currentIndex,
			currentIndex: currentIndex,
			offsetPercentage: 0,
			style: {
				transition: "transform 0.45s ease-out"
			}
		} );
	}

	getCurrentIndex() {
		return this.state.currentIndex;
	}

	_handleResize() {
		if ( this._imageGallery ) {
			this.setState( { galleryWidth: this._imageGallery.offsetWidth } );
		}

		if ( this._imageGalleryThumbnail ) {
			this.setState( { thumbnailWidth: this._imageGalleryThumbnail.offsetWidth } );
		}
	}

	_handleKeyDown( event ) {
		const LEFT_ARROW  = 37;
		const RIGHT_ARROW = 39;
		const key         = parseInt( event.keyCode || event.which || 0 );

		switch ( key ) {
			case LEFT_ARROW:
				if ( this._canSlideLeft() && ! this._intervalId ) {
					this._slideLeft();
				}
				break;

			case RIGHT_ARROW:
				if ( this._canSlideRight() && ! this._intervalId ) {
					this._slideRight();
				}
				break;
		}
	}

	_handleMouseOverThumbnails( index ) {
		if ( this.props.slideOnThumbnailHover ) {
			this.setState( { hovering: true } );

			if ( this._thumbnailTimer ) {
				window.clearTimeout( this._thumbnailTimer );
				this._thumbnailTimer = null;
			}

			this._thumbnailTimer = window.setTimeout( () => {
				this.slideToIndex( index );
			}, this._thumbnailDelay );
		}
	}

	_handleMouseLeaveThumbnails() {
		if ( this._thumbnailTimer ) {
			window.clearTimeout( this._thumbnailTimer );
			this._thumbnailTimer = null;
		}
		this.setState( { hovering: false } );
	}

	_handleMouseOver() {
		this.setState( { hovering: true } );
	}

	_handleMouseLeave() {
		this.setState( { hovering: false } );
	}

	_handleImageError( event ) {
		if ( this.props.defaultImage && -1 === event.target.src.indexOf( this.props.defaultImage ) ) {
			event.target.src = this.props.defaultImage;
		}
	}

	_canNavigate() {
		return this.props.items.length >= 2;
	}

	_canSlideLeft() {
		return this.props.infinite || this.state.currentIndex > 0;
	}

	_canSlideRight() {
		return this.props.infinite || this.state.currentIndex < this.props.items.length - 1;
	}

	_updateThumbnailTranslateX( prevState ) {
		if ( this.state.currentIndex === 0 ) {
			this._setThumbsTranslateX( 0 );
		} else {
			let indexDifference = Math.abs( prevState.currentIndex - this.state.currentIndex );
			let scrollX         = this._getThumbsTranslateX( indexDifference );

			if ( scrollX > 0 ) {
				if ( prevState.currentIndex < this.state.currentIndex ) {
					this._setThumbsTranslateX( this.state.thumbsTranslateX - scrollX );
				} else if ( prevState.currentIndex > this.state.currentIndex ) {
					this._setThumbsTranslateX( this.state.thumbsTranslateX + scrollX );
				}
			}
		}
	}

	_setThumbsTranslateX( thumbsTranslateX ) {
		this.setState( { thumbsTranslateX } );
	}

	_getThumbsTranslateX( indexDifference ) {
		if ( this.props.disableThumbnailScroll ) {
			return 0;
		}

		const { thumbnailWidth } = this.state;

		if ( this._thumbnails ) {
			if ( this._thumbnails.scrollWidth <= thumbnailWidth ) {
				return 0;
			}

			let totalThumbnails = this._thumbnails.children.length;
			// Total scroll-x required to see the last thumbnail.
			let totalScrollX = this._thumbnails.scrollWidth - thumbnailWidth;
			// Scroll-x required per index change.
			let perIndexScrollX = totalScrollX / ( totalThumbnails - 1 );

			return indexDifference * perIndexScrollX;
		}
	}

	_getAlignmentClassName( index ) {
		// LEFT, and RIGHT alignments are necessary for lazyLoad.
		let { currentIndex } = this.state;
		let alignment        = "";

		const LEFT   = "left";
		const CENTER = "center";
		const RIGHT  = "right";

		switch ( index ) {
			case currentIndex - 1:
				alignment = ` ${ LEFT }`;
				break;

			case currentIndex:
				alignment = ` ${ CENTER }`;
				break;

			case currentIndex + 1:
				alignment = ` ${ RIGHT }`;
				break;
		}

		if ( this.props.items.length >= 3 && this.props.infinite ) {
			if ( index === 0 && currentIndex === this.props.items.length - 1 ) {

				// Set first slide as right slide if were sliding right from last slide.
				alignment = ` ${ RIGHT }`;
			} else if ( index === this.props.items.length - 1 && currentIndex === 0 ) {

				// Set last slide as left slide if were sliding left from first slide.
				alignment = ` ${ LEFT }`;
			}
		}

		return alignment;
	}

	_getTranslateXForTwoSlide( index ) {

		// For taking care of infinite swipe when there are only two slides.
		const { currentIndex, offsetPercentage, previousIndex } = this.state;
		const baseTranslateX = -100 * currentIndex;
		let translateX = baseTranslateX + (index * 100) + offsetPercentage;

		// Keep track of user swiping direction.
		if ( offsetPercentage > 0 ) {
			this.direction = 'left';
		} else if ( offsetPercentage < 0 ) {
			this.direction = 'right';
		}

		// when swiping make sure the slides are on the correct side
		if ( currentIndex === 0 && index === 1 && offsetPercentage > 0 ) {
			translateX = -100 + offsetPercentage;
		} else if ( currentIndex === 1 && index === 0 && offsetPercentage < 0 ) {
			translateX = 100 + offsetPercentage;
		}

		if ( currentIndex !== previousIndex ) {

			// When swiped move the slide to the correct side.
			if ( 0 === previousIndex && 0 === index && 0 === offsetPercentage && 'left' === this.direction ) {
				translateX = 100;
			} else if ( 1 === previousIndex && 1 === index && 0 === offsetPercentage && 'right' === this.direction ) {
				translateX = -100;
			}
		} else {

			// Keep the slide on the correct slide even when not a swipe.
			if ( 0 === currentIndex && 1 === index && 0 === offsetPercentage && 'left' === this.direction ) {
				translateX = -100;
			} else if ( 1 === currentIndex && 0 === index && 0 === offsetPercentage && 'right' === this.direction ) {
				translateX = 100;
			}
		}

		return translateX;
	}

	_getSlideStyle( index ) {
		const { currentIndex, offsetPercentage } = this.state;
		const { infinite, items } = this.props;
		const baseTranslateX = -100 * currentIndex;
		const totalSlides = items.length - 1;

		// calculates where the other slides belong based on currentIndex
		let translateX = baseTranslateX + (index * 100) + offsetPercentage;

		// adjust zIndex so that only the current slide and the slide were going
		// to is at the top layer, this prevents transitions from flying in the
		// background when swiping before the first slide or beyond the last slide
		let zIndex = 1;
		if ( index === currentIndex ) {
			zIndex = 3;
		} else if ( index === this.state.previousIndex ) {
			zIndex = 2;
		}

		if ( infinite && items.length > 2 ) {
			if ( currentIndex === 0 && index === totalSlides ) {
				// make the last slide the slide before the first
				translateX = -100 + offsetPercentage;
			} else if ( currentIndex === totalSlides && index === 0 ) {
				// make the first slide the slide after the last
				translateX = 100 + offsetPercentage;
			}
		}

		// Special case when there are only 2 items with infinite on
		if ( infinite && items.length === 2 ) {
			translateX = this._getTranslateXForTwoSlide( index );
		}

		const translate3d = `translate3d(${ translateX }%, 0, 0)`;

		return {
			WebkitTransform: translate3d,
			MozTransform: translate3d,
			msTransform: translate3d,
			OTransform: translate3d,
			transform: translate3d,
			zIndex: zIndex
		};
	}

	_getThumbnailStyle() {
		const translate3d = `translate3d(${ this.state.thumbsTranslateX }px, 0, 0)`;
		return {
			WebkitTransform: translate3d,
			MozTransform: translate3d,
			msTransform: translate3d,
			OTransform: translate3d,
			transform: translate3d
		};
	}

	_slideLeft( event ) {
		this.slideToIndex( this.state.currentIndex - 1, event );
	}

	_slideRight( event ) {
		this.slideToIndex( this.state.currentIndex + 1, event );
	}

	_renderItem( item ) {
		return (
			<figure className="image-gallery-image">
				<img
					src={ item.original }
					alt={ item.originalAlt }
					srcSet={ item.srcSet }
					sizes={ item.sizes }
					onLoad={ this.props.onImageLoad }
					onError={ this._handleImageError.bind( this ) }
				/>
				{
					item.description &&
					<figcaption className="image-gallery-description">
						{ item.description }
					</figcaption>
				}
			</figure>
		);
	}

	render() {
		const { currentIndex } = this.state;
		const thumbnailStyle   = this._getThumbnailStyle();
		const slideLeft        = this._slideLeft.bind( this );
		const slideRight       = this._slideRight.bind( this );

		let slides     = [];
		let thumbnails = [];

		this.props.items.map( ( item, index ) => {
			const alignment      = this._getAlignmentClassName( index );
			const originalClass  = item.originalClass ? ` ${ item.originalClass }` : '';
			const thumbnailClass = item.thumbnailClass ? ` ${ item.thumbnailClass }` : '';

			const renderItem = item.renderItem || this.props.renderItem || this._renderItem.bind( this );

			const slide = (
				<div
					key={ index }
					className={ 'image-gallery-slide' + alignment + originalClass }
					style={ Object.assign( this._getSlideStyle( index ), this.state.style ) }
					onClick={ this.props.onClick }
				>
					{ renderItem( item ) }
				</div>
			);

			if ( this.props.lazyLoad ) {
				if ( alignment ) {
					slides.push( slide );
				}
			} else {
				slides.push( slide );
			}

			thumbnails.push(
				<button
					type="button"
					onMouseOver={ this._handleMouseOverThumbnails.bind( this, index ) }
					onMouseLeave={ this._handleMouseLeaveThumbnails.bind( this, index ) }
					key={ index }
					className={
						'button-link image-gallery-thumbnail' +
						( currentIndex === index ? ' active' : '' ) +
						thumbnailClass
					}
					onTouchStart={ event => this.slideToIndex.call( this, index, event ) }
					onClick={ event => this.slideToIndex.call( this, index, event ) }
				>
					<img
						src={ item.thumbnail }
						alt={ item.thumbnailAlt }
						onError={ this._handleImageError.bind( this ) } />
					<div className="image-gallery-thumbnail-label">
						{ item.thumbnailLabel }
					</div>
				</button>
			);
		} );

		return (
			<section ref={ i => this._imageGallery = i } className="image-gallery">
				<div
					onMouseOver={ this._handleMouseOver.bind( this ) }
					onMouseLeave={ this._handleMouseLeave.bind( this ) }
					className="image-gallery-content">
					{
						this._canNavigate() ?
							[
								this.props.showNav &&
								<span key="navigation">
									{
										this._canSlideLeft() &&
										<button
											type="button"
											className="button-link image-gallery-left-nav"
											onTouchStart={ slideLeft }
											onClick={ slideLeft }/>
									}
									{
										this._canSlideRight() &&
										<button
											type="button"
											className="button-link image-gallery-right-nav"
											onTouchStart={ slideRight }
											onClick={ slideRight }/>
									}
								</span>,

								<div className="image-gallery-slides">{ slides }</div>
							]
							:
							<div className="image-gallery-slides">{ slides }</div>
					}
					{
						this.props.showIndex &&
						<div className="image-gallery-index">
							<span className="image-gallery-index-current">
								{ this.state.currentIndex + 1 }
							</span>
							<span className="image-gallery-index-separator">
								{ this.props.indexSeparator }
							</span>
							<span className="image-gallery-index-total">
								{ this.props.items.length }
							</span>
						</div>
					}
				</div>
				<div
					className="image-gallery-thumbnails"
					ref={ i => this._imageGalleryThumbnail = i }
				>
					<div
						ref={ t => this._thumbnails = t }
						className="image-gallery-thumbnails-container"
						style={ thumbnailStyle }
					>
						{ thumbnails }
					</div>
				</div>
			</section>
		);
	}
}

ImageGallery.propTypes = {
	items: React.PropTypes.array.isRequired,
	showNav: React.PropTypes.bool,
	lazyLoad: React.PropTypes.bool,
	infinite: React.PropTypes.bool,
	showIndex: React.PropTypes.bool,
	showThumbnails: React.PropTypes.bool,
	slideOnThumbnailHover: React.PropTypes.bool,
	disableThumbnailScroll: React.PropTypes.bool,
	disableArrowKeys: React.PropTypes.bool,
	defaultImage: React.PropTypes.string,
	indexSeparator: React.PropTypes.string,
	startIndex: React.PropTypes.number,
	slideInterval: React.PropTypes.number,
	onClick: React.PropTypes.func,
	onImageLoad: React.PropTypes.func,
	onImageError: React.PropTypes.func,
	renderItem: React.PropTypes.func,
};

ImageGallery.defaultProps = {
	items: [],
	showNav: true,
	lazyLoad: false,
	infinite: true,
	showIndex: false,
	showThumbnails: true,
	slideOnThumbnailHover: false,
	disableThumbnailScroll: false,
	disableArrowKeys: false,
	indexSeparator: " / ",
	startIndex: 0,
	slideInterval: 3000
};
