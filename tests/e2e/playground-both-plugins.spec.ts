import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { runCLI } from '@wp-playground/cli';

type PlaygroundCLI = Awaited< ReturnType< typeof runCLI > >;

const pluginRoot = process.cwd();
const googleProviderRoot = path.resolve(
	pluginRoot,
	'..',
	'ai-provider-for-google'
);
const blueprint = JSON.parse(
	fs.readFileSync(
		path.join( pluginRoot, 'playground', 'blueprint-both-plugins.json' ),
		'utf8'
	)
);

test( 'Playground mounts and activates CreatorStack AI with the Google provider', async () => {
	test.setTimeout( 180_000 );

	expect(
		fs.existsSync( path.join( googleProviderRoot, 'plugin.php' ) )
	).toBe( true );

	let cli: PlaygroundCLI | undefined;

	try {
		cli = await runCLI( {
			command: 'server',
			php: blueprint.preferredVersions?.php || '8.3',
			wp: blueprint.preferredVersions?.wp || 'nightly',
			mount: [
				{
					hostPath: pluginRoot,
					vfsPath: '/wordpress/wp-content/plugins/creatorstack-ai',
				},
				{
					hostPath: googleProviderRoot,
					vfsPath:
						'/wordpress/wp-content/plugins/ai-provider-for-google',
				},
			],
			blueprint,
		} );

		const response = await cli.playground.run( {
			code: `<?php
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$connector = function_exists( 'wp_get_connector' ) ? wp_get_connector( 'youtube' ) : null;

echo wp_json_encode(
	array(
		'youtubeConnectorActive' => is_plugin_active( 'creatorstack-ai/creatorstack-youtube-connector.php' ),
		'creatorstackActive' => is_plugin_active( 'creatorstack-ai/creatorstack-ai.php' ),
		'googleActive'       => is_plugin_active( 'ai-provider-for-google/plugin.php' ),
		'googleFileExists'   => file_exists( WP_PLUGIN_DIR . '/ai-provider-for-google/plugin.php' ),
		'googleLoaded'       => function_exists( '\\\\WordPress\\\\GoogleAiProvider\\\\register_provider' ),
		'youtubeRegistered'  => function_exists( 'wp_is_connector_registered' ) && wp_is_connector_registered( 'youtube' ),
		'youtubePluginFile'  => is_array( $connector ) ? ( $connector['plugin']['file'] ?? '' ) : '',
	)
);
`,
		} );

		expect( JSON.parse( response.text ) ).toEqual( {
			youtubeConnectorActive: true,
			creatorstackActive: true,
			googleActive: true,
			googleFileExists: true,
			googleLoaded: true,
			youtubeRegistered: true,
			youtubePluginFile:
				'creatorstack-ai/creatorstack-youtube-connector.php',
		} );
	} finally {
		await cli?.[ Symbol.asyncDispose ]();
	}
} );
