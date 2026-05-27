import { test, expect, type Page } from '@playwright/test';
import { runCLI } from '@wp-playground/cli';
import { startPlayground, stopPlayground } from './fixtures';

let cli: Awaited< ReturnType< typeof runCLI > >;
let serverUrl: string;

const dismissWelcomeGuide = async ( page: Page ) => {
	const welcomeGuide = page.locator( '.components-modal__frame' ).first();

	if ( ! ( await welcomeGuide.isVisible().catch( () => false ) ) ) {
		return;
	}

	const closeButton = welcomeGuide
		.locator( '.components-modal__header button, button' )
		.first();

	if ( await closeButton.isVisible().catch( () => false ) ) {
		await closeButton.click( { force: true } );
	}
};

test.beforeAll( async () => {
	cli = await startPlayground();
	serverUrl = cli.serverUrl;
}, 180_000 );

test.afterAll( async () => {
	await stopPlayground( cli );
} );

test( 'editor exposes thumbnail generator controls', async ( { page } ) => {
	await page.goto( `${ serverUrl }/wp-admin/post-new.php`, {
		waitUntil: 'domcontentloaded',
	} );
	await expect(
		page.locator( '.interface-interface-skeleton__sidebar' )
	).toBeVisible();
	await dismissWelcomeGuide( page );

	const panelButton = page
		.locator( '.components-panel__body-toggle' )
		.filter( { hasText: 'CreatorStack AI' } )
		.first();
	await expect( panelButton ).toBeVisible();

	if ( 'true' !== ( await panelButton.getAttribute( 'aria-expanded' ) ) ) {
		await panelButton.click();
	}

	const thumbnailSection = page.locator(
		'.wttba-editor-panel__section--thumbnail'
	);
	await expect( thumbnailSection ).toBeVisible();
	await expect(
		thumbnailSection.getByRole( 'heading', { name: 'Thumbnail' } )
	).toBeVisible();
	await expect(
		thumbnailSection.getByText(
			'Configure an AI provider with image generation support.'
		)
	).toBeVisible();
	await expect(
		thumbnailSection.getByLabel( 'Style', { exact: true } )
	).toBeDisabled();
	await expect(
		thumbnailSection.getByLabel( 'Blend style', { exact: true } )
	).toBeDisabled();
	await expect(
		thumbnailSection.getByRole( 'button', { name: 'Select Author Image' } )
	).toBeDisabled();
	await expect(
		thumbnailSection.getByRole( 'button', {
			name: 'Select Logos or Objects',
		} )
	).toBeDisabled();
	await expect(
		thumbnailSection.getByRole( 'button', { name: 'Generate Thumbnail' } )
	).toBeDisabled();
} );
