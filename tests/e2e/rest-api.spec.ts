import { test, expect, request as pwRequest } from '@playwright/test';
import { runCLI } from '@wp-playground/cli';
import { startPlayground, stopPlayground } from './fixtures';

let cli: Awaited< ReturnType< typeof runCLI > >;
let serverUrl: string;

type DirectRestResponse = {
	status: number;
	body: unknown;
};

async function directRestRequest(
	method: string,
	path: string,
	data: Record< string, unknown > = {}
): Promise< DirectRestResponse > {
	const payload = Buffer.from(
		JSON.stringify( { method, path, data } )
	).toString( 'base64' );
	const response = await cli.playground.run( {
		code: `<?php
require '/wordpress/wp-load.php';
wp_set_current_user( 1 );
$payload = json_decode( base64_decode( '${ payload }' ), true );
$request = new WP_REST_Request( $payload['method'], $payload['path'] );
foreach ( $payload['data'] as $key => $value ) {
	$request->set_param( $key, $value );
}
$response = rest_do_request( $request );
$server = rest_get_server();
echo wp_json_encode(
	array(
		'status' => $response->get_status(),
		'body'   => $server->response_to_data( $response, false ),
	)
);
`,
	} );

	return JSON.parse( response.text ) as DirectRestResponse;
}

test.beforeAll( async () => {
	cli = await startPlayground();
	serverUrl = cli.serverUrl;
}, 180_000 );

test.afterAll( async () => {
	await stopPlayground( cli );
} );

test( 'wttba/v1 namespace is registered', async () => {
	const ctx = await pwRequest.newContext();
	const res = await ctx.get( `${ serverUrl }/wp-json/wttba/v1` );

	expect( res.ok() ).toBe( true );
	const body = await res.json();
	expect( body.namespace ).toBe( 'wttba/v1' );
	expect( Object.keys( body.routes ) ).toEqual(
		expect.arrayContaining( [
			'/wttba/v1',
			'/wttba/v1/videos',
			'/wttba/v1/capabilities',
			'/wttba/v1/ai/test',
			'/wttba/v1/preview',
			'/wttba/v1/save-draft',
			'/wttba/v1/audio-post/preview',
			'/wttba/v1/audio-post/draft',
			'/wttba/v1/posts/(?P<id>[\\d]+)/audio',
		] )
	);
	await ctx.dispose();
} );

test( 'unauthenticated requests to /capabilities are rejected', async () => {
	const ctx = await pwRequest.newContext();
	const res = await ctx.get( `${ serverUrl }/wp-json/wttba/v1/capabilities` );

	expect( res.status() ).toBe( 401 );
	const body = await res.json();
	expect( body.code ).toBe( 'rest_forbidden' );
	await ctx.dispose();
} );

test( 'unauthenticated requests to /videos are rejected', async () => {
	const ctx = await pwRequest.newContext();
	const res = await ctx.get( `${ serverUrl }/wp-json/wttba/v1/videos` );

	expect( res.status() ).toBe( 401 );
	const body = await res.json();
	expect( body.code ).toBe( 'rest_forbidden' );
	await ctx.dispose();
} );

test( 'authenticated /videos returns configuration error before YouTube is set', async () => {
	const res = await directRestRequest( 'GET', '/wttba/v1/videos' );

	// No API key/channel configured yet → controller surfaces a 4xx/5xx
	// with an error_category payload.
	expect( res.status ).toBeGreaterThanOrEqual( 400 );
	const body = res.body;
	expect( body ).toHaveProperty( 'code' );
	expect( body ).toHaveProperty( 'data.error_category' );
} );

test( '/preview rejects missing video_id with a 400', async () => {
	const res = await directRestRequest( 'POST', '/wttba/v1/preview' );

	expect( res.status ).toBe( 400 );
} );

test( '/preview validates short manual transcripts before generation', async () => {
	const res = await directRestRequest( 'POST', '/wttba/v1/preview', {
		video_id: 'abc123xyz',
		language: 'en',
		manual_transcript: 'Too short.',
	} );

	expect( res.status ).toBe( 400 );
	expect( res.body ).toHaveProperty(
		'code',
		'wttba_manual_transcript_too_short'
	);
	expect( res.body ).toHaveProperty( 'data.error_category', 'validation' );
} );

test( 'authenticated /capabilities returns AI feature flags and audio limits', async () => {
	const res = await directRestRequest( 'GET', '/wttba/v1/capabilities' );

	expect( res.status ).toBe( 200 );
	const body = res.body as Record< string, unknown >;
	const audioUpload = body.audioUpload as {
		allowedExtensions: string[];
		maxBytes: number;
	};
	expect( body ).toHaveProperty( 'textGenerationSupported' );
	expect( body ).toHaveProperty( 'audioInputSupported' );
	expect( body ).toHaveProperty( 'textToSpeechSupported' );
	expect( body ).toHaveProperty( 'features', {
		youtubeToPost: true,
		audioToPost: true,
		postToAudio: false,
	} );
	expect( body.textToSpeechSupported ).toBe( false );
	expect( audioUpload.allowedExtensions ).toEqual(
		expect.arrayContaining( [ 'mp3', 'wav', 'm4a' ] )
	);
	expect( audioUpload.maxBytes ).toBeGreaterThan( 0 );
} );

test( 'authenticated /ai/test returns a structured error when no provider is configured', async () => {
	const res = await directRestRequest( 'POST', '/wttba/v1/ai/test' );

	expect( res.status ).toBeGreaterThanOrEqual( 400 );
	const body = res.body;
	expect( body ).toHaveProperty( 'code' );
	expect( body ).toHaveProperty( 'data.error_category' );
} );

test( 'post-to-audio route is disabled by default with a structured error', async () => {
	const postRes = await directRestRequest( 'POST', '/wp/v2/posts', {
		title: 'Audio route test',
		content: 'This post has enough readable text for an audio test.',
		status: 'draft',
	} );
	expect( postRes.status ).toBe( 201 );
	const post = postRes.body as { id: number };

	const res = await directRestRequest(
		'POST',
		`/wttba/v1/posts/${ post.id }/audio`
	);

	expect( res.status ).toBeGreaterThanOrEqual( 400 );
	const body = res.body;
	expect( body ).toHaveProperty( 'code', 'wttba_feature_disabled' );
	expect( body ).toHaveProperty( 'data.error_category', 'configuration' );
	expect( body ).toHaveProperty(
		'data.configuration_label',
		'Update settings'
	);
} );

test( 'audio draft route returns a structured error when no audio provider is configured', async () => {
	const response = await cli.playground.run( {
		code: `<?php
require '/wordpress/wp-load.php';
wp_set_current_user( 1 );
$upload = wp_upload_bits( 'audio-draft-test.wav', null, base64_decode( 'UklGRiQAAABXQVZFZm10IBAAAAABAAEAIlYAAESsAAACABAAZGF0YQAAAAA=' ) );
if ( ! empty( $upload['error'] ) ) {
	echo wp_json_encode( array( 'error' => $upload['error'] ) );
	return;
}
$attachment_id = wp_insert_attachment(
	array(
		'post_title'     => 'Audio draft test',
		'post_mime_type' => 'audio/wav',
		'post_status'    => 'inherit',
		'guid'           => $upload['url'],
	),
	$upload['file']
);
echo wp_json_encode( array( 'attachment_id' => $attachment_id ) );
`,
	} );
	const attachment = JSON.parse( response.text ) as {
		attachment_id: number;
	};

	const res = await directRestRequest( 'POST', '/wttba/v1/audio-post/draft', {
		attachment_id: attachment.attachment_id,
		language: 'en',
	} );

	expect( res.status ).toBeGreaterThanOrEqual( 400 );
	const body = res.body;
	expect( body ).toHaveProperty( 'code' );
	expect( body ).toHaveProperty( 'data.error_category' );
} );
