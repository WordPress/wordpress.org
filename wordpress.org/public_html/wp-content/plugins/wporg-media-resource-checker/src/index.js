/**
 * External dependencies
 */
import clsx from 'clsx';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { BlockControls } from '@wordpress/block-editor';
import {
	Dropdown,
	ExternalLink,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { caution } from '@wordpress/icons';
import { getAuthority } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { ALLOWED_RESOURCES } from './utils';
import { useHasInvalidSource } from './hooks';
import './style.scss';

/**
 * Block edit component with warning.
 *
 * @param {Object} props Component props.
 * @param {Object} props.BlockEdit The block edit component.
 * @return {Function} The block edit with warning component.
 */
const BlockEditWithWarning = ( { BlockEdit, siteUrl, mediaUrl, ...props } ) => {
	const siteAuthority = getAuthority( siteUrl );
	const allowedDomainList = [
		siteAuthority,
		...ALLOWED_RESOURCES.map( ( resource ) => resource.authority ),
	];

	return (
		<>
			<BlockEdit { ...props } />
			{ props.isSelected && (
				<BlockControls>
					<Dropdown
						contentClassName=""
						renderToggle={ ( { isOpen, onToggle } ) => {
							return (
								<ToolbarGroup>
									<ToolbarButton
										aria-expanded={ isOpen }
										aria-haspopup="true"
										onClick={ onToggle }
										label={ __(
											'Media resource error',
											'wporg'
										) }
										icon={ caution }
										className="wporg-media-resource-checker-toolbar-button"
									/>
								</ToolbarGroup>
							);
						} }
						renderContent={ () => {
							return (
								<div className="wporg-media-resource-checker-popover-content">
									<p>
										{ __(
											'This media resource is from a domain other than the recommended ones.',
											'wporg'
										) }
									</p>
									<p>
										{ mediaUrl && (
											<ExternalLink href={ mediaUrl }>
												{ mediaUrl }
											</ExternalLink>
										) }
									</p>
									<p>
										{ __(
											'Please use a media resource from the following recommended domains:',
											'wporg'
										) }
									</p>
									<ul>
										{ allowedDomainList.map( ( domain ) => (
											<li key={ domain }>{ domain }</li>
										) ) }
									</ul>
								</div>
							);
						} }
					/>
				</BlockControls>
			) }
		</>
	);
};

/**
 * Higher order component to check if the media resource is from a domain
 * other than the recommended ones.
 *
 * @param {Function} BlockEdit The block edit component.
 * @return {Function} The higher order component.
 */
const withMediaResourceChecker = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { name, attributes } = props;

		const { hasInvalidResource, siteUrl, mediaUrl } = useHasInvalidSource(
			name,
			attributes
		);

		return hasInvalidResource ? (
			<BlockEditWithWarning
				BlockEdit={ BlockEdit }
				siteUrl={ siteUrl }
				mediaUrl={ mediaUrl }
				{ ...props }
			/>
		) : (
			<BlockEdit key="edit" { ...props } />
		);
	};
}, 'withMediaResourceChecker' );

/**
 * Higher order component to add className to wrapperProps for blocks
 * with invalid resources.
 *
 * @param {Function} BlockListBlock The block list block component.
 * @return {Function} The higher order component.
 */
const withInvalidResourceClassName = createHigherOrderComponent(
	( BlockListBlock ) => {
		return ( props ) => {
			const { name, attributes, wrapperProps = {} } = props;

			const { hasInvalidResource } = useHasInvalidSource(
				name,
				attributes
			);

			const newWrapperProps = hasInvalidResource
				? {
						...wrapperProps,
						className: clsx(
							wrapperProps.className,
							'wporg-media-resource-checker-has-invalid-resource'
						),
				  }
				: wrapperProps;

			return (
				<BlockListBlock { ...props } wrapperProps={ newWrapperProps } />
			);
		};
	},
	'withInvalidResourceClassName'
);

addFilter(
	'editor.BlockEdit',
	'wporg-media-resource-checker/with-media-resource-checker',
	withMediaResourceChecker
);

addFilter(
	'editor.BlockListBlock',
	'wporg-media-resource-checker/with-invalid-resource-class-name',
	withInvalidResourceClassName
);
