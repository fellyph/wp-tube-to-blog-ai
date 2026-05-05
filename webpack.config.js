const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'dashboard-widget': path.resolve(
			__dirname,
			'src/dashboard-widget/index.js'
		),
		'admin-videos': path.resolve( __dirname, 'src/admin-videos/index.js' ),
		editor: path.resolve( __dirname, 'src/editor/index.js' ),
		settings: path.resolve( __dirname, 'src/settings/index.js' ),
	},
	output: {
		path: path.resolve( __dirname, 'build' ),
		filename: '[name].js',
	},
};
