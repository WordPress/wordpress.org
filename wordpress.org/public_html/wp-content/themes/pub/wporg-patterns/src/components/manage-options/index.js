/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { chevronDown } from '@wordpress/icons';
import { DropdownMenu, Icon, MenuItem } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { store as patternStore } from '../../store';

const ManageOptions = ( { patternId, isSmall } ) => {
	const { isDraft, parent } = useSelect(
		( select ) => {
			const _pattern = select( patternStore ).getPattern( patternId );

			return {
				isDraft: _pattern?.status === 'draft',
				parent: _pattern?.parent || 0,
			};
		},
		[ patternId ]
	);
	const { loadPattern } = useDispatch( patternStore );

	const onRevertToDraft = () => {
		apiFetch( {
			path: `/wp/v2/wporg-pattern/${ patternId }/`,
			method: 'PUT',
			data: {
				status: 'draft',
			},
		} ).then( ( response ) => {
			loadPattern( patternId, response );
		} );
	};
	const onDelete = () => {
		// eslint-disable-next-line no-alert
		if ( window.confirm( __( 'Are you sure you want to delete this pattern?', 'wporg-patterns' ) ) ) {
			apiFetch( {
				path: `/wp/v2/wporg-pattern/${ patternId }/`,
				method: 'DELETE',
			} ).then( () => {
				window.location = `${ wporgPatternsUrl.site }/my-patterns/`;
			} );
		}
	};

	const editLink = `${ wporgPatternsUrl.site }/pattern/${ parent ? parent : patternId }/edit/`;

	const toggleContent = isSmall ? (
		<Icon icon={ chevronDown } />
	) : (
		<>
			{ __( 'Options', 'wporg-patterns' ) }
			<Icon icon={ chevronDown } />
		</>
	);
	const toggleClassName = isSmall ? 'pattern-manage-options__button is-small' : 'pattern-manage-options__button';

	return (
		<DropdownMenu
			className="pattern-manage-options"
			icon={ null }
			toggleProps={ {
				className: toggleClassName,
				children: toggleContent,
				variant: 'secondary',
			} }
			popoverProps={ {
				position: 'bottom center',
				className: 'pattern-manage-options__popover',
			} }
		>
			{ () => (
				<>
					<MenuItem href={ editLink }>
						{ parent
							? __( 'Open original in editor', 'wporg-patterns' )
							: __( 'Open in editor', 'wporg-patterns' ) }
					</MenuItem>
					{ ! isDraft && (
						<MenuItem onClick={ onRevertToDraft }>
							{ __( 'Revert to draft', 'wporg-patterns' ) }
						</MenuItem>
					) }
					<MenuItem isDestructive variant="link" onClick={ onDelete }>
						{ __( 'Delete pattern', 'wporg-patterns' ) }
					</MenuItem>
				</>
			) }
		</DropdownMenu>
	);
};

export default ManageOptions;
