import { test, expect, type Page } from '@playwright/test';
import { runCLI } from '@wp-playground/cli';
import { startPlayground, stopPlayground } from './fixtures';

let cli: Awaited< ReturnType< typeof runCLI > >;
let serverUrl: string;

type SetupStatus = 'complete' | 'missing';

const validApiKey = `AIza${ 'A'.repeat( 35 ) }`;
const validChannelId = `UC${ 'B'.repeat( 22 ) }`;
const validOAuthClientId = '1234567890-example.apps.googleusercontent.com';
const validOAuthClientSecret = 'GOCSPX-test-secret-123456';

const setupItem = ( page: Page, step: string ) =>
	page.locator( `.wttba-auth-checklist__item[data-step="${ step }"]` );

const expectSettingsSection = async (
	page: Page,
	modifier: string,
	heading: string
) => {
	const section = page.locator( `.wttba-settings-section--${ modifier }` );

	await expect( section ).toBeVisible();
	await expect(
		section.getByRole( 'heading', { name: heading } )
	).toBeVisible();
};

const expectSetupItemStatus = async (
	page: Page,
	step: string,
	label: string,
	status: SetupStatus,
	description: string
) => {
	const item = setupItem( page, step );
	const descriptionLocator = item.locator(
		'.wttba-auth-checklist__description'
	);
	const expectedStatusText = 'complete' === status ? 'Done' : 'Pending';
	const expectedIconClass =
		'complete' === status ? 'dashicons-yes-alt' : 'dashicons-no-alt';

	await expect( item ).toHaveAttribute( 'data-status', status );
	await expect( item ).toHaveClass(
		new RegExp( `wttba-auth-checklist__item--${ status }` )
	);
	await expect( item ).toContainText( `${ label }: ${ expectedStatusText }` );
	await expect( item.locator( '.wttba-auth-checklist__icon' ) ).toHaveClass(
		new RegExp( expectedIconClass )
	);

	if ( 'complete' === status ) {
		await expect( descriptionLocator ).toHaveCount( 0 );
	} else {
		await expect( descriptionLocator ).toBeVisible();
		await expect( descriptionLocator ).toHaveText( description );
	}
};

const completeYoutubeAuthSetup = async () => {
	await cli.playground.run( {
		code: `<?php
require '/wordpress/wp-load.php';
update_option( 'wttba_youtube_api_key', '${ validApiKey }' );
update_option( 'wttba_youtube_channel_id', '${ validChannelId }' );
update_option( 'wttba_youtube_oauth_client_id', '1234567890-complete.apps.googleusercontent.com' );
update_option( 'wttba_youtube_oauth_client_secret', '${ validOAuthClientSecret }' );
update_option( 'wttba_youtube_oauth_refresh_token', 'complete-refresh-token' );
update_option( 'wttba_youtube_oauth_verified_redirect_uri', WTTBA\\YouTube_OAuth::get_redirect_uri() );
`,
	} );
};

test.beforeAll( async ( {}, testInfo ) => {
	testInfo.setTimeout( 180_000 );
	cli = await startPlayground();
	serverUrl = cli.serverUrl;
} );

test.afterAll( async () => {
	await stopPlayground( cli );
} );

test( 'settings page renders under Settings menu', async ( { page } ) => {
	await page.goto(
		`${ serverUrl }/wp-admin/options-general.php?page=wttba-settings`
	);

	await expect(
		page.getByRole( 'heading', {
			name: 'AI Content Suite Settings',
		} )
	).toBeVisible();
	await expect(
		page.getByRole( 'heading', { name: 'YouTube Integration' } )
	).toBeVisible();
	await expectSettingsSection( page, 'youtube', 'YouTube Integration' );
	await expectSettingsSection( page, 'content', 'Content Settings' );
	await expectSettingsSection( page, 'ai-provider', 'AI Provider' );
	await expectSettingsSection( page, 'usage', 'AI Usage' );
	await expect(
		page.locator( '#wttba_youtube_oauth_redirect_uri' )
	).toHaveValue(
		/\/wp-admin\/admin-post\.php\?action=wttba_youtube_oauth_callback/
	);
	await expect(
		page.getByRole( 'heading', { name: 'YouTube setup wizard' } )
	).toBeVisible();
	await expect(
		page.getByRole( 'link', { name: /Open YouTube Data API v3/ } )
	).toHaveAttribute( 'href', /youtube\.googleapis\.com/ );
	await expect(
		page.getByRole( 'link', { name: /Open Google credentials/ } )
	).toHaveAttribute(
		'href',
		/console\.cloud\.google\.com\/apis\/credentials/
	);
	await expect(
		page.getByRole( 'link', { name: /Find Channel ID help/ } )
	).toHaveAttribute(
		'href',
		/support\.google\.com\/youtube\/answer\/3250431/
	);
	await expect(
		page.getByRole( 'link', { name: /Create OAuth client/ } )
	).toHaveAttribute( 'href', /oauthclient/ );
	await expect(
		page.locator( '#wttba-oauth-wizard-redirect-uri' )
	).toHaveValue(
		/\/wp-admin\/admin-post\.php\?action=wttba_youtube_oauth_callback/
	);
	await expect(
		page.getByRole( 'button', { name: 'Copy URI' } )
	).toBeVisible();
	await expect(
		page.getByRole( 'button', { name: 'Fill OAuth fields' } )
	).toBeVisible();
	await expect(
		page.getByText( 'YouTube authentication setup' )
	).toBeVisible();
	await expectSetupItemStatus(
		page,
		'api-key',
		'YouTube API key saved',
		'missing',
		'Required for listing channel videos and loading video details.'
	);
	await expectSetupItemStatus(
		page,
		'channel-id',
		'YouTube Channel ID saved',
		'missing',
		'Required for browsing videos from the correct channel.'
	);
	await expectSetupItemStatus(
		page,
		'oauth-credentials',
		'OAuth client credentials saved',
		'missing',
		'Required before WordPress can start the Google OAuth flow.'
	);
	await expectSetupItemStatus(
		page,
		'redirect-uri',
		'Authorized redirect URI verified',
		'missing',
		'Verified after Google redirects back to WordPress. If Google reports redirect_uri_mismatch, copy the URI shown below into Google Cloud.'
	);
	await expectSetupItemStatus(
		page,
		'youtube-account',
		'YouTube account connected',
		'missing',
		'Required for official caption downloads from editable videos.'
	);
	await expect(
		page.getByRole( 'button', { name: 'Save OAuth credentials first' } )
	).toBeDisabled();
	await expect(
		page.getByRole( 'heading', { name: 'Content Settings' } )
	).toBeVisible();
	await expect( page.locator( '#wttba_post_length' ) ).toHaveValue(
		'medium'
	);
	await expect(
		page.getByRole( 'heading', { name: 'AI Usage' } )
	).toBeVisible();
	await expect( page.locator( '#wttba_ai_model' ) ).toHaveValue( '' );
	await expect(
		page.getByRole( 'heading', { name: 'Connection Test' } )
	).toBeVisible();
	await expect(
		page.getByRole( 'heading', { name: 'Localhost Compatibility' } )
	).toBeVisible();
} );

test( 'AI connection test reports provider configuration state on localhost', async ( {
	page,
} ) => {
	await page.goto(
		`${ serverUrl }/wp-admin/options-general.php?page=wttba-settings`
	);

	await expect(
		page.getByText( 'Current detected host: 127.0.0.1' )
	).toBeVisible();

	await page.getByRole( 'button', { name: 'Test AI Connection' } ).click();

	await expect( page.getByText( /No AI provider/ ) ).toBeVisible();
	await expect(
		page.getByRole( 'link', { name: 'Configure AI Provider' } )
	).toBeVisible();
} );

test( 'OAuth setup wizard fills credentials from Google client secret JSON', async ( {
	page,
} ) => {
	await page.goto(
		`${ serverUrl }/wp-admin/options-general.php?page=wttba-settings`
	);

	const redirectUri = await page
		.locator( '#wttba-oauth-wizard-redirect-uri' )
		.inputValue();
	const clientJson = {
		web: {
			client_id: '9876543210-wizard.apps.googleusercontent.com',
			client_secret: 'GOCSPX-wizard-secret',
			redirect_uris: [ redirectUri ],
		},
	};

	await page
		.locator( '#wttba-oauth-client-json' )
		.fill( JSON.stringify( clientJson ) );
	await page.getByRole( 'button', { name: 'Fill OAuth fields' } ).click();

	await expect(
		page.locator( '#wttba_youtube_oauth_client_id' )
	).toHaveValue( '9876543210-wizard.apps.googleusercontent.com' );
	await expect(
		page.locator( '#wttba_youtube_oauth_client_secret' )
	).toHaveValue( 'GOCSPX-wizard-secret' );
	await expect(
		page.getByText(
			'OAuth fields filled. Save changes, then connect YouTube.'
		)
	).toBeVisible();
} );

test( 'OAuth setup wizard rejects invalid client secret JSON credentials', async ( {
	page,
} ) => {
	await page.goto(
		`${ serverUrl }/wp-admin/options-general.php?page=wttba-settings`
	);

	const clientJson = {
		web: {
			client_id: 'not-a-google-client',
			client_secret: 'short',
			redirect_uris: [],
		},
	};

	await page
		.locator( '#wttba-oauth-client-json' )
		.fill( JSON.stringify( clientJson ) );
	await page.getByRole( 'button', { name: 'Fill OAuth fields' } ).click();

	await expect(
		page.getByText(
			'This does not look like a valid Web application client_secret.json file.'
		)
	).toBeVisible();
	await expect(
		page.locator( '#wttba_youtube_oauth_client_id' )
	).toHaveValue( '' );
	await expect(
		page.locator( '#wttba_youtube_oauth_client_secret' )
	).toHaveValue( '' );
} );

test( 'keeps invalid YouTube credential formats pending after save', async ( {
	page,
} ) => {
	await page.goto(
		`${ serverUrl }/wp-admin/options-general.php?page=wttba-settings`
	);

	await page.locator( '#wttba_youtube_api_key' ).fill( 'not-a-google-key' );
	await page.locator( '#wttba_youtube_channel_id' ).fill( 'my-channel' );
	await page
		.locator( '#wttba_youtube_oauth_client_id' )
		.fill( 'not-a-google-client' );
	await page.locator( '#wttba_youtube_oauth_client_secret' ).fill( 'short' );

	await page.getByRole( 'button', { name: 'Save Changes' } ).click();

	await expect(
		page.getByText(
			'Enter a valid YouTube Data API key from Google Cloud.'
		)
	).toBeVisible();
	await expect(
		page.getByText(
			'Enter a valid YouTube Channel ID. Channel IDs start with UC followed by 22 characters.'
		)
	).toBeVisible();
	await expect(
		page.getByText(
			'Enter a valid Google OAuth Web application Client ID ending in .apps.googleusercontent.com.'
		)
	).toBeVisible();
	await expect(
		page.getByText(
			'Enter a valid Google OAuth Client Secret from the Web application client.'
		)
	).toBeVisible();
	await expectSetupItemStatus(
		page,
		'api-key',
		'YouTube API key saved',
		'missing',
		'Required for listing channel videos and loading video details.'
	);
	await expectSetupItemStatus(
		page,
		'channel-id',
		'YouTube Channel ID saved',
		'missing',
		'Required for browsing videos from the correct channel.'
	);
	await expectSetupItemStatus(
		page,
		'oauth-credentials',
		'OAuth client credentials saved',
		'missing',
		'Required before WordPress can start the Google OAuth flow.'
	);
	await expect(
		page.getByRole( 'button', { name: 'Save OAuth credentials first' } )
	).toBeDisabled();
} );

test( 'saves YouTube credentials, content defaults, and AI preferences', async ( {
	page,
} ) => {
	await page.goto(
		`${ serverUrl }/wp-admin/options-general.php?page=wttba-settings`
	);

	// WP's Settings API renders <th> labels without a for= association, so
	// target the inputs by their deterministic ids.
	const apiKey = page.locator( '#wttba_youtube_api_key' );
	const channelId = page.locator( '#wttba_youtube_channel_id' );
	const oauthClientId = page.locator( '#wttba_youtube_oauth_client_id' );
	const oauthClientSecret = page.locator(
		'#wttba_youtube_oauth_client_secret'
	);
	const language = page.locator( '#wttba_default_language' );
	const postLength = page.locator( '#wttba_post_length' );
	const aiModel = page.locator( '#wttba_ai_model' );
	const persona = page.locator( '#wttba_default_persona' );

	await apiKey.fill( validApiKey );
	await channelId.fill( validChannelId );
	await oauthClientId.fill( validOAuthClientId );
	await oauthClientSecret.fill( validOAuthClientSecret );
	await language.selectOption( 'pt-br' );
	await postLength.selectOption( 'long' );
	await aiModel.selectOption( 'gemini-3.1-flash-lite-preview' );
	await persona.fill( 'Friendly, concise, developer-focused tone.' );

	await page.getByRole( 'button', { name: 'Save Changes' } ).click();

	await expect( page.getByText( 'Settings saved' ) ).toBeVisible();
	await expect( apiKey ).toHaveValue( validApiKey );
	await expect( channelId ).toHaveValue( validChannelId );
	await expect( oauthClientId ).toHaveValue( validOAuthClientId );
	await expect( oauthClientSecret ).toHaveValue( validOAuthClientSecret );
	await expect( postLength ).toHaveValue( 'long' );
	await expect( aiModel ).toHaveValue( 'gemini-3.1-flash-lite-preview' );
	await expect(
		page.getByText(
			'OAuth credentials are saved. You can connect YouTube.'
		)
	).toBeVisible();
	await expect(
		page.getByRole( 'link', { name: 'Connect YouTube' } )
	).toBeVisible();
	await expectSetupItemStatus(
		page,
		'api-key',
		'YouTube API key saved',
		'complete',
		'Required for listing channel videos and loading video details.'
	);
	await expectSetupItemStatus(
		page,
		'channel-id',
		'YouTube Channel ID saved',
		'complete',
		'Required for browsing videos from the correct channel.'
	);
	await expectSetupItemStatus(
		page,
		'oauth-credentials',
		'OAuth client credentials saved',
		'complete',
		'Required before WordPress can start the Google OAuth flow.'
	);
	await expectSetupItemStatus(
		page,
		'redirect-uri',
		'Authorized redirect URI verified',
		'missing',
		'Verified after Google redirects back to WordPress. If Google reports redirect_uri_mismatch, copy the URI shown below into Google Cloud.'
	);
	await expectSetupItemStatus(
		page,
		'youtube-account',
		'YouTube account connected',
		'missing',
		'Required for official caption downloads from editable videos.'
	);
	await expect( language ).toHaveValue( 'pt-br' );
	await expect( persona ).toHaveValue(
		'Friendly, concise, developer-focused tone.'
	);
} );

test( 'hides authentication checklist after all YouTube auth steps are complete', async ( {
	page,
} ) => {
	await completeYoutubeAuthSetup();

	await page.goto(
		`${ serverUrl }/wp-admin/options-general.php?page=wttba-settings`
	);

	await expect( page.locator( '.wttba-auth-checklist-notice' ) ).toHaveCount(
		0
	);
	await expect( page.locator( '#wttba-oauth-setup-wizard' ) ).toHaveCount(
		0
	);
	await expect(
		page.getByText(
			'YouTube authentication is configured. You can update credentials below when they change.'
		)
	).toBeVisible();
	await expect( setupItem( page, 'api-key' ) ).toHaveCount( 0 );
	await expect( setupItem( page, 'youtube-account' ) ).toHaveCount( 0 );
} );

test( 'settings select fields only expose whitelisted options', async ( {
	page,
} ) => {
	await page.goto(
		`${ serverUrl }/wp-admin/options-general.php?page=wttba-settings`
	);

	const language = page.locator( '#wttba_default_language' );
	const languageOptions = await language
		.locator( 'option' )
		.evaluateAll( ( els ) =>
			els.map( ( el ) => ( el as HTMLOptionElement ).value )
		);
	const postLengthOptions = await page
		.locator( '#wttba_post_length option' )
		.evaluateAll( ( els ) =>
			els.map( ( el ) => ( el as HTMLOptionElement ).value )
		);
	const modelOptions = await page
		.locator( '#wttba_ai_model option' )
		.evaluateAll( ( els ) =>
			els.map( ( el ) => ( el as HTMLOptionElement ).value )
		);

	expect( languageOptions ).toEqual(
		expect.arrayContaining( [ 'en', 'pt-br', 'ja', 'ar' ] )
	);
	expect( languageOptions ).not.toContain( 'xx' );
	expect( postLengthOptions ).toEqual( [ 'short', 'medium', 'long' ] );
	expect( modelOptions ).toEqual(
		expect.arrayContaining( [
			'',
			'claude-sonnet-4-6',
			'gpt-5.4',
			'gemini-3-flash-preview',
			'gemini-3-pro-preview',
			'gemini-3.1-pro-preview',
			'gemini-3.1-flash-lite-preview',
			'gemma-4-31b-it',
			'gpt-4o-mini',
		] )
	);
	expect( modelOptions ).not.toContain( 'unsupported-model' );
} );
