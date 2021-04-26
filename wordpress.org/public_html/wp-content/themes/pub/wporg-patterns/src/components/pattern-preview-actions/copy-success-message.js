/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Notice } from '@wordpress/components';

const CopySuccessMessage = ( { onClick } ) => (
	<Notice className="pattern-actions__notice" status="success" isDismissible={ false }>
		<div>
			<b>{ __( 'Pattern copied!', 'wporg-patterns' ) }</b>
			{ __( ' Now you can paste it into any WordPress post or page.', 'wporg-patterns' ) }
		</div>
		<Button onClick={ onClick } isSecondary>
			{ __( 'Learn More', 'wporg-patterns' ) }
		</Button>
	</Notice>
);

export default CopySuccessMessage;
