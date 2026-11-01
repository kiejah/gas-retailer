/**
 * ESLint flat config: wp-scripts defaults, plus WordPress script packages
 * marked as core modules. WordPress provides them at runtime (externals),
 * so they are deliberately not installed as npm dependencies.
 */
const wpConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...( Array.isArray( wpConfig ) ? wpConfig : [ wpConfig ] ),
	{
		settings: {
			'import/core-modules': [
				'@wordpress/blocks',
				'@wordpress/block-editor',
				'@wordpress/components',
				'@wordpress/i18n',
				'@wordpress/server-side-render',
			],
		},
	},
	{
		ignores: [ '**/build/**', 'vendor/**', 'gas-retailer/wp-admin/**', 'gas-retailer/wp-includes/**' ],
	},
];
