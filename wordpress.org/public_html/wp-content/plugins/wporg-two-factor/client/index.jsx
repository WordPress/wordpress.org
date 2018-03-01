/**
 * External dependencies.
 */
import React, { Component } from 'react';
import { render } from 'react-dom';

/**
 * Internal dependencies.
 */
import Active from 'components/active';
import Start from 'components/start';
import QrCode from 'components/qr-code';
import KeyCode from 'components/key-code';

export class TwoFactor extends Component {

	constructor() {
		super();

		this.state = {
			errorMessage: '',
			isActive: window.twoFactorClient.isActive,
			setup: 'start',
		};
	}

	showQrCode = () => this.setState( { setup: 'qr' } );
	showKeyCode = () => this.setState( { setup: 'key' } );
	returnToStart = () => this.setState( { setup: 'start' } );

	enable = ( { authCode, keyCode } ) => {
		window.wp.ajax.post( 'two-factor-totp-verify-code', {
			_ajax_nonce: window.twoFactorClient.nonce,
			user_id: window.twoFactorClient.userId,
			key: keyCode.value,
			authcode: authCode.value,
		} )
			.done( () => this.setState( { isActive: true, setup: 'start' } ) )
			.fail( ( errorMessage ) => this.setState( { errorMessage } ) );
	};

	disable = () => {
		window.wp.ajax.post( 'two-factor-disable', {
			_ajax_nonce: window.twoFactorClient.nonce,
			user_id: window.twoFactorClient.userId,
		} )
			.done( () => this.setState( { isActive: false, setup: 'start' } ) )
			.fail( ( errorMessage ) => this.setState( { errorMessage } ) );
	};

	render() {
		const { key: keyCode, qrCode } = window.twoFactorClient;

		if ( this.state.isActive ) {
			return <Active
				onClickDisable={ this.disable }
				error={ this.state.errorMessage }
				i18n={ window.twoFactorClient }
			/>;
		}
		if ( 'qr' === this.state.setup ) {
			return <QrCode
				error={ this.state.errorMessage }
				i18n={ window.twoFactorClient }
				keyCode={ keyCode }
				onClickCancel={ this.returnToStart }
				onClickEnable={ this.enable }
				onClickSwitch={ this.showKeyCode }
				qrCode={ qrCode }
			/>;
		}
		if ( 'key' === this.state.setup ) {
			return <KeyCode
				error={ this.state.errorMessage }
				i18n={ window.twoFactorClient }
				keyCode={ keyCode }
				onClickCancel={ this.returnToStart }
				onClickEnable={ this.enable }
				onClickSwitch={ this.showQrCode }
			/>;
		}

		return <Start
			i18n={ window.twoFactorClient }
			onClickStart={ this.showQrCode }
		/>;
	}
}

render(
	<TwoFactor />,
	document.getElementById( 'two-factor-client' )
);
