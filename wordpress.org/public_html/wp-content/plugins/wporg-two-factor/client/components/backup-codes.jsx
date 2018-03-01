/**
 * External dependencies.
 */
import React, { Component } from 'react';

export class BackupCodes extends Component {

	constructor() {
		super();

		this.state = {
			codes: [],
			downloadLink: '',
			printConfirmed: false,
		};
	}

	fetchBackupCodes = () => {
		window.wp.ajax.post( 'two_factor_backup_codes_generate', {
			nonce: window.twoFactorClient.backupNonce,
			user_id: window.twoFactorClient.userId,
		} )
			.done( ( { codes, i18n } ) => {
				// Build the download link
				let downloadLink = 'data:application/text;charset=utf-8,' + '\n';
				downloadLink += i18n.title.replace( /%s/g, document.domain ) + '\n\n';

				for ( let i = 0; i < codes.length; i++ ) {
					downloadLink += i + 1 + '. ' + codes[ i ] + '\n';
				}

				this.setState( { codes, downloadLink } );
			} );
	};

	printAgreement = () => this.setState( { printConfirmed: this.refs.printAgreement.checked } );

	clear = () => this.setState( {
		codes: [],
		downloadLink: '',
		printConfirmed: false,
	} );

	/**
	 * @todo
	 */
	copyCodes = () => {
		const $temp = $( '<textarea>' );

		$( 'body' ).append( $temp );

		$temp.val( this.state.codes.map( ( code ) => code + "\n" ) ).select();
		document.execCommand( 'copy' );
		$temp.remove();
	};

	printCodes = () => {
		const printer = window.open( '', '_blank' );

		printer.document.writeln( '<ol><li>' + this.state.codes.join( '</li><li>' ) + '</li></ol>' );
		printer.document.close();
		printer.focus();
		printer.print();
		printer.close();
	};

	render() {
		const { i18n } = this.props;

		let fieldsetContent = (
			<div id="two-factor-backup-codes-button">
				<div>
					<button type="button" onClick={ this.fetchBackupCodes } className="button button-secondary">
						{ i18n.generateNewBackupCodes }
					</button>
				</div>
				<p>{ i18n.backupCodesDescription }</p>
			</div>
		);

		if ( this.state.codes.length ) {
			fieldsetContent = (
				<div className="two-factor-backup-codes-wrapper">
					<p className="description">
						{ i18n.askToPrintList }
					</p>
					<ol id="two-factor-backup-codes-list">
						{ this.state.codes.map( ( code ) =>
							<li key={ code }>{ code }</li>
						) }
					</ol>
					<div><small>{ i18n.backupCodesWarning }</small></div>
					<div>
						<input
							type="checkbox"
							ref="printAgreement"
							onChange={ this.printAgreement }
							id="print-agreement"
							name="print-agreement"
						/>
						<label htmlFor="print-agreement">{ i18n.printConfirmation }</label>
						<span className="button-group">
							<button
								type="button"
								onClick={ this.printCodes }
								className="button button-secondary dashicons-before dashicons-index-card"
								id="two-factor-backup-codes-print"
								title={ i18n.printCodes }
							>
								<span className="screen-reader-text">{ i18n.printCodes }</span>
							</button>
							<a
								href={ encodeURI( this.state.downloadLink ) }
								className="button button-secondary dashicons-before dashicons-download"
								id="two-factor-backup-codes-download"
								title={ i18n.downloadCodes }
								download="two-factor-backup-codes.txt"
							>
								<span className="screen-reader-text">{ i18n.downloadCodes }</span>
							</a>
						</span>
						<button
							type="button"
							onClick={ this.clear }
							className="button two-factor-submit button-secondary"
							disabled={ ! this.state.printConfirmed }
						>
							{ i18n.allFinished }
						</button>
					</div>
				</div>
			);
		}
		return (
			<div>
				<label htmlFor="">{ i18n.backupCodes }</label>
				<fieldset className="bbp-form">
					{ fieldsetContent }
				</fieldset>
			</div>
		);
	}
}

export default BackupCodes;
