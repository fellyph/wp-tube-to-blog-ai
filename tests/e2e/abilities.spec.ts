import { test, expect } from '@playwright/test';
import { runCLI } from '@wp-playground/cli';
import { startPlayground, stopPlayground } from './fixtures';

let cli: Awaited< ReturnType< typeof runCLI > >;

async function php( code: string ) {
	const response = await cli.playground.run( {
		code: `<?php require '/wordpress/wp-load.php'; wp_set_current_user( 1 ); ${ code }`,
	} );
	return JSON.parse( response.text );
}

test.beforeAll( async () => {
	cli = await startPlayground();
}, 180_000 );

test.afterAll( async () => {
	await stopPlayground( cli );
} );

test( 'discovers all creator abilities and their category through REST', async () => {
	const result = await php( `
$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
$request->set_param( 'category', 'creatorstack-ai' );
$request->set_param( 'per_page', 100 );
$response = rest_do_request( $request );
$category = rest_do_request( new WP_REST_Request( 'GET', '/wp-abilities/v1/categories/creatorstack-ai' ) );
echo wp_json_encode( array( 'status' => $response->get_status(), 'abilities' => $response->get_data(), 'category' => $category->get_data() ) );
` );
	expect( result.status ).toBe( 200 );
	expect( result.category.slug ).toBe( 'creatorstack-ai' );
	expect( result.abilities ).toHaveLength( 11 );
	for ( const ability of result.abilities ) {
		expect( ability.name ).toMatch( /^creatorstack-ai\// );
		expect( ability.category ).toBe( 'creatorstack-ai' );
		expect( ability.output_schema.type ).toBe( 'object' );
		const readonly = [
			'get-capabilities',
			'list-videos',
			'get-video',
		].some( ( name ) => ability.name === `creatorstack-ai/${ name }` );
		expect( ability.meta.annotations.readonly ).toBe( readonly );
		expect( ability.meta.annotations.idempotent ).toBe( readonly );
	}
} );

test( 'executes read abilities from PHP and REST with matching data', async () => {
	const result = await php( `
$direct = wp_get_ability( 'creatorstack-ai/get-capabilities' )->execute();
$response = rest_do_request( new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/creatorstack-ai/get-capabilities/run' ) );
echo wp_json_encode( array( 'direct' => $direct, 'rest' => $response->get_data(), 'status' => $response->get_status() ) );
` );
	expect( result.status ).toBe( 200 );
	expect( result.rest ).toEqual( result.direct );
	expect( result.direct.features.postToAudio ).toBe( false );
} );

test( 'rejects malformed and unknown input before executing workflows', async () => {
	const result = await php( `
$cases = array(
 'preview-youtube-post' => array( 'video_id' => 'bad/id' ),
 'get-video' => array(),
 'list-videos' => array( 'max_results' => 51 ),
 'create-audio-draft' => array( 'attachment_id' => -1 ),
 'preview-audio-post' => array( 'post_id' => 1, 'attachment_id' => 1, 'language' => 'invalid' ),
 'preview-post-thumbnail' => array( 'id' => 1, 'style' => 'invalid' ),
 'save-youtube-draft' => array( 'video_id' => 'abc123', 'title' => 'Test', 'content' => 'Test', 'status' => 'publish' ),
 'generate-post-audio' => array( 'id' => 1, 'overwrite_block' => 'invalid' ),
 'save-post-thumbnail' => array( 'id' => 1, 'preview_id' => '' ),
);
$result = array();
foreach ( $cases as $name => $input ) {
 $error = wp_get_ability( 'creatorstack-ai/' . $name )->execute( $input );
 $result[ $name ] = is_wp_error( $error ) ? $error->get_error_code() : 'unexpected_success';
}
echo wp_json_encode( $result );
` );
	for ( const code of Object.values( result ) ) {
		expect( code ).toBe( 'ability_invalid_input' );
	}
} );

test( 'anonymous users and subscribers cannot execute creator abilities', async () => {
	const result = await php( `
$subscriber = wp_insert_user( array( 'user_login' => 'ability_subscriber', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$result = array();
foreach ( array( 0, $subscriber ) as $user_id ) {
 wp_set_current_user( $user_id );
 foreach ( wp_get_abilities() as $ability ) {
  if ( str_starts_with( $ability->get_name(), 'creatorstack-ai/' ) ) {
   $result[] = $ability->check_permissions( array( 'id' => 1, 'post_id' => 1, 'attachment_id' => 1 ) );
  }
 }
 $error = wp_get_ability( 'creatorstack-ai/get-capabilities' )->execute();
 $result[] = ! is_wp_error( $error );
}
echo wp_json_encode( $result );
` );
	expect( result ).toHaveLength( 24 );
	for ( const allowed of result ) {
		expect( allowed ).toBe( false );
	}
} );

test( 'checks ownership, upload capability and administrator-only AI tests', async () => {
	const result = await php( `
$author = wp_insert_user( array( 'user_login' => 'ability_author', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
$own = wp_insert_post( array( 'post_title' => 'Own post', 'post_author' => $author, 'post_status' => 'draft' ) );
$other = wp_insert_post( array( 'post_title' => 'Other post', 'post_author' => 1, 'post_status' => 'draft' ) );
wp_set_current_user( $author );
$result = array();
foreach ( array( 'generate-post-audio', 'preview-post-thumbnail', 'save-post-thumbnail' ) as $name ) {
 $ability = wp_get_ability( 'creatorstack-ai/' . $name );
 $result[] = $ability->check_permissions( array( 'id' => $own ) );
 $result[] = $ability->check_permissions( array( 'id' => $other ) );
}
$result[] = wp_get_ability( 'creatorstack-ai/preview-audio-post' )->check_permissions( array( 'post_id' => $own, 'attachment_id' => $other ) );
$result[] = wp_get_ability( 'creatorstack-ai/create-audio-draft' )->check_permissions( array( 'attachment_id' => $other ) );
$result[] = wp_get_ability( 'creatorstack-ai/test-ai-connection' )->check_permissions();
$user = new WP_User( $author );
$user->add_cap( 'upload_files', false );
wp_set_current_user( 0 );
wp_set_current_user( $author );
$result[] = wp_get_ability( 'creatorstack-ai/generate-post-audio' )->check_permissions( array( 'id' => $own ) );
echo wp_json_encode( $result );
` );
	expect( result ).toEqual( [
		true,
		false,
		true,
		false,
		true,
		false,
		false,
		false,
		false,
		false,
	] );
} );

test( 'preserves feature-disabled and expired-preview errors through REST', async () => {
	const result = await php( `
$id = wp_insert_post( array( 'post_title' => 'Ability error test', 'post_status' => 'draft' ) );
$result = array();
foreach ( array( 'generate-post-audio', 'save-post-thumbnail' ) as $name ) {
 $request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/creatorstack-ai/' . $name . '/run' );
 $request->set_header( 'Content-Type', 'application/json' );
 $input = array( 'id' => $id );
 if ( 'save-post-thumbnail' === $name ) { $input['preview_id'] = 'missing-preview'; }
 $request->set_body( wp_json_encode( array( 'input' => $input ) ) );
 $response = rest_do_request( $request );
 $result[] = array( 'status' => $response->get_status(), 'body' => $response->get_data() );
}
echo wp_json_encode( $result );
` );
	expect( result[ 0 ].status ).toBe( 403 );
	expect( result[ 0 ].body.code ).toBe( 'wttba_feature_disabled' );
	expect( result[ 0 ].body.data.error_category ).toBe( 'configuration' );
	expect( result[ 1 ].status ).toBe( 404 );
	expect( result[ 1 ].body.code ).toBe( 'wttba_thumbnail_preview_expired' );
} );

test( 'saves sanitized drafts via PHP and REST without publishing', async () => {
	const result = await php( `
update_option( 'connectors_content_source_youtube_api_key', 'AIza' . str_repeat( 'x', 35 ) );
update_option( 'wttba_youtube_channel_id', 'UC' . str_repeat( 'x', 22 ) );
set_transient( 'wttba_video_' . md5( 'UC' . str_repeat( 'x', 22 ) . 'ability123' ), array( 'id' => 'ability123', 'title' => 'Video', 'thumbnail' => '' ), 60 );
$input = array( 'video_id' => 'ability123', 'title' => '<b>Draft title</b>', 'content' => '<p>Keep this</p><script>alert(1)</script>' );
$direct = wp_get_ability( 'creatorstack-ai/save-youtube-draft' )->execute( $input );
$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/creatorstack-ai/save-youtube-draft/run' );
$request->set_header( 'Content-Type', 'application/json' );
$request->set_body( wp_json_encode( array( 'input' => $input ) ) );
$response = rest_do_request( $request );
$result = array();
foreach ( array( $direct, $response->get_data() ) as $data ) {
 if ( is_wp_error( $data ) || empty( $data['post_id'] ) ) { $result[] = $data; continue; }
 $post = get_post( $data['post_id'] );
 $result[] = array( 'id' => $post->ID, 'title' => $post->post_title, 'status' => $post->post_status, 'content' => $post->post_content, 'source' => get_post_meta( $post->ID, '_wttba_source_video_id', true ) );
}
delete_option( 'connectors_content_source_youtube_api_key' );
delete_option( 'wttba_youtube_channel_id' );
echo wp_json_encode( $result );
` );
	for ( const post of result ) {
		expect( post.status, JSON.stringify( result ) ).toBe( 'draft' );
		expect( post.title ).toBe( 'Draft title' );
		expect( post.source ).toBe( 'ability123' );
		expect( post.content ).toContain( '<p>Keep this</p>' );
		expect( post.content ).not.toContain( '<script>' );
	}
	expect( result[ 0 ].id ).not.toBe( result[ 1 ].id );
} );
