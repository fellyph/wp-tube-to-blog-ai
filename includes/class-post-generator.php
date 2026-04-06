<?php
/**
 * AI-powered blog post generator.
 *
 * @package WP_Tube_To_Blog_AI
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
	 * Generate a blog post draft from a YouTube video.
	 *
	 * @param string $video_id The YouTube video ID.
	 * @param string $language The target language code.
	 * @return array{post_id: int, edit_url: string}|\WP_Error
	 */
	public function generate( string $video_id, string $language, string $persona = '' ): array|\WP_Error {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new \WP_Error(
				'wttba_ai_client_missing',
				__( 'The WordPress AI Client plugin is required to generate posts. Please install and activate it.', 'wp-tube-to-blog-ai' )
			);
		}

		// Rate limiting: prevent concurrent generation per user.
		$user_id  = get_current_user_id();
		$lock_key = 'wttba_generating_' . $user_id;

		if ( get_transient( $lock_key ) ) {
			return new \WP_Error(
				'wttba_rate_limited',
				__( 'A post is already being generated. Please wait for it to complete.', 'wp-tube-to-blog-ai' )
			);
		}

		set_transient( $lock_key, true, 120 );

		// Step 1: Fetch video details.
		$youtube = new YouTube_API();
		$video   = $youtube->get_video( $video_id );

		if ( is_wp_error( $video ) ) {
			delete_transient( $lock_key );
			return $video;
		}

		// Step 2: Fetch transcript.
		$fetcher    = new Transcript_Fetcher();
		$transcript = $fetcher->fetch( $video_id, $language );

		if ( is_wp_error( $transcript ) ) {
			delete_transient( $lock_key );
			return $transcript;
		}

		// Step 3: Generate content via AI.
		$ai_result = $this->call_ai( $transcript, $language, $video['title'], $persona );

		if ( is_wp_error( $ai_result ) ) {
			delete_transient( $lock_key );
			return $ai_result;
		}

		// Step 4: Create the WordPress draft post.
		$post_id = $this->create_draft( $ai_result, $video_id, $video );

		if ( is_wp_error( $post_id ) ) {
			delete_transient( $lock_key );
			return $post_id;
		}

		delete_transient( $lock_key );

		return array(
			'post_id'  => $post_id,
			'edit_url' => get_edit_post_link( $post_id, 'raw' ),
		);
	}

	/**
	 * Construct the prompt and call the AI client.
	 *
	 * @param string $transcript  The video transcript.
	 * @param string $language    Target language code.
	 * @param string $video_title Original video title.
	 * @return array{title: string, content: string}|\WP_Error
	 */
	private function call_ai( string $transcript, string $language, string $video_title, string $persona = '' ): array|\WP_Error {
		$language_name = Settings::LANGUAGES[ $language ] ?? 'English';

		// Fall back to the saved default persona if none provided per-request.
		if ( empty( $persona ) ) {
			$persona = get_option( 'wttba_default_persona', '' );
		}

		$persona_section = '';
		if ( ! empty( $persona ) ) {
			$persona_section = sprintf(
				"\n\n## Writing Style:\n%s",
				$persona
			);
		}

		$prompt = sprintf(
			'You are a professional blog writer. Analyze the following YouTube video transcript and create a well-structured, SEO-friendly blog post.

## Instructions:
- Write the blog post in %1$s.
- The original video title is: "%2$s"
- Create an engaging, descriptive title (do not just copy the video title).
- Structure the content with proper HTML headings (h2, h3), paragraphs, and lists where appropriate.
- Write in a clear, informative tone suitable for a blog audience.
- Do not include any references to "the video" or "the transcript" — write as if this is an original article.
- Ensure the content is cohesive and flows naturally.
%3$s

## Transcript:
%4$s',
			$language_name,
			$video_title,
			$persona_section,
			$transcript
		);

		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'title'   => array(
					'type'        => 'string',
					'description' => 'The blog post title.',
				),
				'content' => array(
					'type'        => 'string',
					'description' => 'The blog post content in HTML format with headings, paragraphs, and lists.',
				),
			),
			'required'   => array( 'title', 'content' ),
		);

		$result = wp_ai_client_prompt( $prompt )
			->using_temperature( 0.7 )
			->using_max_tokens( 8000 )
			->as_json_response( $schema )
			->generate_text();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$parsed = json_decode( $result, true );

		if ( ! is_array( $parsed ) || empty( $parsed['title'] ) || empty( $parsed['content'] ) ) {
			return new \WP_Error(
				'wttba_ai_parse_error',
				__( 'Failed to parse the AI response. Please try again.', 'wp-tube-to-blog-ai' )
			);
		}

		return $parsed;
	}

	/**
	 * Create a WordPress draft post from the AI output.
	 *
	 * @param array  $ai_result The parsed AI response with title and content.
	 * @param string $video_id  The YouTube video ID.
	 * @param array  $video     The video details from YouTube API.
	 * @return int|\WP_Error The post ID or error.
	 */
	private function create_draft( array $ai_result, string $video_id, array $video ): int|\WP_Error {
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
			),
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set featured image from YouTube thumbnail.
		if ( ! empty( $video['thumbnail'] ) ) {
			$this->set_featured_image( $post_id, $video['thumbnail'], $ai_result['title'] );
		}

		return $post_id;
	}

	/**
	 * Download and set the featured image from a URL.
	 *
	 * @param int    $post_id   The post ID.
	 * @param string $image_url The image URL.
	 * @param string $title     The image title/alt text.
	 */
	private function set_featured_image( int $post_id, string $image_url, string $title ): void {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $image_url, $post_id, $title, 'id' );

		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}
}
