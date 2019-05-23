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
 * Implements post list markup for WordCamp blocks, with options for list and grid layout.
 *
 * Should be used with rest of the components in this folder. Uses attributes `layout` and `columns`.
 */
export class PostList extends Component {
	/**
	 * Render the content.
	 *
	 * @return {Element}
	 */
	render() {
		const { attributes, className, children = [] } = this.props;
		const { align, grid_columns, layout } = attributes;

		const containerClasses = [
			'wordcamp-block',
			'wordcamp-post-list',
			'has-layout-' + layout,
			className,
		];

		if ( 'grid' === layout ) {
			containerClasses.push( 'has-grid-columns-' + Number( grid_columns ) );
		}

		if ( align ) {
			containerClasses.push( 'align' + align );
		}

		return (
			<ul className={ classnames( containerClasses ) }>
				{ ( children ).map( ( childComponent ) =>
					<li
						key={ childComponent.key }
						className={ classnames( 'wordcamp-post-list__post', 'wordcamp-clearfix' ) }
					>
						{ childComponent }
					</li>
				) }
			</ul>
		);
	}
}

/**
 * Additional component exports
 */
export * from './inspector-controls';
export * from './toolbar';
