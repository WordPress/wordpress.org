/**
 * External dependencies.
 */
import React, { Component, PropTypes } from 'react';
import { identity } from 'lodash';
import { localize } from 'i18n-calypso';

export class FAQ extends Component {
	static propTypes = {
		content: PropTypes.string,
		translate: PropTypes.func,
	};

	static defaultProps = {
		content: null,
		translate: identity,
	};

	toggleAnswer = ( event ) => {
		const $question = jQuery( event.target );

		if ( ! $question.is( '.open' ) ) {
			$question.siblings( '.open' ).toggleClass( 'open' ).attr( 'aria-expanded', false )
				.next( 'dd' ).slideToggle( 200 );
		}

		$question.toggleClass( 'open' ).attr( 'aria-expanded', ( index, attribute ) => ( 'true' !== attribute ) )
			.next( 'dd' ).slideToggle( 200 );
	};

	render() {
		const { content, translate } = this.props;

		if ( content ) {
			return (
				<div id="faq" className="section plugin-faq">
					<h2>{ translate( 'FAQ' ) }</h2>
					<div onClick={ this.toggleAnswer } dangerouslySetInnerHTML={ { __html: content } } />
				</div>
			);
		}

		return null;
	}
}

export default localize( FAQ );
