<?php
/**
 * AI-powered blog post generator.
 *
 * @package CreatorStack_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constructs AI prompts, generates blog posts, and creates WordPress drafts.
 */
class Post_Generator {

	/**
	 * Minimum accepted manual transcript length in characters.
	 */
	private const MIN_MANUAL_TRANSCRIPT_LENGTH = 50;

	/**
	 * Generate a preview of the blog post content without creating a WordPress draft.
	 *
	 * @param string $video_id          The YouTube video ID.
	 * @param string $language          The target language code.
	 * @param string $persona           Optional writing style persona.
	 * @param string $manual_transcript Optional manually supplied transcript.
	 * @return array{title: string, content: string, video_id: string, ai_metadata: array<string, mixed>}|\WP_Error
	 */
	public function preview( ?string $video_id, ?string $language, ?string $persona = '', ?string $manual_transcript = '' ): array|\WP_Error {
		$video_id          = (string) $video_id;
		$language          = (string) $language;
		$persona           = (string) $persona;
		$manual_transcript = trim( (string) $manual_transcript );

		if ( '' === $video_id || ! preg_match( '/^[a-zA-Z0-9_-]+$/', $video_id ) ) {
			return new \WP_Error(
				'wttba_invalid_video_id',
				__( 'A valid YouTube video ID is required.', 'creatorstack-ai' )
			);
		}

		if ( '' !== $manual_transcript && mb_strlen( $manual_transcript ) < self::MIN_MANUAL_TRANSCRIPT_LENGTH ) {
			return new \WP_Error(
				'wttba_manual_transcript_too_short',
				__( 'Paste a manual transcript with at least 50 characters, or leave the manual transcript field empty to fetch captions automatically.', 'creatorstack-ai' )
			);
		}

		if ( ! AI_Provider_Status::is_text_generation_supported() ) {
			return new \WP_Error(
				'wttba_ai_not_supported',
				AI_Provider_Status::get_unavailable_message(),
				AI_Provider_Status::get_configuration_error_data()
			);
		}

		// Rate limiting: prevent concurrent generation per user.
		$user_id  = get_current_user_id();
		$lock_key = 'wttba_generating_' . $user_id;

		if ( get_transient( $lock_key ) ) {
			return new \WP_Error(
				'wttba_rate_limited',
				__( 'A post is already being generated. Please wait for it to complete.', 'creatorstack-ai' )
			);
		}

		set_transient( $lock_key, true, 120 );

		try {
			// Step 1: Fetch video details.
			$youtube = new YouTube_API();
			$video   = $youtube->get_video( $video_id );

			if ( is_wp_error( $video ) ) {
				return $video;
			}

			// Step 2: Use the provided transcript or fetch captions automatically.
			if ( '' !== $manual_transcript ) {
				$transcript  = $manual_transcript;
				$source_type = 'manual_transcript';
			} else {
				$fetcher     = new Transcript_Fetcher();
				$transcript  = $fetcher->fetch( $video_id, $language );
				$source_type = 'youtube_video';
			}

			if ( is_wp_error( $transcript ) ) {
				return $transcript;
			}

			// Step 3: Generate content via AI.
			$generator = new Content_Generator();
			$ai_result = $generator->generate_from_text( $transcript, $language, $video['title'], $persona, $source_type );

			if ( is_wp_error( $ai_result ) ) {
				Generation_Logger::record( null, Generation_Logger::metadata_from_error( $source_type, $ai_result ) );
				return $ai_result;
			}

			return array(
				'title'       => $ai_result['title'],
				'content'     => $ai_result['content'],
				'video_id'    => $video_id,
				'ai_metadata' => $ai_result['ai_metadata'],
			);
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Save AI-generated content as a WordPress draft post.
	 *
	 * @param string $video_id The YouTube video ID.
	 * @param string $title    The generated post title.
	 * @param string $content  The generated post content (HTML).
	 * @param array  $metadata AI generation metadata.
	 * @return array{post_id: int, edit_url: string, warnings: string[]}|\WP_Error
	 */
	public function save_draft( string $video_id, string $title, string $content, array $metadata = array() ): array|\WP_Error {
		$youtube = new YouTube_API();
		$video   = $youtube->get_video( $video_id );

		if ( is_wp_error( $video ) ) {
			return $video;
		}

		$ai_result = array(
			'title'   => $title,
			'content' => $content,
		);

		$draft_result = $this->create_draft( $ai_result, $video_id, $video, $metadata );

		if ( is_wp_error( $draft_result ) ) {
			return $draft_result;
		}

		return array(
			'post_id'  => $draft_result['post_id'],
			'edit_url' => get_edit_post_link( $draft_result['post_id'], 'raw' ),
			'warnings' => $draft_result['warnings'],
		);
	}

	/**
	 * Create a WordPress draft post from the AI output.
	 *
	 * @param array  $ai_result The parsed AI response with title and content.
	 * @param string $video_id   The YouTube video ID.
	 * @param array  $video      The video details from YouTube API.
	 * @param array  $metadata   AI generation metadata.
	 * @return array{post_id: int, warnings: string[]}|\WP_Error
	 */
	private function create_draft( array $ai_result, string $video_id, array $video, array $metadata = array() ): array|\WP_Error {
		// Build the YouTube embed block.
		$embed_block = sprintf(
			'<!-- wp:embed {"url":"https://www.youtube.com/watch?v=%1$s","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://www.youtube.com/watch?v=%1$s
</div></figure>
<!-- /wp:embed -->',
			esc_attr( $video_id )
		);

		$content = $embed_block . "\n\n" . wp_kses_post( $ai_result['content'] );

		$post_data = array(
			'post_title'   => sanitize_text_field( $ai_result['title'] ),
			'post_content' => $content,
			'post_status'  => 'draft',
			'post_type'    => 'post',
			'meta_input'   => array(
				'_wttba_source_video_id' => $video_id,
				'_wttba_source_type'     => 'youtube_video',
			),
		);

		if ( ! empty( $metadata ) ) {
			$post_data['meta_input'][ Generation_Logger::META_KEY ] = Generation_Logger::sanitize_metadata( $metadata );
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! empty( $metadata ) ) {
			Generation_Logger::record( (int) $post_id, $metadata );
		}

		$warnings = array();

		// Set featured image from YouTube thumbnail.
		if ( ! empty( $video['thumbnail'] ) ) {
			$image_error = $this->set_featured_image( $post_id, $video['thumbnail'], $ai_result['title'] );

			if ( null !== $image_error ) {
				$warnings[] = $image_error->get_error_message();
			}
		}

		return array(
			'post_id'  => $post_id,
			'warnings' => $warnings,
		);
	}

	/**
	 * Download and set the featured image from a URL.
	 *
	 * @param int    $post_id   The post ID.
	 * @param string $image_url The image URL.
	 * @param string $title     The image title/alt text.
	 * @return \WP_Error|null Error on failure, null on success.
	 */
	private function set_featured_image( int $post_id, string $image_url, string $title ): ?\WP_Error {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $image_url, $post_id, $title, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			return new \WP_Error(
				'wttba_featured_image_failed',
				__( 'The post was created, but the featured image could not be set from the video thumbnail.', 'creatorstack-ai' )
			);
		}

		set_post_thumbnail( $post_id, $attachment_id );
		return null;
	}
}
