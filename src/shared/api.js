/**
 * API helper functions using @wordpress/api-fetch.
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Fetch channel videos.
 *
 * @param {string} pageToken Pagination token.
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
 * Generate a blog post from a video.
 *
 * @param {string} videoId  The YouTube video ID.
 * @param {string} language Target language code.
 * @return {Promise<Object>} Generated post info { post_id, edit_url }.
 */
export function generatePost( videoId, language, persona = '' ) {
	return apiFetch( {
		path: '/wttba/v1/generate',
		method: 'POST',
		data: {
			video_id: videoId,
			language,
			persona,
		},
	} );
}
