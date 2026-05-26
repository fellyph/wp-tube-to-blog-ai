<?php
/**
 * AI-powered thumbnail generator.
 *
 * @package CreatorStack_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates post thumbnails and saves approved previews as featured images.
 */
class Thumbnail_Generator {

	/**
	 * Generated thumbnail attachment meta key.
	 */
	public const THUMBNAIL_ATTACHMENT_META_KEY = '_wttba_generated_thumbnail_attachment_id';

	/**
	 * Maximum number of non-author reference images accepted.
	 */
	public const MAX_REFERENCE_IMAGES = 2;

	/**
	 * Maximum source text length used in the image prompt.
	 */
	private const MAX_SOURCE_LENGTH = 5000;

	/**
	 * Maximum reference image size.
	 */
	private const MAX_IMAGE_BYTES = 10485760; // 10 MB.

	/**
	 * Preview transient TTL in seconds.
	 */
	private const PREVIEW_TTL = 20 * MINUTE_IN_SECONDS;

	/**
	 * Timeout for image generation requests.
	 */
	private const AI_REQUEST_TIMEOUT = 120;

	/**
	 * Supported image extensions for reference uploads.
	 *
	 * @var array<int, string>
	 */
	public const ALLOWED_IMAGE_EXTENSIONS = array( 'jpg', 'jpeg', 'png', 'webp' );

	/**
	 * Preferred image generation models when available.
	 *
	 * @var array<int, string>
	 */
	private const IMAGE_MODEL_PREFERENCES = array(
		'gpt-image-1',
		'gemini-3-pro-image-preview',
		'gemini-2.5-flash-image',
	);

	/**
	 * Get public style preset metadata.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function get_public_style_presets(): array {
		$presets = array();

		foreach ( self::get_style_presets() as $key => $preset ) {
			$presets[ $key ] = array(
				'label'       => $preset['label'],
				'description' => $preset['description'],
			);
		}

		return $presets;
	}

	/**
	 * Get style presets with prompt instructions.
	 *
	 * @return array<string, array{label: string, description: string, instruction: string}>
	 */
	public static function get_style_presets(): array {
		return array(
			'bold_youtube'    => array(
				'label'       => __( 'Bold YouTube', 'creatorstack-ai' ),
				'description' => __( 'High-contrast, creator-first thumbnail energy.', 'creatorstack-ai' ),
				'instruction' => __( 'Create a high-contrast creator thumbnail with a clear central subject, dramatic lighting, bold composition, and generous negative space for platform overlays.', 'creatorstack-ai' ),
			),
			'editorial'       => array(
				'label'       => __( 'Editorial Portrait', 'creatorstack-ai' ),
				'description' => __( 'Polished publication-style image with a human focus.', 'creatorstack-ai' ),
				'instruction' => __( 'Create a refined editorial image with magazine-quality composition, natural skin tones, restrained contrast, and a polished professional finish.', 'creatorstack-ai' ),
			),
			'product_spotlight' => array(
				'label'       => __( 'Product Spotlight', 'creatorstack-ai' ),
				'description' => __( 'Clean hero treatment for objects, products, and tools.', 'creatorstack-ai' ),
				'instruction' => __( 'Create a product-focused hero thumbnail with the object or logo treated as the main visual anchor, clear separation from the background, and brand-safe spacing.', 'creatorstack-ai' ),
			),
			'cinematic_tech'  => array(
				'label'       => __( 'Cinematic Tech', 'creatorstack-ai' ),
				'description' => __( 'Modern depth, lighting, and technology cues.', 'creatorstack-ai' ),
				'instruction' => __( 'Create a cinematic technology thumbnail with dimensional lighting, controlled depth, contemporary materials, and a focused visual story.', 'creatorstack-ai' ),
			),
			'minimal_branded' => array(
				'label'       => __( 'Minimal Branded', 'creatorstack-ai' ),
				'description' => __( 'Simple, premium, and easy to scan.', 'creatorstack-ai' ),
				'instruction' => __( 'Create a minimal branded thumbnail with a clean layout, crisp hierarchy, limited visual noise, and strong recognition at small sizes.', 'creatorstack-ai' ),
			),
		);
	}

	/**
	 * Get the configured maximum image file size.
	 */
	public function get_max_image_bytes(): int {
		return min( self::MAX_IMAGE_BYTES, (int) wp_max_upload_size() );
	}

	/**
	 * Generate a thumbnail preview for a post.
	 *
	 * @param int    $post_id                  Post ID.
	 * @param string $style                    Primary style key.
	 * @param string $secondary_style          Optional secondary style key.
	 * @param int    $author_attachment_id     Optional author reference image attachment.
	 * @param array  $reference_attachment_ids Optional logo/object reference image attachments.
	 * @return array<string, mixed>|\WP_Error Preview payload or error.
	 */
	public function preview_for_post(
		int $post_id,
		string $style,
		string $secondary_style = '',
		int $author_attachment_id = 0,
		array $reference_attachment_ids = array()
	): array|\WP_Error {
		$post = $this->get_editable_post( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$style_presets = self::get_style_presets();

		if ( ! isset( $style_presets[ $style ] ) ) {
			return new \WP_Error(
				'wttba_invalid_thumbnail_style',
				__( 'Choose a supported thumbnail style.', 'creatorstack-ai' ),
				array( 'status' => 400 )
			);
		}

		if ( '' !== $secondary_style && ! isset( $style_presets[ $secondary_style ] ) ) {
			return new \WP_Error(
				'wttba_invalid_thumbnail_style',
				__( 'Choose a supported secondary thumbnail style.', 'creatorstack-ai' ),
				array( 'status' => 400 )
			);
		}

		if ( ! AI_Provider_Status::is_image_generation_supported() ) {
			return new \WP_Error(
				'wttba_image_generation_not_supported',
				__( 'No configured AI provider supports image generation. Configure a compatible provider in Settings > Connectors.', 'creatorstack-ai' ),
				AI_Provider_Status::get_configuration_error_data()
			);
		}

		$reference_attachment_ids = array_slice(
			array_values( array_unique( array_filter( array_map( 'absint', $reference_attachment_ids ) ) ) ),
			0,
			self::MAX_REFERENCE_IMAGES
		);

		$has_references = $author_attachment_id > 0 || ! empty( $reference_attachment_ids );

		if ( $has_references && ! AI_Provider_Status::is_image_reference_generation_supported() ) {
			return new \WP_Error(
				'wttba_image_reference_not_supported',
				__( 'The configured AI provider can generate images, but it does not support image references for thumbnails.', 'creatorstack-ai' ),
				AI_Provider_Status::get_configuration_error_data()
			);
		}

		$author_reference = null;
		if ( $author_attachment_id > 0 ) {
			$author_reference = $this->validate_image_attachment( $author_attachment_id );

			if ( is_wp_error( $author_reference ) ) {
				return $author_reference;
			}
		}

		$references = array();
		foreach ( $reference_attachment_ids as $attachment_id ) {
			$reference = $this->validate_image_attachment( $attachment_id );

			if ( is_wp_error( $reference ) ) {
				return $reference;
			}

			$references[] = $reference;
		}

		$user_id  = get_current_user_id();
		$lock_key = 'wttba_thumb_generating_' . $user_id;

		if ( get_transient( $lock_key ) ) {
			return new \WP_Error(
				'wttba_rate_limited',
				__( 'A thumbnail is already being generated. Please wait for it to complete.', 'creatorstack-ai' )
			);
		}

		set_transient( $lock_key, true, 120 );

		try {
			$prompt = $this->build_prompt( $post, $style_presets[ $style ], '' === $secondary_style ? null : $style_presets[ $secondary_style ], null !== $author_reference, $references );
			$result = $this->generate_image_result( $prompt, $author_reference, $references );

			if ( is_wp_error( $result ) ) {
				Generation_Logger::record( $post_id, Generation_Logger::metadata_from_error( 'post_thumbnail', $result ) );
				return $result;
			}

			$file_payload = $this->extract_image_file_payload( $result );

			if ( is_wp_error( $file_payload ) ) {
				Generation_Logger::record( $post_id, Generation_Logger::metadata_from_error( 'post_thumbnail', $file_payload ) );
				return $file_payload;
			}

			$preview_id = wp_generate_uuid4();
			$preview_id = str_replace( '-', '', $preview_id );

			$metadata = Generation_Logger::metadata_from_result( $result, 'post_thumbnail' );
			$payload  = array_merge(
				$file_payload,
				array(
					'post_id'     => $post_id,
					'post_title'  => get_the_title( $post ),
					'ai_metadata' => $metadata,
				)
			);

			set_transient( $this->get_preview_transient_key( $post_id, $preview_id ), $payload, self::PREVIEW_TTL );

			return array_filter(
				array(
					'preview_id'     => $preview_id,
					'image_data_uri' => $file_payload['image_data_uri'] ?? '',
					'image_url'      => $file_payload['image_url'] ?? '',
					'mime_type'      => $file_payload['mime_type'],
					'ai_metadata'    => $metadata,
				)
			);
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Save a generated preview and set it as the featured image.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $preview_id Preview token.
	 * @return array<string, mixed>|\WP_Error Save payload or error.
	 */
	public function save_preview_for_post( int $post_id, string $preview_id ): array|\WP_Error {
		$post = $this->get_editable_post( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$preview_id = sanitize_key( $preview_id );
		$payload    = get_transient( $this->get_preview_transient_key( $post_id, $preview_id ) );

		if ( ! is_array( $payload ) || empty( $payload['mime_type'] ) ) {
			return new \WP_Error(
				'wttba_thumbnail_preview_expired',
				__( 'The generated thumbnail preview expired. Generate a new thumbnail before saving.', 'creatorstack-ai' ),
				array( 'status' => 404 )
			);
		}

		$attachment_id = $this->save_image_file( $payload, $post );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		set_post_thumbnail( $post_id, $attachment_id );
		update_post_meta( $post_id, self::THUMBNAIL_ATTACHMENT_META_KEY, $attachment_id );

		if ( ! empty( $payload['ai_metadata'] ) && is_array( $payload['ai_metadata'] ) ) {
			Generation_Logger::record( $post_id, $payload['ai_metadata'] );
		}

		delete_transient( $this->get_preview_transient_key( $post_id, $preview_id ) );

		$image_url = wp_get_attachment_url( $attachment_id );

		if ( ! $image_url ) {
			return new \WP_Error(
				'wttba_thumbnail_save_failed',
				__( 'The generated thumbnail was saved, but its URL could not be loaded.', 'creatorstack-ai' ),
				array( 'status' => 500 )
			);
		}

		return array(
			'attachment_id' => $attachment_id,
			'image_url'     => $image_url,
			'edit_url'      => get_edit_post_link( $post_id, 'raw' ),
			'post_id'       => $post_id,
			'ai_metadata'   => is_array( $payload['ai_metadata'] ?? null ) ? $payload['ai_metadata'] : array(),
		);
	}

	/**
	 * Validate an image attachment for AI reference input.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{id: int, path: string, mime_type: string, size: int, title: string}|\WP_Error
	 */
	private function validate_image_attachment( int $attachment_id ): array|\WP_Error {
		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new \WP_Error(
				'wttba_invalid_image_attachment',
				__( 'The selected image attachment could not be found.', 'creatorstack-ai' ),
				array( 'status' => 404 )
			);
		}

		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
			return new \WP_Error(
				'wttba_invalid_image_attachment',
				__( 'The selected image file is missing from the server.', 'creatorstack-ai' ),
				array( 'status' => 404 )
			);
		}

		$mime_type = (string) get_post_mime_type( $attachment_id );
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, self::ALLOWED_IMAGE_EXTENSIONS, true ) || ! str_starts_with( $mime_type, 'image/' ) ) {
			return new \WP_Error(
				'wttba_invalid_image_attachment',
				__( 'Please select a supported image file.', 'creatorstack-ai' ),
				array( 'status' => 400 )
			);
		}

		$size      = filesize( $path );
		$max_bytes = $this->get_max_image_bytes();

		if ( false === $size || $size > $max_bytes ) {
			return new \WP_Error(
				'wttba_image_too_large',
				sprintf(
					/* translators: %s: maximum upload size. */
					__( 'The selected image file is too large. The maximum size is %s.', 'creatorstack-ai' ),
					size_format( $max_bytes )
				),
				array( 'status' => 400 )
			);
		}

		return array(
			'id'        => $attachment_id,
			'path'      => $path,
			'mime_type' => $mime_type,
			'size'      => (int) $size,
			'title'     => get_the_title( $attachment ),
		);
	}

	/**
	 * Get an editable post object.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|\WP_Error
	 */
	private function get_editable_post( int $post_id ): \WP_Post|\WP_Error {
		$post = get_post( $post_id );

		if ( ! $post || 'post' !== $post->post_type ) {
			return new \WP_Error(
				'wttba_post_not_found',
				__( 'The post could not be found.', 'creatorstack-ai' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You are not allowed to edit this post.', 'creatorstack-ai' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return $post;
	}

	/**
	 * Build the image prompt.
	 *
	 * @param \WP_Post   $post            Post object.
	 * @param array      $style           Primary style preset.
	 * @param array|null $secondary_style Optional secondary style preset.
	 * @param bool       $has_author      Whether an author reference is included.
	 * @param array      $references      Additional reference images.
	 * @return string
	 */
	private function build_prompt( \WP_Post $post, array $style, ?array $secondary_style, bool $has_author, array $references ): string {
		$title      = get_the_title( $post );
		$excerpt    = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
		$content    = do_blocks( $post->post_content );
		$content    = strip_shortcodes( $content );
		$content    = wp_strip_all_tags( $content, true );
		$content    = trim( preg_replace( '/\s+/', ' ', $content ) ?? '' );
		$content    = mb_substr( $content, 0, self::MAX_SOURCE_LENGTH );
		$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
		$categories = is_array( $categories ) ? implode( ', ', array_map( 'sanitize_text_field', $categories ) ) : '';

		$secondary = '';
		if ( null !== $secondary_style ) {
			$secondary = sprintf(
				"\n- Blend this secondary style subtly into the primary direction: %s",
				$secondary_style['instruction']
			);
		}

		$reference_instruction = '';
		if ( $has_author ) {
			$reference_instruction .= "\n- The first attached reference image is the author. Preserve the author's recognizable likeness while creating a new thumbnail image.";
		}

		if ( ! empty( $references ) ) {
			$reference_instruction .= "\n- The remaining attached reference images are logos, objects, or brand elements. Include them only when they naturally support the post topic.";
		}

		return sprintf(
			'Create a landscape 16:9 featured image thumbnail for a WordPress post.

## Post
Title: %1$s
Excerpt: %2$s
Categories: %3$s
Content summary: %4$s

## Visual direction
- Primary style: %5$s%6$s
- Make the image readable at small thumbnail sizes.
- Create one cohesive image, not a collage.
- Do not render headline text, captions, UI chrome, watermarks, or fake lettering.
- Leave useful negative space so WordPress themes or social platforms can overlay text if needed.%7$s
',
			$title,
			'' === $excerpt ? __( 'No excerpt provided.', 'creatorstack-ai' ) : $excerpt,
			'' === $categories ? __( 'Uncategorized', 'creatorstack-ai' ) : $categories,
			'' === $content ? __( 'No post content is available yet.', 'creatorstack-ai' ) : $content,
			$style['instruction'],
			$secondary,
			$reference_instruction
		);
	}

	/**
	 * Generate an image result via the AI Client.
	 *
	 * @param string     $prompt           Prompt text.
	 * @param array|null $author_reference Optional author image reference.
	 * @param array      $references       Additional reference images.
	 * @return mixed|\WP_Error
	 */
	private function generate_image_result( string $prompt, ?array $author_reference, array $references ): mixed {
		$this->add_ai_request_timeout_filter();

		try {
			$builder = wp_ai_client_prompt();

			if ( is_wp_error( $builder ) || ! is_object( $builder ) ) {
				return new \WP_Error(
					'wttba_ai_client_missing',
					__( 'The WordPress AI Client is not available for thumbnail generation.', 'creatorstack-ai' ),
					AI_Provider_Status::get_configuration_error_data()
				);
			}

			$builder = $builder
				->with_text( $prompt )
				->using_system_instruction( __( 'You are a professional art director creating original WordPress featured images for creator content.', 'creatorstack-ai' ) )
				->using_temperature( 0.8 )
				->using_model_preference( ...self::IMAGE_MODEL_PREFERENCES );

			if ( null !== $author_reference ) {
				$builder = $builder->with_file( $author_reference['path'], $author_reference['mime_type'] );
			}

			foreach ( $references as $reference ) {
				$builder = $builder->with_file( $reference['path'], $reference['mime_type'] );
			}

			$result = $builder->generate_image_result();
		} finally {
			$this->remove_ai_request_timeout_filter();
		}

		if ( is_wp_error( $result ) ) {
			error_log( sprintf( '[CreatorStack AI] Thumbnail generation failed - %s: %s', $result->get_error_code(), $result->get_error_message() ) );
			return $result;
		}

		return $result;
	}

	/**
	 * Extract a serializable image payload from an AI result.
	 *
	 * @param mixed $result AI Client result object.
	 * @return array<string, string>|\WP_Error
	 */
	private function extract_image_file_payload( mixed $result ): array|\WP_Error {
		try {
			$file = is_object( $result ) && method_exists( $result, 'toImageFile' ) ? $result->toImageFile() : null;
		} catch ( \Throwable $throwable ) {
			error_log( sprintf( '[CreatorStack AI] Thumbnail result parse failed - %s', $throwable->getMessage() ) );
			return new \WP_Error(
				'wttba_thumbnail_generation_failed',
				__( 'The AI provider did not return a usable image file.', 'creatorstack-ai' ),
				array( 'status' => 502 )
			);
		}

		if ( ! is_object( $file ) ) {
			return new \WP_Error(
				'wttba_thumbnail_generation_failed',
				__( 'The AI provider did not return a usable image file.', 'creatorstack-ai' ),
				array( 'status' => 502 )
			);
		}

		$mime_type = method_exists( $file, 'getMimeType' ) ? (string) $file->getMimeType() : 'image/png';

		if ( method_exists( $file, 'getBase64Data' ) && $file->getBase64Data() ) {
			$base64 = (string) $file->getBase64Data();

			return array(
				'storage'        => 'inline',
				'mime_type'      => $mime_type,
				'base64_data'    => $base64,
				'image_data_uri' => sprintf( 'data:%s;base64,%s', $mime_type, $base64 ),
			);
		}

		if ( method_exists( $file, 'getDataUri' ) && $file->getDataUri() ) {
			$data_uri = (string) $file->getDataUri();
			$base64   = preg_replace( '/^data:[^;]+;base64,/', '', $data_uri );

			if ( is_string( $base64 ) && '' !== $base64 ) {
				return array(
					'storage'        => 'inline',
					'mime_type'      => $mime_type,
					'base64_data'    => $base64,
					'image_data_uri' => $data_uri,
				);
			}
		}

		if ( method_exists( $file, 'getUrl' ) && $file->getUrl() ) {
			return array(
				'storage'   => 'remote',
				'mime_type' => $mime_type,
				'image_url' => esc_url_raw( (string) $file->getUrl() ),
			);
		}

		return new \WP_Error(
			'wttba_thumbnail_generation_failed',
			__( 'The generated thumbnail was returned in an unsupported format.', 'creatorstack-ai' ),
			array( 'status' => 502 )
		);
	}

	/**
	 * Save an extracted image payload to the Media Library.
	 *
	 * @param array    $payload Image payload.
	 * @param \WP_Post $post    Parent post.
	 * @return int|\WP_Error Attachment ID or error.
	 */
	private function save_image_file( array $payload, \WP_Post $post ): int|\WP_Error {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( 'remote' === ( $payload['storage'] ?? '' ) && ! empty( $payload['image_url'] ) ) {
			return $this->save_remote_image_file( (string) $payload['image_url'], $post );
		}

		if ( empty( $payload['base64_data'] ) || empty( $payload['mime_type'] ) ) {
			return new \WP_Error(
				'wttba_thumbnail_save_failed',
				__( 'The generated thumbnail preview could not be saved.', 'creatorstack-ai' ),
				array( 'status' => 500 )
			);
		}

		$image_bytes = base64_decode( (string) $payload['base64_data'], true );

		if ( false === $image_bytes ) {
			return new \WP_Error(
				'wttba_thumbnail_save_failed',
				__( 'The generated thumbnail data could not be decoded.', 'creatorstack-ai' ),
				array( 'status' => 500 )
			);
		}

		$mime_type = sanitize_mime_type( (string) $payload['mime_type'] );
		$extension = $this->get_extension_for_mime_type( $mime_type );
		$filename  = sanitize_file_name( sprintf( '%s-thumbnail.%s', $post->post_name ?: 'post-' . $post->ID, $extension ) );
		$upload    = wp_upload_bits( $filename, null, $image_bytes );

		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error(
				'wttba_thumbnail_save_failed',
				$upload['error'],
				array( 'status' => 500 )
			);
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime_type,
				'post_title'     => sprintf(
					/* translators: %s: post title. */
					__( 'Thumbnail for %s', 'creatorstack-ai' ),
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
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sprintf(
			/* translators: %s: post title. */
			__( 'Generated thumbnail for %s', 'creatorstack-ai' ),
			get_the_title( $post )
		) );

		return (int) $attachment_id;
	}

	/**
	 * Save a remote image URL to the Media Library.
	 *
	 * @param string   $url  Remote URL.
	 * @param \WP_Post $post Parent post.
	 * @return int|\WP_Error Attachment ID or error.
	 */
	private function save_remote_image_file( string $url, \WP_Post $post ): int|\WP_Error {
		$tmp = download_url( $url );

		if ( is_wp_error( $tmp ) ) {
			return new \WP_Error(
				'wttba_thumbnail_save_failed',
				$tmp->get_error_message(),
				array( 'status' => 500 )
			);
		}

		$file = array(
			'name'     => sanitize_file_name( sprintf( '%s-thumbnail.png', $post->post_name ?: 'post-' . $post->ID ) ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file, $post->ID );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return new \WP_Error(
				'wttba_thumbnail_save_failed',
				$attachment_id->get_error_message(),
				array( 'status' => 500 )
			);
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sprintf(
			/* translators: %s: post title. */
			__( 'Generated thumbnail for %s', 'creatorstack-ai' ),
			get_the_title( $post )
		) );

		return (int) $attachment_id;
	}

	/**
	 * Resolve an image file extension from a MIME type.
	 *
	 * @param string $mime_type MIME type.
	 * @return string File extension.
	 */
	private function get_extension_for_mime_type( string $mime_type ): string {
		return match ( $mime_type ) {
			'image/jpeg', 'image/jpg' => 'jpg',
			'image/webp' => 'webp',
			default => 'png',
		};
	}

	/**
	 * Build a user-scoped preview transient key.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $preview_id Preview ID.
	 * @return string
	 */
	private function get_preview_transient_key( int $post_id, string $preview_id ): string {
		return 'wttba_thumb_' . get_current_user_id() . '_' . absint( $post_id ) . '_' . sanitize_key( $preview_id );
	}

	/**
	 * Increase AI Client HTTP timeout while this plugin is issuing generation.
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
	 * Filter AI Client timeout.
	 *
	 * @return int Timeout in seconds.
	 */
	public function filter_ai_request_timeout(): int {
		return self::AI_REQUEST_TIMEOUT;
	}
}
