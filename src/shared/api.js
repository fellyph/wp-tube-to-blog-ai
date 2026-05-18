/**
 * API helper functions using @wordpress/api-fetch.
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Extract structured error info from an api-fetch error.
 *
 * @param {Error|Object} err The caught error.
 * @return {{ code: string|null, message: string, category: string|null, configurationUrl: string|null, configurationLabel: string|null }} Parsed error info.
 */
export function parseError( err ) {
	return {
		code: err.code || null,
		message:
			err.message ||
			__( 'An unexpected error occurred.', 'creatorstack-ai' ),
		category: err.data?.error_category || null,
		configurationUrl: err.data?.configuration_url || null,
		configurationLabel: err.data?.configuration_label || null,
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
 * Fetch AI capability and upload limit information.
 *
 * @return {Promise<Object>} Capability data.
 */
export function fetchCapabilities() {
	return apiFetch( {
		path: '/wttba/v1/capabilities',
	} );
}

/**
 * Generate a blog post preview from a video (no draft created).
 *
 * @param {string} videoId          The YouTube video ID.
 * @param {string} language         Target language code.
 * @param {string} persona          Optional writing style persona.
 * @param {string} manualTranscript Optional manually supplied transcript.
 * @return {Promise<Object>} Preview data { title, content, video_id }.
 */
export function previewPost(
	videoId,
	language,
	persona = '',
	manualTranscript = ''
) {
	return apiFetch( {
		path: '/wttba/v1/preview',
		method: 'POST',
		data: {
			video_id: videoId,
			language,
			persona,
			manual_transcript: manualTranscript,
		},
	} );
}

/**
 * Save AI-generated content as a WordPress draft.
 *
 * @param {string} videoId    The YouTube video ID.
 * @param {string} title      The generated post title.
 * @param {string} content    The generated post content (HTML).
 * @param {Object} aiMetadata AI generation metadata.
 * @return {Promise<Object>} Draft info { post_id, edit_url, warnings }.
 */
export function saveDraft( videoId, title, content, aiMetadata = {} ) {
	return apiFetch( {
		path: '/wttba/v1/save-draft',
		method: 'POST',
		data: {
			video_id: videoId,
			title,
			content,
			ai_metadata: aiMetadata,
		},
	} );
}

/**
 * Generate a blog post preview from an audio attachment.
 *
 * @param {number} postId       Current post ID.
 * @param {number} attachmentId Audio attachment ID.
 * @param {string} language     Target language code.
 * @param {string} persona      Optional writing style persona.
 * @return {Promise<Object>} Preview data.
 */
export function previewAudioPost(
	postId,
	attachmentId,
	language,
	persona = ''
) {
	return apiFetch( {
		path: '/wttba/v1/audio-post/preview',
		method: 'POST',
		data: {
			post_id: postId,
			attachment_id: attachmentId,
			language,
			persona,
		},
	} );
}

/**
 * Upload an audio file to the WordPress Media Library.
 *
 * @param {File|Blob} file  Audio file.
 * @param {string}    title Attachment title.
 * @return {Promise<Object>} Media attachment response.
 */
export function uploadAudioAttachment( file, title = '' ) {
	const formData = new window.FormData();
	const filename = file.name || 'wttba-audio-recording.webm';

	formData.append( 'file', file, filename );

	if ( title ) {
		formData.append( 'title', title );
	}

	return apiFetch( {
		path: '/wp/v2/media',
		method: 'POST',
		body: formData,
	} );
}

/**
 * Generate and save a new draft post from an audio attachment.
 *
 * @param {number} attachmentId Audio attachment ID.
 * @param {string} language     Target language code.
 * @param {string} persona      Optional writing style persona.
 * @return {Promise<Object>} Draft response.
 */
export function createAudioDraft( attachmentId, language, persona = '' ) {
	return apiFetch( {
		path: '/wttba/v1/audio-post/draft',
		method: 'POST',
		data: {
			attachment_id: attachmentId,
			language,
			persona,
		},
	} );
}

/**
 * Generate audio from an existing post.
 *
 * @param {number}  postId         Post ID.
 * @param {string}  voice          Optional provider-specific voice.
 * @param {boolean} overwriteBlock Whether to update an existing generated audio block.
 * @return {Promise<Object>} Audio generation response.
 */
export function generatePostAudio( postId, voice = '', overwriteBlock = true ) {
	return apiFetch( {
		path: `/wttba/v1/posts/${ postId }/audio`,
		method: 'POST',
		data: {
			voice,
			overwrite_block: overwriteBlock,
		},
	} );
}

/**
 * Generate a thumbnail preview for an existing post.
 *
 * @param {number}   postId                 Post ID.
 * @param {string}   style                  Primary thumbnail style.
 * @param {string}   secondaryStyle         Optional secondary style.
 * @param {number}   authorAttachmentId     Optional author image attachment ID.
 * @param {number[]} referenceAttachmentIds Optional logo/object image attachment IDs.
 * @return {Promise<Object>} Thumbnail preview response.
 */
export function previewThumbnail(
	postId,
	style,
	secondaryStyle = '',
	authorAttachmentId = 0,
	referenceAttachmentIds = []
) {
	return apiFetch( {
		path: `/wttba/v1/posts/${ postId }/thumbnail/preview`,
		method: 'POST',
		data: {
			style,
			secondary_style: secondaryStyle,
			author_attachment_id: authorAttachmentId,
			reference_attachment_ids: referenceAttachmentIds,
		},
	} );
}

/**
 * Save a generated thumbnail preview and set it as the post featured image.
 *
 * @param {number} postId    Post ID.
 * @param {string} previewId Preview ID returned from previewThumbnail().
 * @return {Promise<Object>} Saved thumbnail response.
 */
export function setGeneratedThumbnail( postId, previewId ) {
	return apiFetch( {
		path: `/wttba/v1/posts/${ postId }/thumbnail`,
		method: 'POST',
		data: {
			preview_id: previewId,
		},
	} );
}
