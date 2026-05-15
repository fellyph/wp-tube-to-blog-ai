<?php
/**
 * Shared AI article generation service.
 *
 * @package CreatorStack_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates structured blog post content from text or audio sources.
 */
class Content_Generator {

	/**
	 * Maximum text source length sent to the model.
	 */
	private const MAX_SOURCE_LENGTH = 30000;

	/**
	 * Timeout for AI Client requests in seconds.
	 */
	private const AI_REQUEST_TIMEOUT = 90;

	/**
	 * Maximum uploaded audio size accepted for audio-to-post.
	 */
	public const MAX_AUDIO_BYTES = 26214400; // 25 MB.

	/**
	 * Allowed audio file extensions.
	 *
	 * @var array<int, string>
	 */
	public const ALLOWED_AUDIO_EXTENSIONS = array( 'mp3', 'm4a', 'wav', 'ogg', 'webm', 'flac', 'aac' );

	/**
	 * Preferred text generation models when available.
	 *
	 * @var array<int, string>
	 */
	private const TEXT_MODEL_PREFERENCES = array(
		'claude-sonnet-4-6',
		'gpt-5.4',
		'gemini-3-flash-preview',
		'gemini-3-pro-preview',
		'gemini-2.5-flash',
		'gpt-4o-mini',
	);

	/**
	 * Run a tiny text generation to verify the configured AI provider works.
	 *
	 * @return array{summary: string, ai_metadata: array<string, mixed>}|\WP_Error
	 */
	public function test_text_generation(): array|\WP_Error {
		if ( ! AI_Provider_Status::is_text_generation_supported() ) {
			return new \WP_Error(
				'wttba_ai_not_supported',
				AI_Provider_Status::get_unavailable_message(),
				AI_Provider_Status::get_configuration_error_data()
			);
		}

		$this->add_ai_request_timeout_filter();

		try {
			$builder = wp_ai_client_prompt(
				__( 'Reply with a short confirmation that the WordPress AI content generation test succeeded.', 'creatorstack-ai' )
			)
				->using_system_instruction( __( 'You verify AI provider connectivity for a WordPress plugin. Keep the response under 20 words.', 'creatorstack-ai' ) )
				->using_temperature( 0 )
				->using_max_tokens( 80 )
				->using_model_preference( ...$this->get_text_model_preferences() );

			$result = $builder->generate_text_result();
		} finally {
			$this->remove_ai_request_timeout_filter();
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		try {
			$text = trim( $result->toText() );
		} catch ( \Throwable $throwable ) {
			return new \WP_Error(
				'wttba_ai_parse_error',
				__( 'The AI provider responded, but the test response could not be read.', 'creatorstack-ai' )
			);
		}

		if ( '' === $text ) {
			return new \WP_Error(
				'wttba_ai_parse_error',
				__( 'The AI provider responded with an empty test result.', 'creatorstack-ai' )
			);
		}

		return array(
			'summary'     => sanitize_text_field( $text ),
			'ai_metadata' => Generation_Logger::metadata_from_result( $result, 'ai_connection_test' ),
		);
	}

	/**
	 * Generate post content from source text.
	 *
	 * @param string $source_text  Source text.
	 * @param string $language     Target language code.
	 * @param string $source_title Source title.
	 * @param string $persona      Optional writing persona.
	 * @param string $source_type  Source type identifier.
	 * @return array{title: string, content: string, ai_metadata: array<string, mixed>}|\WP_Error
	 */
	public function generate_from_text(
		string $source_text,
		string $language,
		string $source_title = '',
		string $persona = '',
		string $source_type = 'text'
	): array|\WP_Error {
		if ( ! AI_Provider_Status::is_text_generation_supported() ) {
			return new \WP_Error(
				'wttba_ai_not_supported',
				AI_Provider_Status::get_unavailable_message(),
				AI_Provider_Status::get_configuration_error_data()
			);
		}

		$source_text  = $this->truncate_source_text( $source_text );
		$post_length  = Settings::get_post_length_generation_config();
		$prompt       = $this->build_text_prompt( $source_text, $language, $source_title, $persona, $source_type, $post_length['instruction'] );

		$this->add_ai_request_timeout_filter();

		try {
			$builder = wp_ai_client_prompt( $prompt )
				->using_system_instruction( __( 'You are a professional blog writer creating accurate, SEO-friendly WordPress content.', 'creatorstack-ai' ) )
				->using_temperature( 0.7 )
				->using_max_tokens( $post_length['max_tokens'] )
				->using_model_preference( ...$this->get_text_model_preferences() )
				->as_json_response( $this->get_article_schema() );

			return $this->generate_article_from_builder( $builder, $source_type );
		} finally {
			$this->remove_ai_request_timeout_filter();
		}
	}

	/**
	 * Generate post content from an audio attachment.
	 *
	 * @param int    $attachment_id Audio attachment ID.
	 * @param string $language      Target language code.
	 * @param string $persona       Optional writing persona.
	 * @return array{title: string, content: string, source_attachment_id: int, ai_metadata: array<string, mixed>}|\WP_Error
	 */
	public function generate_from_audio_attachment( int $attachment_id, string $language, string $persona = '' ): array|\WP_Error {
		$audio = $this->validate_audio_attachment( $attachment_id );

		if ( is_wp_error( $audio ) ) {
			return $audio;
		}

		if ( ! AI_Provider_Status::is_audio_input_generation_supported() ) {
			return new \WP_Error(
				'wttba_audio_input_not_supported',
				__( 'No configured AI provider supports generating text from audio input. Configure a compatible provider in Settings > Connectors.', 'creatorstack-ai' ),
				AI_Provider_Status::get_configuration_error_data()
			);
		}

		$language_name = Settings::LANGUAGES[ $language ] ?? 'English';
		$persona       = $this->get_persona( $persona );
		$post_length   = Settings::get_post_length_generation_config();

		$persona_section = '';
		if ( '' !== $persona ) {
			$persona_section = sprintf(
				"\n\n## Writing Style:\n%s",
				$persona
			);
		}

		$prompt = sprintf(
			'Analyze the attached audio and create a ready-to-review WordPress blog post.

## Instructions:
- Write the blog post in %1$s.
- Extract the key ideas from the audio; do not include a raw transcript.
- Create an engaging title.
- Structure the article with valid HTML headings (h2, h3), paragraphs, and lists where useful.
- Write as an original article, not as a summary of an audio recording.
- Preserve factual details from the audio and do not invent dates, names, or claims not present in the source.
- Follow the selected post length: %3$s
%2$s
',
			$language_name,
			$persona_section,
			$post_length['instruction']
		);

		$this->add_ai_request_timeout_filter();

		try {
			$builder = wp_ai_client_prompt()
				->with_text( $prompt )
				->with_file( $audio['path'], $audio['mime_type'] )
				->using_system_instruction( __( 'You are a professional editor turning spoken source material into accurate WordPress articles.', 'creatorstack-ai' ) )
				->using_temperature( 0.5 )
				->using_max_tokens( $post_length['max_tokens'] )
				->using_model_preference( ...$this->get_text_model_preferences() )
				->as_json_response( $this->get_article_schema() );

			$result = $this->generate_article_from_builder( $builder, 'audio_upload' );
		} finally {
			$this->remove_ai_request_timeout_filter();
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['source_attachment_id'] = $attachment_id;

		return $result;
	}

	/**
	 * Validate an audio attachment for AI processing.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{path: string, mime_type: string, size: int}|\WP_Error
	 */
	public function validate_audio_attachment( int $attachment_id ): array|\WP_Error {
		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new \WP_Error(
				'wttba_invalid_audio_attachment',
				__( 'The selected audio attachment could not be found.', 'creatorstack-ai' ),
				array( 'status' => 404 )
			);
		}

		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
			return new \WP_Error(
				'wttba_audio_file_missing',
				__( 'The selected audio file is missing from the server.', 'creatorstack-ai' ),
				array( 'status' => 404 )
			);
		}

		$mime_type = (string) get_post_mime_type( $attachment_id );
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		$is_audio_mime = str_starts_with( $mime_type, 'audio/' )
			|| ( 'webm' === $extension && 'video/webm' === $mime_type );

		if ( ! in_array( $extension, self::ALLOWED_AUDIO_EXTENSIONS, true ) || ! $is_audio_mime ) {
			return new \WP_Error(
				'wttba_invalid_audio_attachment',
				__( 'Please select a supported audio file.', 'creatorstack-ai' ),
				array( 'status' => 400 )
			);
		}

		$size      = filesize( $path );
		$max_bytes = $this->get_max_audio_bytes();

		if ( false === $size || $size > $max_bytes ) {
			return new \WP_Error(
				'wttba_audio_too_large',
				sprintf(
					/* translators: %s: maximum upload size. */
					__( 'The selected audio file is too large. The maximum size is %s.', 'creatorstack-ai' ),
					size_format( $max_bytes )
				),
				array( 'status' => 400 )
			);
		}

		return array(
			'path'      => $path,
			'mime_type' => 'video/webm' === $mime_type && 'webm' === $extension ? 'audio/webm' : $mime_type,
			'size'      => (int) $size,
		);
	}

	/**
	 * Get the configured maximum audio file size.
	 *
	 * @return int Maximum bytes.
	 */
	public function get_max_audio_bytes(): int {
		return min( self::MAX_AUDIO_BYTES, (int) wp_max_upload_size() );
	}

	/**
	 * Generate and parse article JSON from a prompt builder.
	 *
	 * @param object $builder     Prompt builder.
	 * @param string $source_type Source type identifier.
	 * @return array{title: string, content: string, ai_metadata: array<string, mixed>}|\WP_Error
	 */
	private function generate_article_from_builder( object $builder, string $source_type ): array|\WP_Error {
		$result = $builder->generate_text_result();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		try {
			$json = $result->toText();
		} catch ( \Throwable $throwable ) {
			return new \WP_Error(
				'wttba_ai_parse_error',
				__( 'The AI returned an unexpected response format. Please try generating again.', 'creatorstack-ai' )
			);
		}

		$parsed = json_decode( $json, true );

		if ( ! is_array( $parsed ) || empty( $parsed['title'] ) || empty( $parsed['content'] ) ) {
			return new \WP_Error(
				'wttba_ai_parse_error',
				__( 'The AI returned an unexpected response format. Please try generating again.', 'creatorstack-ai' )
			);
		}

		return array(
			'title'       => sanitize_text_field( (string) $parsed['title'] ),
			'content'     => wp_kses_post( (string) $parsed['content'] ),
			'ai_metadata' => Generation_Logger::metadata_from_result( $result, $source_type ),
		);
	}

	/**
	 * Increase AI Client HTTP timeout while this plugin is issuing a generation request.
	 */
	private function add_ai_request_timeout_filter(): void {
		add_filter( 'wp_ai_client_default_request_timeout', array( $this, 'filter_ai_request_timeout' ) );
	}

	/**
	 * Remove the scoped AI Client HTTP timeout filter.
	 */
	private function remove_ai_request_timeout_filter(): void {
		remove_filter( 'wp_ai_client_default_request_timeout', array( $this, 'filter_ai_request_timeout' ) );
	}

	/**
	 * Filter the AI Client request timeout.
	 *
	 * @param int $timeout Current timeout in seconds.
	 * @return int
	 */
	public function filter_ai_request_timeout( int $timeout ): int {
		return max( $timeout, self::AI_REQUEST_TIMEOUT );
	}

	/**
	 * Get model preferences with the saved administrator choice first.
	 *
	 * @return array<int, string>
	 */
	private function get_text_model_preferences(): array {
		$preferred_model = (string) get_option( 'wttba_ai_model', '' );

		if ( in_array( $preferred_model, Settings::AI_MODEL_IDS, true ) && '' !== $preferred_model ) {
			return array_values( array_unique( array_merge( array( $preferred_model ), self::TEXT_MODEL_PREFERENCES ) ) );
		}

		return self::TEXT_MODEL_PREFERENCES;
	}

	/**
	 * Build the text-source article prompt.
	 *
	 * @param string $source_text        Source text.
	 * @param string $language           Language code.
	 * @param string $source_title       Source title.
	 * @param string $persona            Optional persona.
	 * @param string $source_type        Source type.
	 * @param string $length_instruction Post length instruction.
	 * @return string Prompt text.
	 */
	private function build_text_prompt( string $source_text, string $language, string $source_title, string $persona, string $source_type, string $length_instruction ): string {
		$language_name = Settings::LANGUAGES[ $language ] ?? 'English';
		$persona       = $this->get_persona( $persona );

		$persona_section = '';
		if ( '' !== $persona ) {
			$persona_section = sprintf(
				"\n\n## Writing Style:\n%s",
				$persona
			);
		}

		$source_label = 'source material';
		if ( 'youtube_video' === $source_type ) {
			$source_label = 'YouTube video transcript';
		} elseif ( 'manual_transcript' === $source_type ) {
			$source_label = 'manually provided YouTube transcript';
		} elseif ( 'post_content' === $source_type ) {
			$source_label = 'WordPress post content';
		}

		return sprintf(
			'You are a professional blog writer. Analyze the following %1$s and create a well-structured, SEO-friendly WordPress blog post.

## Instructions:
- Write the blog post in %2$s.
- The source title is: "%3$s"
- Create an engaging, descriptive title.
- Structure the content with proper HTML headings (h2, h3), paragraphs, and lists where appropriate.
- Write in a clear, informative tone suitable for a blog audience.
- Do not include references to the source format, transcript, or AI generation process.
- Ensure the content is cohesive and flows naturally.
- Follow the selected post length: %6$s.
%4$s

## Source:
%5$s',
			$source_label,
			$language_name,
			$source_title,
			$persona_section,
			$source_text,
			$length_instruction
		);
	}

	/**
	 * Get the effective writing persona.
	 *
	 * @param string $persona Request persona.
	 * @return string Persona.
	 */
	private function get_persona( string $persona ): string {
		if ( '' === trim( $persona ) ) {
			$persona = (string) get_option( 'wttba_default_persona', '' );
		}

		return sanitize_textarea_field( $persona );
	}

	/**
	 * Truncate long text to stay within model limits.
	 *
	 * @param string $source_text Source text.
	 * @return string Truncated source text.
	 */
	private function truncate_source_text( string $source_text ): string {
		if ( mb_strlen( $source_text ) <= self::MAX_SOURCE_LENGTH ) {
			return $source_text;
		}

		return mb_substr( $source_text, 0, self::MAX_SOURCE_LENGTH ) . "\n\n[Source truncated]";
	}

	/**
	 * Get the structured article JSON schema.
	 *
	 * @return array<string, mixed>
	 */
	private function get_article_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'title'   => array(
					'type'        => 'string',
					'description' => 'The blog post title.',
				),
				'content' => array(
					'type'        => 'string',
					'description' => 'The blog post content in valid HTML format with headings, paragraphs, and lists.',
				),
			),
			'required'   => array( 'title', 'content' ),
		);
	}
}
