module.exports = {
	extends: [ '@wordpress/stylelint-config' ],
	rules: {
		'font-family-no-missing-generic-family-keyword': [ true, { ignoreFontFamilies: [ 'dashicons', 'Dashicons' ] } ],
		// Legacy CSS for markup we don't own; satisfying these would rename foreign selectors or alter rendering.
		'declaration-property-unit-allowed-list': null,
		'no-descending-specificity': null,
		'no-duplicate-selectors': null,
		'selector-class-pattern': null,
		'selector-id-pattern': null,
	},
};
