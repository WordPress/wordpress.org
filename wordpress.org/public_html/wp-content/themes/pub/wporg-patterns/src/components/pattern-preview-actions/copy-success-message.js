/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';

const CopySuccessMessage = ( { onClick } ) => (
	<Notice
		className="pattern-actions__notice"
		status="success"
		isDismissible={ false }
		actions={ [
			{
				label: __( 'Learn More', 'wporg-patterns' ),
				onClick: onClick,
				variant: 'secondary',
			},
		] }
	>
		<div>
			<b>{ __( 'Pattern copied!', 'wporg-patterns' ) }</b>
			{ __( ' Now you can paste it into any WordPress post or page.', 'wporg-patterns' ) }
		</div>
	</Notice>
);

export default CopySuccessMessage;
