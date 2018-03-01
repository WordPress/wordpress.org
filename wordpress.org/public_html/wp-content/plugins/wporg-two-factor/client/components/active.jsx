/**
 * External dependencies.
 */
import React from 'react';

/**
 * Internal dependencies.
 */
import BackupCodes from 'components/backup-codes';

export const Active = ( { error, i18n, onClickDisable } ) => (
	<fieldset id="two-factor-active" className="bbp-form two-factor">
		<legend>{ i18n.twoFactorAuthentication }</legend>
		<div>
			{ error && <div className="bbp-template-notice error">{ error }</div> }
			<label htmlFor="">{ i18n.twoFactor }</label>
			<fieldset className="bbp-form">
				<div>
					<button
						type="button"
						className="button button-secondary two-factor-disable alignright"
						onClick={ onClickDisable }
					>
						{ i18n.disableTwoFactorAuthentication }
					</button>
					<p className="status" dangerouslySetInnerHTML={ { __html: i18n.statusActive } } />
				</div>
				<p>{ i18n.twoFactorDescription }</p>
			</fieldset>
		</div>

		<BackupCodes i18n={ i18n } />
	</fieldset>
);

export default Active;
