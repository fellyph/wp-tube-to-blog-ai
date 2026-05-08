import fs from 'node:fs';
import path from 'node:path';
import { runCLI } from '@wp-playground/cli';

type PlaygroundCLI = Awaited< ReturnType< typeof runCLI > >;

// Playwright runs from the project root; resolve the plugin root and the
// blueprint relative to it rather than via import.meta so the file works
// under Playwright's CJS/ESM TS loader.
const pluginRoot = process.cwd();
const blueprint = JSON.parse(
	fs.readFileSync(
		path.join( pluginRoot, 'playground', 'blueprint.json' ),
		'utf8'
	)
);

export async function startPlayground(): Promise< PlaygroundCLI > {
	return runCLI( {
		command: 'server',
		php: blueprint.preferredVersions?.php || '8.3',
		wp: blueprint.preferredVersions?.wp || 'latest',
		mount: [
			{
				hostPath: pluginRoot,
				vfsPath: '/wordpress/wp-content/plugins/creatorstack-ai',
			},
		],
		blueprint,
	} );
}

export async function stopPlayground(
	cli: PlaygroundCLI | undefined
): Promise< void > {
	if ( ! cli ) {
		return;
	}
	await cli[ Symbol.asyncDispose ]();
}
