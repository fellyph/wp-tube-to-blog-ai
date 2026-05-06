/**
 * Settings page interactions.
 */
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { parseError } from '../shared/api';
import './style.scss';

const config = window.wttbaSettingsConfig || {};
const root = document.getElementById( 'wttba-ai-test' );
const wizard = document.getElementById( 'wttba-oauth-setup-wizard' );

if ( wizard ) {
	const redirectUri = wizard.querySelector(
		'#wttba-oauth-wizard-redirect-uri'
	);
	const copyButton = wizard.querySelector( '#wttba-copy-redirect-uri' );
	const copyStatus = wizard.querySelector(
		'#wttba-copy-redirect-uri-status'
	);
	const clientJson = wizard.querySelector( '#wttba-oauth-client-json' );
	const fillButton = wizard.querySelector( '#wttba-fill-oauth-fields' );
	const fillStatus = wizard.querySelector(
		'#wttba-oauth-client-json-result'
	);
	const clientIdInput = document.getElementById(
		'wttba_youtube_oauth_client_id'
	);
	const clientSecretInput = document.getElementById(
		'wttba_youtube_oauth_client_secret'
	);
	const oauthClientIdPattern =
		/^[0-9]+-[0-9A-Za-z_-]+\.apps\.googleusercontent\.com$/;
	const oauthClientSecretPattern = /^[0-9A-Za-z_-]{8,}$/;

	const setStatus = ( element, message, type = '' ) => {
		if ( ! element ) {
			return;
		}

		element.textContent = message;
		element.classList.toggle(
			'wttba-settings-message--success',
			'success' === type
		);
		element.classList.toggle(
			'wttba-settings-message--error',
			'error' === type
		);
		element.classList.toggle(
			'wttba-settings-message--warning',
			'warning' === type
		);
	};

	const writeInputValue = ( input, value ) => {
		if ( ! input ) {
			return;
		}

		input.value = value;
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	};

	const fallbackCopy = ( input ) => {
		input.focus();
		input.select();
		return document.execCommand( 'copy' );
	};

	copyButton?.addEventListener( 'click', async () => {
		const value = redirectUri?.value || config.redirectUri || '';

		if ( ! value ) {
			setStatus(
				copyStatus,
				__(
					'No redirect URI was available to copy.',
					'creatorstack-ai'
				),
				'error'
			);
			return;
		}

		try {
			if ( window.navigator.clipboard?.writeText ) {
				await window.navigator.clipboard.writeText( value );
			} else if ( ! redirectUri || ! fallbackCopy( redirectUri ) ) {
				throw new Error( 'Copy failed' );
			}

			setStatus(
				copyStatus,
				__( 'Redirect URI copied.', 'creatorstack-ai' ),
				'success'
			);
		} catch ( err ) {
			setStatus(
				copyStatus,
				__(
					'Copy failed. Select and copy the redirect URI manually.',
					'creatorstack-ai'
				),
				'error'
			);
		}
	} );

	fillButton?.addEventListener( 'click', () => {
		const rawJson = clientJson?.value?.trim() || '';

		if ( ! rawJson ) {
			setStatus(
				fillStatus,
				__(
					'Paste the client_secret.json contents first.',
					'creatorstack-ai'
				),
				'error'
			);
			return;
		}

		try {
			const parsed = JSON.parse( rawJson );
			const webClient = parsed.web;
			const clientId =
				'string' === typeof webClient?.client_id
					? webClient.client_id.trim()
					: '';
			const clientSecret =
				'string' === typeof webClient?.client_secret
					? webClient.client_secret.trim()
					: '';

			if (
				! oauthClientIdPattern.test( clientId ) ||
				! oauthClientSecretPattern.test( clientSecret )
			) {
				setStatus(
					fillStatus,
					__(
						'This does not look like a valid Web application client_secret.json file.',
						'creatorstack-ai'
					),
					'error'
				);
				return;
			}

			writeInputValue( clientIdInput, clientId );
			writeInputValue( clientSecretInput, clientSecret );

			const redirectUris = Array.isArray( webClient.redirect_uris )
				? webClient.redirect_uris
				: [];
			const currentRedirectUri =
				redirectUri?.value || config.redirectUri || '';
			const hasRedirectUri = redirectUris.includes( currentRedirectUri );
			const successMessage = __(
				'OAuth fields filled. Save changes, then connect YouTube.',
				'creatorstack-ai'
			);
			const warningMessage = __(
				'OAuth fields filled. Make sure the Authorized redirect URI above is also saved in Google Cloud before connecting.',
				'creatorstack-ai'
			);

			setStatus(
				fillStatus,
				hasRedirectUri ? successMessage : warningMessage,
				hasRedirectUri ? 'success' : 'warning'
			);
		} catch ( err ) {
			setStatus(
				fillStatus,
				__(
					'The pasted text is not valid JSON. Download client_secret.json from Google Cloud and paste the full file contents.',
					'creatorstack-ai'
				),
				'error'
			);
		}
	} );
}

if ( root ) {
	const button = root.querySelector( '#wttba-ai-test-button' );
	const spinner = root.querySelector( '#wttba-ai-test-spinner' );
	const result = root.querySelector( '#wttba-ai-test-result' );
	const sample = root.querySelector( '#wttba-ai-test-sample' );

	const setBusy = ( isBusy ) => {
		if ( button ) {
			button.disabled = isBusy;
			button.textContent = isBusy
				? __( 'Testing…', 'creatorstack-ai' )
				: __( 'Test AI Connection', 'creatorstack-ai' );
		}

		if ( spinner ) {
			spinner.classList.toggle( 'is-active', isBusy );
		}
	};

	const clearElement = ( element ) => {
		if ( element ) {
			element.replaceChildren();
		}
	};

	const renderNotice = ( type, message ) => {
		if ( ! result ) {
			return;
		}

		result.className = `notice notice-${ type } inline`;
		clearElement( result );
		result.appendChild( document.createElement( 'p' ) ).textContent =
			message;
	};

	const appendParagraph = ( element, text ) => {
		const paragraph = document.createElement( 'p' );
		paragraph.textContent = text;
		element.appendChild( paragraph );
	};

	const renderSample = ( response ) => {
		if ( ! sample ) {
			return;
		}

		clearElement( sample );

		if ( response.summary ) {
			appendParagraph(
				sample,
				sprintf(
					/* translators: %s: short AI generated test response. */
					__( 'Test generation: %s', 'creatorstack-ai' ),
					response.summary
				)
			);
		}

		const metadata = response.ai_metadata || {};
		const provider = metadata.provider?.name || metadata.provider?.id || '';
		const model = metadata.model?.name || metadata.model?.id || '';

		if ( provider || model ) {
			appendParagraph(
				sample,
				sprintf(
					/* translators: 1: provider name, 2: model name. */
					__( 'Provider: %1$s. Model: %2$s.', 'creatorstack-ai' ),
					provider || __( 'unknown', 'creatorstack-ai' ),
					model || __( 'unknown', 'creatorstack-ai' )
				)
			);
		}

		if ( response.localhost?.message ) {
			appendParagraph( sample, response.localhost.message );
		} else if ( config.localhost?.message ) {
			appendParagraph( sample, config.localhost.message );
		}

		sample.hidden = false;
	};

	const renderError = ( err ) => {
		const parsed = parseError( err );
		renderNotice( 'error', parsed.message );

		if (
			parsed.category !== 'configuration' ||
			! result ||
			! ( parsed.configurationUrl || config.configurationUrl )
		) {
			return;
		}

		const paragraph = document.createElement( 'p' );
		const link = document.createElement( 'a' );
		link.className = 'button button-secondary button-small';
		link.href = parsed.configurationUrl || config.configurationUrl;
		link.textContent =
			parsed.configurationLabel ||
			config.configurationLabel ||
			__( 'Configure AI Provider', 'creatorstack-ai' );
		paragraph.appendChild( link );
		result.appendChild( paragraph );
	};

	button?.addEventListener( 'click', async () => {
		setBusy( true );
		if ( sample ) {
			sample.hidden = true;
			clearElement( sample );
		}
		clearElement( result );
		if ( result ) {
			result.className = '';
		}

		try {
			const response = await apiFetch( {
				path: config.testPath || '/wttba/v1/ai/test',
				method: 'POST',
			} );

			renderNotice(
				'success',
				response.message ||
					__(
						'AI provider connection test succeeded.',
						'creatorstack-ai'
					)
			);
			renderSample( response );
		} catch ( err ) {
			renderError( err );
		} finally {
			setBusy( false );
		}
	} );
}
