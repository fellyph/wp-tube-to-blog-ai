import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const blueprintPath = process.argv[2] || 'playground/blueprint-github-release.json';
const blueprint = JSON.parse( readFileSync( resolve( process.cwd(), blueprintPath ), 'utf8' ) );
const encodedBlueprint = encodeURIComponent( JSON.stringify( blueprint ) );

console.log( `https://playground.wordpress.net/#${ encodedBlueprint }` );
