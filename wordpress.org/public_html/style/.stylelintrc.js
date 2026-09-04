module.exports = {
	extends: [ '@wordpress/stylelint-config' ],
	rules: {
		/*
		 * The stylesheets style Trac's and WordPress.org's existing markup, whose
		 * ids and class names are not ours to rename, and they predate these
		 * rules — reordering or merging selectors to satisfy them would change
		 * the cascade, as would converting the em/rem line-heights.
		 */
		'declaration-property-unit-allowed-list': null,
		'no-descending-specificity': null,
		'no-duplicate-selectors': null,
		'selector-class-pattern': null,
		'selector-id-pattern': null,
	},
};
