/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { Disabled, Spinner } = wp.components;
const { Fragment, RawHTML } = wp.element;
const { __ }                = wp.i18n;

/**
 * Internal dependencies
 */
import './style.scss';

/**
 * Component for indicating why there is no content.
 *
 * @param {Object} props {
 *     @type {boolean} loading
 * }
 *
 * @return {Element}
 */
export function BlockNoContent( { loading } ) {
	return (
		<div className="wordcamp-block-content-none">
			{ loading ?
				<Spinner /> :
				__( 'No content found.', 'wordcamporg' )
			}
		</div>
	);
}

/**
 * Component for an entity title, optionally linked.
 *
 * @param {Object} props {
 *     @type {number} headingLevel
 *     @type {string} className
 *     @type {string} title
 *     @type {string} link
 * }
 *
 * @return {Element}
 */
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

/**
 * Component for an entity's content, with an optional permalink appended.
 *
 * DO NOT use this to output untrusted content. Note that this takes a blob of arbitrary HTML as input,
 * and uses RawHTML (which uses dangerouslySetHTML) to render it in the node tree.
 *
 * @param {Object} props {
 *     @type {string} className
 *     @type {string} content
 *     @type {string} link
 *     @type {string} linkText
 * }
 *
 * @return {Element}
 */
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

/**
 * Component for an entity's permalink.
 *
 * @param {Object} props {
 *     @type {string} className
 *     @type {string} link
 *     @type {string} linkText
 * }
 *
 * @return {Element}
 */
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
