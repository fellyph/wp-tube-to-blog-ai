import { expect, type Locator } from '@playwright/test';

export const expectPluginLogoLoaded = async ( logo: Locator ) => {
	await expect( logo ).toBeVisible();
	await expect( logo ).toHaveAttribute(
		'src',
		/\/assets\/creatorstack-ai-logo\.png$/
	);
	await expect
		.poll( async () =>
			logo.evaluate(
				( image ) => ( image as HTMLImageElement ).naturalWidth
			)
		)
		.toBeGreaterThan( 0 );
};
