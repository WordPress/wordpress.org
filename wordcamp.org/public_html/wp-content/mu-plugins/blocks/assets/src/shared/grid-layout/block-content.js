/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { Component } = wp.element;

/**
 * Internal dependencies
 */
import './style.scss';

/**
 * Implements grid / list layout for WordCamp blocks. Should be used with rest of the components in this folder. Uses attribute `layout` and `columnns`.
 */
class GridContentLayout extends Component {
	/**
	 * Render the content.
	 *
	 * @return {Element}
	 */
	render() {
		const { attributes, className, children } = this.props;
		const { grid_columns, layout, align } = attributes;

		const containerClasses = [
			'wordcamp-block',
			'wordcamp-block-post-list',
			'layout-' + layout,
			className,
		];

		if ( 'grid' === layout ) {
			containerClasses.push( 'grid-columns-' + Number( grid_columns ) );
		}

		if ( align ) {
			containerClasses.push( 'align' + align );
		}

		return (
			<ul className={ classnames( containerClasses ) }>
				{
					( children || [] ).map(
						( childComponent ) => {
							return (
								<li
									key={ childComponent.key }
									className={ classnames( 'wordcamp-grid-layout-item', 'wordcamp-block-post-list-item', 'wordcamp-clearfix' ) }
								>
									{ childComponent }
								</li>
							);
						}
					)
				}
			</ul>
		);
	}
}

export default GridContentLayout;
