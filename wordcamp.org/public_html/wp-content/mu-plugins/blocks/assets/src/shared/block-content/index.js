/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { Disabled } = wp.components;
const { Fragment, RawHTML } = wp.element;
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import './style.scss';

export function ItemTitle( { headingLevel, className, title, link } ) {
	const validLevels = [ 1, 2, 3, 4, 5, 6 ];
	let Tag = 'h3';

	if ( validLevels.includes( headingLevel ) ) {
		Tag = 'h' + headingLevel;
	}

	const classes = [
		'wordcamp-item-title',
		className,
	];

	const content = title || __( '(Untitled)', 'wordcamporg' );

	return (
		<Tag className={ classnames( classes ) }>
			{ link &&
				<Disabled>
					<a href={ link }>
						{ content }
					</a>
				</Disabled>
			}

			{ ! link &&
				<Fragment>
					{ content }
				</Fragment>
			}
		</Tag>
	);
}

export function ItemHTMLContent( { className, content, link, linkText } ) {
	const classes = [
		'wordcamp-item-content',
		className,
	];

	return (
		<div className={ classnames( classes ) }>
			<Disabled>
				<RawHTML children={ content } />
				{ link &&
					<ItemPermalink
						link={ link }
						linkText={ linkText }
					/>
				}
			</Disabled>
		</div>
	);
}

export function ItemPermalink( { className, link, linkText } ) {
	const classes = [
		'wordcamp-item-permalink',
		className,
	];

	return (
		<p className={ classnames( classes ) }>
			<a href={ link }>
				{ linkText || __( 'Read more', 'wordcamporg' ) }
			</a>
		</p>
	);
}
