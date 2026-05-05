import { test, expect, type Page } from '@playwright/test';
import { runCLI } from '@wp-playground/cli';
import { startPlayground, stopPlayground } from './fixtures';

let cli: Awaited< ReturnType< typeof runCLI > >;
let serverUrl: string;

type SetupStatus = 'complete' | 'missing';

const setupItem = ( page: Page, step: string ) =>
	page.locator( `.wttba-auth-checklist__item[data-step="${ step }"]` );

const expectSetupItemStatus = async (
	page: Page,
	step: string,
	label: string,
	status: SetupStatus
) => {
	const item = setupItem( page, step );
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
	await expect(
		page.locator( '#wttba_youtube_oauth_redirect_uri' )
	).toHaveValue(
		/\/wp-admin\/admin-post\.php\?action=wttba_youtube_oauth_callback/
	);
	await expect(
		page.getByText( 'YouTube authentication setup' )
	).toBeVisible();
	await expectSetupItemStatus(
		page,
		'api-key',
		'YouTube API key saved',
		'missing'
	);
	await expectSetupItemStatus(
		page,
		'channel-id',
		'YouTube Channel ID saved',
		'missing'
	);
	await expectSetupItemStatus(
		page,
		'oauth-credentials',
		'OAuth client credentials saved',
		'missing'
	);
	await expectSetupItemStatus(
		page,
		'redirect-uri',
		'Authorized redirect URI verified',
		'missing'
	);
	await expectSetupItemStatus(
		page,
		'youtube-account',
		'YouTube account connected',
		'missing'
	);
	await expect(
		page.getByRole( 'button', { name: 'Save OAuth credentials first' } )
	).toBeDisabled();
	await expect(
		page.getByRole( 'heading', { name: 'Content Settings' } )
	).toBeVisible();
	await expect(
		page.getByRole( 'heading', { name: 'AI Usage' } )
	).toBeVisible();
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

test( 'saves YouTube credentials, language, and persona', async ( {
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
	const persona = page.locator( '#wttba_default_persona' );

	await apiKey.fill( 'AIzaTestKey_1234567890' );
	await channelId.fill( 'UCabcdefghijklmnopqrstuv' );
	await oauthClientId.fill( '1234567890-example.apps.googleusercontent.com' );
	await oauthClientSecret.fill( 'GOCSPX-test-secret' );
	await language.selectOption( 'pt-br' );
	await persona.fill( 'Friendly, concise, developer-focused tone.' );

	await page.getByRole( 'button', { name: 'Save Changes' } ).click();

	await expect( page.getByText( 'Settings saved' ) ).toBeVisible();
	await expect( apiKey ).toHaveValue( 'AIzaTestKey_1234567890' );
	await expect( channelId ).toHaveValue( 'UCabcdefghijklmnopqrstuv' );
	await expect( oauthClientId ).toHaveValue(
		'1234567890-example.apps.googleusercontent.com'
	);
	await expect( oauthClientSecret ).toHaveValue( 'GOCSPX-test-secret' );
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
		'complete'
	);
	await expectSetupItemStatus(
		page,
		'channel-id',
		'YouTube Channel ID saved',
		'complete'
	);
	await expectSetupItemStatus(
		page,
		'oauth-credentials',
		'OAuth client credentials saved',
		'complete'
	);
	await expectSetupItemStatus(
		page,
		'redirect-uri',
		'Authorized redirect URI verified',
		'missing'
	);
	await expectSetupItemStatus(
		page,
		'youtube-account',
		'YouTube account connected',
		'missing'
	);
	await expect( language ).toHaveValue( 'pt-br' );
	await expect( persona ).toHaveValue(
		'Friendly, concise, developer-focused tone.'
	);
} );

test( 'language field only exposes whitelisted options', async ( { page } ) => {
	await page.goto(
		`${ serverUrl }/wp-admin/options-general.php?page=wttba-settings`
	);

	const language = page.locator( '#wttba_default_language' );
	const options = await language
		.locator( 'option' )
		.evaluateAll( ( els ) =>
			els.map( ( el ) => ( el as HTMLOptionElement ).value )
		);

	expect( options ).toEqual(
		expect.arrayContaining( [ 'en', 'pt-br', 'ja', 'ar' ] )
	);
	expect( options ).not.toContain( 'xx' );
} );
