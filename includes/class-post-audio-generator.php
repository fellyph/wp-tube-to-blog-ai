<?php
/**
 * Post-to-audio generator.
 *
 * @package CreatorStack_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts WordPress post content to an attached audio file.
 */
class Post_Audio_Generator {

	/**
	 * Maximum narration source length.
	 */
	private const MAX_NARRATION_LENGTH = 15000;

	/**
	 * Generated audio attachment meta key.
	 */
	public const AUDIO_ATTACHMENT_META_KEY = '_wttba_generated_audio_attachment_id';

	/**
	 * Generated audio block marker class.
	 */
	private const AUDIO_BLOCK_CLASS = 'wttba-generated-audio';

	/**
	 * Generate audio for a post and insert/update the audio block.
	 *
	 * @param int    $post_id         Post ID.
	 * @param string $voice           Optional provider-specific voice.
	 * @param bool   $overwrite_block Whether to update an existing generated audio block.
	 * @return array{attachment_id: int, audio_url: string, edit_url: string, audio_block: string, post_content: string, ai_metadata: array<string, mixed>}|\WP_Error
	 */
	public function generate_for_post( int $post_id, string $voice = '', bool $overwrite_block = true ): array|\WP_Error {
		$post = get_post( $post_id );

		if ( ! $post || 'post' !== $post->post_type ) {
			return new \WP_Error(
				'wttba_post_not_found',
				__( 'The post could not be found.', 'creatorstack-ai' ),
				array( 'status' => 404 )
			);
		}

		if ( ! AI_Provider_Status::is_text_to_speech_supported() ) {
			return new \WP_Error(
				'wttba_tts_not_supported',
				__( 'No configured AI provider supports text-to-speech conversion. Configure a compatible provider in Settings > Connectors.', 'creatorstack-ai' ),
				AI_Provider_Status::get_configuration_error_data()
			);
		}

		$narration = $this->get_narration_text( $post );

		if ( '' === $narration ) {
			return new \WP_Error(
				'wttba_empty_post_content',
				__( 'This post does not have enough readable content to generate audio.', 'creatorstack-ai' ),
				array( 'status' => 400 )
			);
		}

		$builder = wp_ai_client_prompt( $narration )
			->using_system_instruction( __( 'Convert the supplied WordPress article into clear, natural narration audio.', 'creatorstack-ai' ) );

		if ( '' !== trim( $voice ) ) {
			$builder = $builder->as_output_speech_voice( sanitize_key( $voice ) );
		}

		$result = $builder->convert_text_to_speech_result();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		try {
			$file = $result->toAudioFile();
		} catch ( \Throwable $throwable ) {
			return new \WP_Error(
				'wttba_audio_generation_failed',
				__( 'The AI provider did not return a usable audio file.', 'creatorstack-ai' ),
				array( 'status' => 502 )
			);
		}

		$attachment_id = $this->save_audio_file( $file, $post );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$audio_url = wp_get_attachment_url( $attachment_id );

		if ( ! $audio_url ) {
			return new \WP_Error(
				'wttba_audio_save_failed',
				__( 'The generated audio attachment could not be loaded.', 'creatorstack-ai' ),
				array( 'status' => 500 )
			);
		}

		$audio_block  = $this->build_audio_block( $attachment_id, $audio_url );
		$post_content = $this->insert_or_update_audio_block( $post->post_content, $audio_block, $overwrite_block );
		$updated      = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $post_content,
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		update_post_meta( $post_id, self::AUDIO_ATTACHMENT_META_KEY, $attachment_id );

		$metadata = Generation_Logger::metadata_from_result( $result, 'post_audio' );
		Generation_Logger::record( $post_id, $metadata );

		return array(
			'attachment_id' => $attachment_id,
			'audio_url'     => $audio_url,
			'edit_url'      => get_edit_post_link( $post_id, 'raw' ),
			'audio_block'   => $audio_block,
			'post_content'  => $post_content,
			'ai_metadata'   => $metadata,
		);
	}

	/**
	 * Prepare post content for narration.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string Narration text.
	 */
	private function get_narration_text( \WP_Post $post ): string {
		$title   = get_the_title( $post );
		$content = do_blocks( $post->post_content );
		$content = strip_shortcodes( $content );
		$content = wp_strip_all_tags( $content, true );
		$content = trim( preg_replace( '/\s+/', ' ', $content ) ?? '' );

		$narration = trim( $title . "\n\n" . $content );

		if ( mb_strlen( $narration ) > self::MAX_NARRATION_LENGTH ) {
			$narration = mb_substr( $narration, 0, self::MAX_NARRATION_LENGTH );
		}

		return $narration;
	}

	/**
	 * Save generated audio to the Media Library.
	 *
	 * @param mixed    $file AI Client file DTO.
	 * @param \WP_Post $post Parent post.
	 * @return int|\WP_Error Attachment ID or error.
	 */
	private function save_audio_file( mixed $file, \WP_Post $post ): int|\WP_Error {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$mime_type = is_object( $file ) && method_exists( $file, 'getMimeType' ) ? (string) $file->getMimeType() : 'audio/mpeg';

		if ( is_object( $file ) && method_exists( $file, 'getBase64Data' ) && $file->getBase64Data() ) {
			return $this->save_inline_audio_file( (string) $file->getBase64Data(), $mime_type, $post );
		}

		if ( is_object( $file ) && method_exists( $file, 'getUrl' ) && $file->getUrl() ) {
			return $this->save_remote_audio_file( (string) $file->getUrl(), $post );
		}

		return new \WP_Error(
			'wttba_audio_save_failed',
			__( 'The generated audio could not be saved because it was returned in an unsupported format.', 'creatorstack-ai' ),
			array( 'status' => 500 )
		);
	}

	/**
	 * Save inline base64 audio.
	 *
	 * @param string   $base64_data Base64 audio data.
	 * @param string   $mime_type   MIME type.
	 * @param \WP_Post $post        Parent post.
	 * @return int|\WP_Error Attachment ID or error.
	 */
	private function save_inline_audio_file( string $base64_data, string $mime_type, \WP_Post $post ): int|\WP_Error {
		$audio_bytes = base64_decode( $base64_data, true );

		if ( false === $audio_bytes ) {
			return new \WP_Error(
				'wttba_audio_save_failed',
				__( 'The generated audio data could not be decoded.', 'creatorstack-ai' ),
				array( 'status' => 500 )
			);
		}

		$extension = $this->get_extension_for_mime_type( $mime_type );
		$filename  = sanitize_file_name( sprintf( '%s-audio.%s', $post->post_name ?: 'post-' . $post->ID, $extension ) );
		$upload    = wp_upload_bits( $filename, null, $audio_bytes );

		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error(
				'wttba_audio_save_failed',
				$upload['error'],
				array( 'status' => 500 )
			);
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime_type,
				'post_title'     => sprintf(
					/* translators: %s: post title. */
					__( 'Audio version of %s', 'creatorstack-ai' ),
					get_the_title( $post )
				),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file'],
			$post->ID,
			true
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

		return (int) $attachment_id;
	}

	/**
	 * Save remote audio.
	 *
	 * @param string   $url  Remote URL.
	 * @param \WP_Post $post Parent post.
	 * @return int|\WP_Error Attachment ID or error.
	 */
	private function save_remote_audio_file( string $url, \WP_Post $post ): int|\WP_Error {
		$tmp = download_url( $url );

		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$file = array(
			'name'     => sanitize_file_name( sprintf( '%s-audio.mp3', $post->post_name ?: 'post-' . $post->ID ) ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file, $post->ID );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return $attachment_id;
		}

		return (int) $attachment_id;
	}

	/**
	 * Build the core audio block markup.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $audio_url     Audio URL.
	 * @return string Audio block markup.
	 */
	private function build_audio_block( int $attachment_id, string $audio_url ): string {
		$attrs = wp_json_encode(
			array(
				'id'        => $attachment_id,
				'className' => self::AUDIO_BLOCK_CLASS,
			)
		);

		return sprintf(
			'<!-- wp:audio %1$s -->
<figure class="wp-block-audio %2$s"><audio controls src="%3$s"></audio></figure>
<!-- /wp:audio -->',
			$attrs,
			esc_attr( self::AUDIO_BLOCK_CLASS ),
			esc_url( $audio_url )
		);
	}

	/**
	 * Insert or update the generated audio block.
	 *
	 * @param string $content         Current post content.
	 * @param string $audio_block     Audio block markup.
	 * @param bool   $overwrite_block Whether to overwrite existing generated block.
	 * @return string Updated post content.
	 */
	private function insert_or_update_audio_block( string $content, string $audio_block, bool $overwrite_block ): string {
		$pattern = '/<!-- wp:audio [\s\S]*?' . preg_quote( self::AUDIO_BLOCK_CLASS, '/' ) . '[\s\S]*?<!-- \/wp:audio -->/';

		if ( preg_match( $pattern, $content ) ) {
			if ( ! $overwrite_block ) {
				return $content;
			}

			return preg_replace( $pattern, $audio_block, $content, 1 ) ?? $content;
		}

		return $audio_block . "\n\n" . $content;
	}

	/**
	 * Resolve an audio file extension from a MIME type.
	 *
	 * @param string $mime_type MIME type.
	 * @return string File extension.
	 */
	private function get_extension_for_mime_type( string $mime_type ): string {
		return match ( $mime_type ) {
			'audio/wav', 'audio/x-wav' => 'wav',
			'audio/ogg', 'application/ogg' => 'ogg',
			'audio/flac', 'audio/x-flac' => 'flac',
			'audio/aac' => 'aac',
			'audio/mp4', 'audio/m4a', 'audio/x-m4a' => 'm4a',
			'audio/webm' => 'webm',
			default => 'mp3',
		};
	}
}
