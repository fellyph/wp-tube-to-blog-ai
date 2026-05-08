import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import {
	mkdtempSync,
	readFileSync,
	rmSync,
	writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { afterEach, describe, test } from 'node:test';
import { fileURLToPath } from 'node:url';

const repoRoot = resolve(
	dirname( fileURLToPath( import.meta.url ) ),
	'../..'
);
const scriptPath = join( repoRoot, 'scripts/sync-version.mjs' );
const tempDirs = [];

function createTestProject() {
	const projectDir = mkdtempSync(
		join( tmpdir(), 'creatorstack-version-test-' )
	);
	tempDirs.push( projectDir );

	writeFileSync(
		join( projectDir, 'package.json' ),
		JSON.stringify(
			{
				name: 'creatorstack-ai',
				version: '1.0.0',
				private: true,
			},
			null,
			'\t'
		) + '\n'
	);

	writeFileSync(
		join( projectDir, 'creatorstack-ai.php' ),
		`<?php
/**
 * Plugin Name:       CreatorStack AI
 * Version:           1.0.0
 */

define( 'WTTBA_VERSION', '1.0.0' );
`
	);

	return projectDir;
}

function readProjectFile( projectDir, file ) {
	return readFileSync( join( projectDir, file ), 'utf8' );
}

function runSyncVersion( projectDir, version ) {
	return spawnSync( process.execPath, [ scriptPath, version ], {
		cwd: projectDir,
		encoding: 'utf8',
	} );
}

function escapeRegExp( value ) {
	return value.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
}

function assertSyncedVersion( projectDir, version ) {
	const packageJson = JSON.parse(
		readProjectFile( projectDir, 'package.json' )
	);
	const pluginFile = readProjectFile( projectDir, 'creatorstack-ai.php' );
	const escapedVersion = escapeRegExp( version );

	assert.equal( packageJson.version, version );
	assert.match(
		pluginFile,
		new RegExp( `\\* Version:\\s+${ escapedVersion }` )
	);
	assert.match(
		pluginFile,
		new RegExp( `define\\( 'WTTBA_VERSION', '${ escapedVersion }' \\);` )
	);
}

afterEach( () => {
	while ( tempDirs.length ) {
		rmSync( tempDirs.pop(), { recursive: true, force: true } );
	}
} );

describe( 'sync-version', () => {
	test( 'syncs package metadata and plugin bootstrap versions', () => {
		const projectDir = createTestProject();
		const result = runSyncVersion( projectDir, '2.3.4' );

		assert.equal( result.status, 0, result.stderr );
		assert.match(
			result.stdout,
			/Synced CreatorStack AI version to 2\.3\.4\./
		);
		assertSyncedVersion( projectDir, '2.3.4' );
	} );

	test( 'accepts v-prefixed prerelease versions', () => {
		const projectDir = createTestProject();
		const result = runSyncVersion( projectDir, 'v2.3.4-beta.1' );

		assert.equal( result.status, 0, result.stderr );
		assertSyncedVersion( projectDir, '2.3.4-beta.1' );
	} );

	test( 'rejects invalid versions without changing files', () => {
		const projectDir = createTestProject();
		const originalPackageJson = readProjectFile( projectDir, 'package.json' );
		const originalPluginFile = readProjectFile(
			projectDir,
			'creatorstack-ai.php'
		);
		const result = runSyncVersion( projectDir, '2.3' );

		assert.notEqual( result.status, 0 );
		assert.match( result.stderr, /Invalid version/ );
		assert.equal(
			readProjectFile( projectDir, 'package.json' ),
			originalPackageJson
		);
		assert.equal(
			readProjectFile( projectDir, 'creatorstack-ai.php' ),
			originalPluginFile
		);
	} );

	test( 'fails without partial writes when plugin markers are missing', () => {
		const projectDir = createTestProject();
		writeFileSync(
			join( projectDir, 'creatorstack-ai.php' ),
			'<?php\n// Missing version markers.\n'
		);
		const originalPackageJson = readProjectFile( projectDir, 'package.json' );
		const originalPluginFile = readProjectFile(
			projectDir,
			'creatorstack-ai.php'
		);
		const result = runSyncVersion( projectDir, '2.3.4' );

		assert.notEqual( result.status, 0 );
		assert.match( result.stderr, /Could not find plugin header version/ );
		assert.equal(
			readProjectFile( projectDir, 'package.json' ),
			originalPackageJson
		);
		assert.equal(
			readProjectFile( projectDir, 'creatorstack-ai.php' ),
			originalPluginFile
		);
	} );
} );
