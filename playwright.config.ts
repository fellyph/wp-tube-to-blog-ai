import { defineConfig } from '@playwright/test';

export default defineConfig( {
	testDir: './tests/e2e',
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: 1,
	reporter: process.env.CI ? 'github' : 'html',
	timeout: 120_000,
	expect: {
		timeout: 30_000,
	},
	use: {
		screenshot: 'only-on-failure',
		trace: 'on-first-retry',
	},
} );
