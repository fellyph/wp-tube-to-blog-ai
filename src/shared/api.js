/**
 * API helper functions using @wordpress/api-fetch.
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Extract structured error info from an api-fetch error.
 *
 * @param {Error|Object} err The caught error.
 * @return {{ message: string, category: string|null }} Parsed error info.
 */
export function parseError( err ) {
	return {
		message:
			err.message ||
			__( 'An unexpected error occurred.', 'wp-tube-to-blog-ai' ),
		category: err.data?.error_category || null,
	};
}

/**
 * Fetch channel videos.
 *
 * @param {string} pageToken  Pagination token.
 * @param {number} maxResults Number of results.
 * @return {Promise<Object>} Videos response.
 */
export function fetchVideos( pageToken = '', maxResults = 5 ) {
	const params = new URLSearchParams( {
		max_results: maxResults.toString(),
	} );

	if ( pageToken ) {
		params.set( 'page_token', pageToken );
	}

	return apiFetch( {
		path: `/wttba/v1/videos?${ params.toString() }`,
	} );
}

/**
 * Fetch a single video.
 *
 * @param {string} videoId The YouTube video ID.
 * @return {Promise<Object>} Video details.
 */
export function fetchVideo( videoId ) {
	return apiFetch( {
		path: `/wttba/v1/videos/${ videoId }`,
	} );
}

/**
 * Generate a blog post preview from a video (no draft created).
 *
 * @param {string} videoId  The YouTube video ID.
 * @param {string} language Target language code.
 * @param {string} persona  Optional writing style persona.
 * @return {Promise<Object>} Preview data { title, content, video_id }.
 */
export function previewPost( videoId, language, persona = '' ) {
	return apiFetch( {
		path: '/wttba/v1/preview',
		method: 'POST',
		data: {
			video_id: videoId,
			language,
			persona,
		},
	} );
}

/**
 * Save AI-generated content as a WordPress draft.
 *
 * @param {string} videoId The YouTube video ID.
 * @param {string} title   The generated post title.
 * @param {string} content The generated post content (HTML).
 * @return {Promise<Object>} Draft info { post_id, edit_url, warnings }.
 */
export function saveDraft( videoId, title, content ) {
	return apiFetch( {
		path: '/wttba/v1/save-draft',
		method: 'POST',
		data: {
			video_id: videoId,
			title,
			content,
		},
	} );
}
