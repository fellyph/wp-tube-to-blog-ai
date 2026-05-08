import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const rawVersion = process.argv[2];

if ( ! rawVersion ) {
	console.error( 'Usage: npm run version:set -- 1.0.0' );
	process.exit( 1 );
}

const version = rawVersion.replace( /^v/, '' );
const versionPattern = /^\d+\.\d+\.\d+(?:-[0-9A-Za-z]+(?:[.-][0-9A-Za-z]+)*)?$/;

if ( ! versionPattern.test( version ) ) {
	console.error( `Invalid version "${ rawVersion }". Use semantic versions like 1.0.0 or 1.0.0-beta.1.` );
	process.exit( 1 );
}

const root = process.cwd();

function readProjectFile( path ) {
	return readFileSync( resolve( root, path ), 'utf8' );
}

function writeProjectFile( path, contents ) {
	writeFileSync( resolve( root, path ), contents );
}

function replaceOrFail( contents, pattern, replacement, label ) {
	if ( ! pattern.test( contents ) ) {
		throw new Error( `Could not find ${ label }.` );
	}

	return contents.replace( pattern, replacement );
}

const packageJson = JSON.parse( readProjectFile( 'package.json' ) );
packageJson.version = version;
const updatedPackageJson = `${ JSON.stringify( packageJson, null, '\t' ) }\n`;
let pluginFile = readProjectFile( 'creatorstack-ai.php' );
pluginFile = replaceOrFail(
	pluginFile,
	/^ \* Version:\s+.+$/m,
	` * Version:           ${ version }`,
	'plugin header version'
);
pluginFile = replaceOrFail(
	pluginFile,
	/define\(\s*'WTTBA_VERSION'\s*,\s*'[^']+'\s*\);/,
	`define( 'WTTBA_VERSION', '${ version }' );`,
	'WTTBA_VERSION constant'
);

writeProjectFile( 'package.json', updatedPackageJson );
writeProjectFile( 'creatorstack-ai.php', pluginFile );

console.log( `Synced CreatorStack AI version to ${ version }.` );
