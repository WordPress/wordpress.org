/**
 * Internal dependencies.
 */
import React, { Component, PropTypes } from 'react';
import { identity } from 'lodash';
import { localize } from 'i18n-calypso';

export class SearchForm extends Component {
	static propTypes = {
		onChange: PropTypes.func,
		onSubmit: PropTypes.func,
		search: PropTypes.string,
		translate: PropTypes.func,
	};

	static defaultProps = {
		onChange: () => {},
		onSubmit: () => {},
		search: '',
		translate: identity,
	};

	onChange = () => this.props.onChange( this.refs.search.value );

	render() {
		const { onSubmit, search, translate } = this.props;

		return (
			<form onSubmit={ onSubmit } role="search" method="get" className="search-form">
				<label htmlFor="s" className="screen-reader-text">{ translate( 'Search for:' ) }</label>
				<input
					className="search-field"
					id="s"
					name="s"
					onChange={ this.onChange }
					placeholder="Search plugins"
					ref="search"
					type="search"
					defaultValue={ search }
				/>
				<button className="button button-primary button-search">
					<i className="dashicons dashicons-search" />
					<span className="screen-reader-text">{ translate( 'Search plugins' ) }</span>
				</button>
			</form>
		);
	}
}

export default localize( SearchForm );
