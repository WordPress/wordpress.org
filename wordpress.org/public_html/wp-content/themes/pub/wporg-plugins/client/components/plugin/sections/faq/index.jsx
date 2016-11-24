import React from 'react';

export default React.createClass( {
	displayName: 'FAQ',

	toggleAnswer( event ) {
		var $question = jQuery( event.target );

		if ( ! $question.is( '.open' ) ) {
			$question.siblings( '.open' ).toggleClass( 'open' ).attr( 'aria-expanded', false ).next( 'dd' ).slideToggle( 200 );
		}

		$question.toggleClass( 'open' ).attr( 'aria-expanded', function( index, attribute ) {
			return 'true' !== attribute;
		} ).next( 'dd' ).slideToggle( 200 );
	},

	render() {
		if ( ! this.props.content ) {
			return <div />;
		}

		return (
			<div id="faq">
				<h2>FAQ</h2>
				<div onClick={ this.toggleAnswer } dangerouslySetInnerHTML={ { __html: this.props.content } } />
			</div>
		)
	}
} );
