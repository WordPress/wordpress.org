/**
 * External dependencies.
 */
import React, { Component } from 'react';

export class KeyCode extends Component {
	onSubmit = () => this.props.onClickEnable( this.refs );

	render() {
		const { error, i18n, keyCode, onClickSwitch, onClickCancel } = this.props;

		return (
			<fieldset id="two-factor-key-code" className="bbp-form two-factor">
				<legend>{ i18n.twoFactorAuthentication }</legend>
				<div>
					{ error && <div className="bbp-template-notice error">{ error }</div> }
					<p>{ i18n.enterThisTimeCode }</p>
					<p>
						<button type="button" className="button-link" onClick={ onClickSwitch }>
							{ i18n.preferToScanTheCode }
						</button>
					</p>
					<p className="key"><strong>{ keyCode }</strong></p>
					<p>{ i18n.thenEnterTheAuthenticationCode }</p>
					<p>
						<label className="screen-reader-text" htmlFor="two-factor-totp-authcode">
							{ i18n.authenticationCode }
						</label>
						<input type="hidden" name="two-factor-totp-key" ref="keyCode" value={ keyCode } />
						<input
							type="tel"
							name="two-factor-totp-authcode"
							ref="authCode"
							className="input"
							size="20"
							pattern="[0-9]*"
							placeholder={ i18n.placeholder }
						/>
					</p>
					<small
						className="description"
						dangerouslySetInnerHTML={ { __html: i18n.notSureWhatThisScreenMeans } }
					/>
					<button
						type="button"
						className="button button-secondary two-factor-cancel alignleft"
						onClick={ onClickCancel }
					>
						{ i18n.cancel }
					</button>
					<button
						type="button"
						className="button button-primary two-factor-submit alignright"
						onClick={ this.onSubmit }
					>
						{ i18n.enable }
					</button>
				</div>
			</fieldset>
		);
	}
}

export default KeyCode;
