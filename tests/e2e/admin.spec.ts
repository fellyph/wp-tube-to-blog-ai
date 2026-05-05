import { test, expect } from '@playwright/test';
import { runCLI } from '@wp-playground/cli';
import { startPlayground, stopPlayground } from './fixtures';

let cli: Awaited< ReturnType< typeof runCLI > >;
let serverUrl: string;

test.beforeAll( async () => {
	cli = await startPlayground();
	serverUrl = cli.serverUrl;
}, 180_000 );

test.afterAll( async () => {
	await stopPlayground( cli );
} );

test( 'plugin is listed and active on the Plugins screen', async ( {
	page,
} ) => {
	await page.goto( `${ serverUrl }/wp-admin/plugins.php` );

	const row = page.locator( 'tr[data-slug="wp-tube-to-blog-ai"]' );
	await expect( row ).toBeVisible();
	await expect( row ).toHaveClass( /active/ );
	await expect( row.locator( '.plugin-title strong' ).first() ).toHaveText(
		/WP Tube-to-Blog AI/
	);
} );

test( 'top-level "Tube-to-Blog" menu opens the videos admin page', async ( {
	page,
} ) => {
	await page.goto( `${ serverUrl }/wp-admin/admin.php?page=wttba-videos` );

	await expect(
		page.getByRole( 'heading', { name: 'YouTube Videos' } )
	).toBeVisible();
	await expect( page.locator( '#wttba-admin-videos' ) ).toBeVisible();
} );

test( 'plugin admin tabs switch between content screens', async ( {
	page,
} ) => {
	await page.goto( `${ serverUrl }/wp-admin/admin.php?page=wttba-videos` );

	const nav = page.getByRole( 'navigation', {
		name: 'AI Content Suite sections',
	} );

	await expect(
		nav.getByRole( 'link', { name: 'YouTube Content' } )
	).toHaveAttribute( 'aria-current', 'page' );
	await expect(
		nav.getByRole( 'link', { name: 'Audio to Post' } )
	).toBeVisible();
	await expect( nav.getByRole( 'link', { name: 'Settings' } ) ).toBeVisible();

	await nav.getByRole( 'link', { name: 'Audio to Post' } ).click();
	await expect(
		page.getByRole( 'heading', { name: 'Audio to Post', level: 1 } )
	).toBeVisible();
	await expect(
		nav.getByRole( 'link', { name: 'Audio to Post' } )
	).toHaveAttribute( 'aria-current', 'page' );

	await nav.getByRole( 'link', { name: 'Settings' } ).click();
	await expect(
		page.getByRole( 'heading', {
			name: 'AI Content Suite Settings',
		} )
	).toBeVisible();
	await expect(
		page
			.getByRole( 'navigation', { name: 'AI Content Suite sections' } )
			.getByRole( 'link', { name: 'Settings' } )
	).toHaveAttribute( 'aria-current', 'page' );
} );

test( 'dashboard widget is registered on wp-admin', async ( { page } ) => {
	await page.goto( `${ serverUrl }/wp-admin/index.php` );

	// Widget id is registered via add_meta_box( 'wttba_dashboard_widget', ... ).
	// Accept either underscored or hyphenated variants to avoid coupling
	// tests to an internal implementation detail.
	await expect(
		page
			.locator( '#wttba_dashboard_widget, #wttba-dashboard-widget' )
			.first()
	).toBeVisible();
} );

test( 'settings link is registered under the Settings menu', async ( {
	page,
} ) => {
	await page.goto( `${ serverUrl }/wp-admin/` );

	// The submenu item is in the DOM but hidden until the parent "Settings"
	// menu is hovered, so assert attachment rather than visibility.
	await expect(
		page.locator( '#adminmenu a[href*="page=wttba-settings"]' )
	).toHaveCount( 1 );
} );
