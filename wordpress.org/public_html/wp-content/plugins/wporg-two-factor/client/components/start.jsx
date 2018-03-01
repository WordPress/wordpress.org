/**
 * External dependencies.
 */
import React from 'react';

export const Start = ( { i18n, onClickStart } ) => (
	<fieldset id="two-factor-start" className="bbp-form two-factor">
		<legend>{ i18n.twoFactorAuthentication }</legend>
		<div>{ i18n.twoFactorLongDescription }</div>
		<div>
			<button
				type="button"
				id="two-factor-start-toggle"
				className="button button-primary"
				onClick={ onClickStart }
			>
				{ i18n.getStarted }
			</button>
		</div>
	</fieldset>
);

export default Start;
