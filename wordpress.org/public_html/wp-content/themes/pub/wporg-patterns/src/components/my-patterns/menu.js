/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Menu from '../menu';
import { getValueFromPath } from '../../utils';
import { useRoute } from '../../hooks';

export default function () {
	const { path, update: updatePath } = useRoute();
	let view = getValueFromPath( path, 'my-patterns' );
	if ( 'page' === view ) {
		view = 'all';
	}

	// @todo Load from an API to get pattern counts.
	const options = [
		{
			value: `${ wporgPatternsUrl.site }/my-patterns/`,
			slug: 'all',
			label: __( 'All', 'wporg-patterns' ),
		},
		{
			value: `${ wporgPatternsUrl.site }/my-patterns/draft/`,
			slug: 'draft',
			label: __( 'Drafts', 'wporg-patterns' ),
		},
		{
			value: `${ wporgPatternsUrl.site }/my-patterns/pending/`,
			slug: 'pending',
			label: __( 'Pending Review', 'wporg-patterns' ),
		},
	];

	return (
		<Menu
			label={ __( 'Menu', 'wporg-patterns' ) }
			current={ view || 'all' }
			options={ options }
			onClick={ ( event ) => {
				event.preventDefault();
				updatePath( event.target.pathname );
			} }
			isLoading={ false }
		/>
	);
}
