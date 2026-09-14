<?php
/**
 * Discoverable creator workflows for the WordPress Abilities API.
 *
 * @package CreatorStack_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adapts the existing REST workflows without bypassing their validation or permissions.
 */
class Abilities {
	/** Wire the lazy Core registries; harmless when the API is unavailable. */
	public function __construct() {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/** Register the product category before its abilities are resolved. */
	public function register_category(): void {
		wp_register_ability_category(
			'creatorstack-ai',
			array(
				'label'       => __( 'CreatorStack AI', 'creatorstack-ai' ),
				'description' => __( 'Create WordPress content from YouTube videos and audio, and generate narration and featured images.', 'creatorstack-ai' ),
			)
		);
	}

	/** Register stable public names, schemas, and behavior annotations. */
	public function register_abilities(): void {
		foreach ( $this->get_definitions() as $name => $definition ) {
			$readonly = 'GET' === $definition['method'];
			wp_register_ability(
				'creatorstack-ai/' . $name,
				array(
					'label'               => $definition['label'],
					'description'         => $definition['description'],
					'category'            => 'creatorstack-ai',
					'input_schema'        => $definition['input'],
					'output_schema'       => $definition['output'],
					'permission_callback' => static function ( $input = null ) use ( $definition ): bool {
						$request = new \WP_REST_Request();
						foreach ( (array) $input as $key => $value ) {
							$request->set_param( $key, $value );
						}
						return ( new REST_Controller() )->{$definition['permission']}( $request );
					},
					'execute_callback'    => function ( $input = null ) use ( $definition ) {
						return $this->execute( $definition, (array) $input );
					},
					'meta'                => array(
						'show_in_rest' => true,
						'annotations' => array(
							'readonly'    => $readonly,
							'destructive' => $definition['destructive'] ?? false,
							'idempotent'  => $readonly,
						),
					),
				)
			);
		}
	}

	/**
	 * Dispatch internally as the current user, including REST sanitizers and defaults.
	 *
	 * @param array $definition Fixed route and method, never supplied by the caller.
	 * @param array $input Validated ability input.
	 * @return mixed|\WP_Error Workflow data or its original structured error.
	 */
	private function execute( array $definition, array $input ) {
		$route = $definition['route'];
		if ( str_contains( $route, '{id}' ) ) {
			$route = str_replace( '{id}', rawurlencode( (string) $input['id'] ), $route );
			unset( $input['id'] );
		}
		$request = new \WP_REST_Request( $definition['method'], '/wttba/v1' . $route );
		foreach ( $input as $key => $value ) {
			$request->set_param( $key, $value );
		}
		$response = rest_do_request( $request );
		return $response->is_error() ? $response->as_error() : $response->get_data();
	}

	/**
	 * Build an object schema. Output schemas allow additive workflow metadata.
	 *
	 * @param array $properties Property schemas.
	 * @param array $required Required property names.
	 * @param bool  $additional Allow additional properties.
	 * @return array
	 */
	private function object_schema( array $properties, array $required = array(), bool $additional = false ): array {
		$schema = array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => $additional,
		);
		if ( $required ) {
			$schema['required'] = $required;
		}
		return $schema;
	}

	/**
	 * Describe the supported workflows independently of whether a provider is connected.
	 *
	 * @return array<string, array>
	 */
	private function get_definitions(): array {
		$string   = array( 'type' => 'string' );
		$positive = array( 'type' => 'integer', 'minimum' => 1 );
		$video_id = array( 'type' => 'string', 'pattern' => '^[a-zA-Z0-9_-]+$' );
		$writing  = array(
			'language' => array(
				'type'        => 'string',
				'enum'        => array_keys( Settings::LANGUAGES ),
				'description' => __( 'Language code. Omit to use the saved default language.', 'creatorstack-ai' ),
			),
			'persona' => $string + array( 'description' => __( 'Writing instructions. Omit to use the saved persona.', 'creatorstack-ai' ) ),
		);
		$preview_output = $this->object_schema( array( 'title' => $string, 'content' => $string ), array( 'title', 'content' ), true );
		$draft_output   = $this->object_schema( array( 'post_id' => $positive, 'edit_url' => $string ), array( 'post_id', 'edit_url' ), true );
		$styles         = array_keys( Thumbnail_Generator::get_public_style_presets() );

		return array(
			'get-capabilities' => array(
				'label'       => __( 'Get creator capabilities', 'creatorstack-ai' ),
				'description' => __( 'Inspect enabled workflows, AI provider support, audio limits, and thumbnail styles before generating content. Does not expose credentials.', 'creatorstack-ai' ),
				'method'      => 'GET',
				'route'       => '/capabilities',
				'permission'  => 'can_edit_posts',
				'input'       => array(),
				'output'      => $this->object_schema( array( 'features' => array( 'type' => 'object' ) ), array( 'features' ), true ),
			),
			'test-ai-connection' => array(
				'label'       => __( 'Test AI connection', 'creatorstack-ai' ),
				'description' => __( 'Run a small text generation request and record diagnostics. Requires administrator settings permission and may incur provider charges.', 'creatorstack-ai' ),
				'method'      => 'POST',
				'route'       => '/ai/test',
				'permission'  => 'can_manage_options',
				'input'       => array(),
				'output'      => $this->object_schema( array( 'message' => $string, 'summary' => $string ), array( 'message', 'summary' ), true ),
			),
			'list-videos' => array(
				'label'       => __( 'List YouTube videos', 'creatorstack-ai' ),
				'description' => __( 'List videos from the configured YouTube channel, with pagination. Requires the YouTube workflow and connector; may consume YouTube API quota.', 'creatorstack-ai' ),
				'method'      => 'GET',
				'route'       => '/videos',
				'permission'  => 'can_edit_posts',
				'input'       => $this->object_schema( array( 'page_token' => $string, 'max_results' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 5 ) ) ),
				'output'      => $this->object_schema( array( 'items' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ), 'nextPageToken' => $string, 'totalResults' => array( 'type' => 'integer' ) ), array( 'items', 'totalResults' ), true ),
			),
			'get-video' => array(
				'label'       => __( 'Get YouTube video', 'creatorstack-ai' ),
				'description' => __( 'Retrieve details for a YouTube video ID. Requires the YouTube workflow and connector; may consume YouTube API quota.', 'creatorstack-ai' ),
				'method'      => 'GET',
				'route'       => '/videos/{id}',
				'permission'  => 'can_edit_posts',
				'input'       => $this->object_schema( array( 'id' => $video_id ), array( 'id' ) ),
				'output'      => $this->object_schema( array( 'id' => $string, 'title' => $string ), array( 'id', 'title' ), true ),
			),
			'preview-youtube-post' => array(
				'label'       => __( 'Preview a post from YouTube', 'creatorstack-ai' ),
				'description' => __( 'Generate title and HTML content from a video transcript without saving a post. Supports a manual transcript. Calls the AI provider and consumes generation quota.', 'creatorstack-ai' ),
				'method'      => 'POST',
				'route'       => '/preview',
				'permission'  => 'can_edit_posts',
				'input'       => $this->object_schema( array( 'video_id' => $video_id, 'manual_transcript' => $string ) + $writing, array( 'video_id' ) ),
				'output'      => $preview_output,
			),
			'save-youtube-draft' => array(
				'label'       => __( 'Save a YouTube draft', 'creatorstack-ai' ),
				'description' => __( 'Create a new draft from reviewed title and HTML content, with source video metadata, an embed and a thumbnail when available. Each execution creates a new post; it does not publish.', 'creatorstack-ai' ),
				'method'      => 'POST',
				'route'       => '/save-draft',
				'permission'  => 'can_edit_posts',
				'input'       => $this->object_schema( array( 'video_id' => $video_id, 'title' => $string, 'content' => $string, 'ai_metadata' => array( 'type' => 'object' ) ), array( 'video_id', 'title', 'content' ) ),
				'output'      => $draft_output,
			),
			'preview-audio-post' => array(
				'label'       => __( 'Preview a post from audio', 'creatorstack-ai' ),
				'description' => __( 'Generate title and HTML from an existing audio attachment for an editable post. Links the attachment to the post and updates source metadata and diagnostics. Consumes AI quota.', 'creatorstack-ai' ),
				'method'      => 'POST',
				'route'       => '/audio-post/preview',
				'permission'  => 'can_preview_audio_post',
				'destructive' => true,
				'input'       => $this->object_schema( array( 'post_id' => $positive, 'attachment_id' => $positive ) + $writing, array( 'post_id', 'attachment_id' ) ),
				'output'      => $preview_output,
			),
			'create-audio-draft' => array(
				'label'       => __( 'Create a draft from audio', 'creatorstack-ai' ),
				'description' => __( 'Generate and save a new draft from an editable audio attachment, then link the attachment to the new post. Consumes AI quota. Each execution creates a new draft.', 'creatorstack-ai' ),
				'method'      => 'POST',
				'route'       => '/audio-post/draft',
				'permission'  => 'can_create_audio_post_draft',
				'destructive' => true,
				'input'       => $this->object_schema( array( 'attachment_id' => $positive ) + $writing, array( 'attachment_id' ) ),
				'output'      => $draft_output,
			),
			'generate-post-audio' => array(
				'label'       => __( 'Generate post narration', 'creatorstack-ai' ),
				'description' => __( 'Generate a narration attachment from an editable post and insert or replace its audio block. Requires uploads and the post-to-audio feature. Consumes AI quota and changes post content.', 'creatorstack-ai' ),
				'method'      => 'POST',
				'route'       => '/posts/{id}/audio',
				'permission'  => 'can_generate_post_audio',
				'destructive' => true,
				'input'       => $this->object_schema( array( 'id' => $positive, 'voice' => $string, 'overwrite_block' => array( 'type' => 'boolean', 'default' => true ) ), array( 'id' ) ),
				'output'      => $this->object_schema( array( 'attachment_id' => $positive, 'audio_url' => $string, 'audio_block' => $string, 'post_content' => $string ), array( 'attachment_id', 'audio_url' ), true ),
			),
			'preview-post-thumbnail' => array(
				'label'       => __( 'Preview a post thumbnail', 'creatorstack-ai' ),
				'description' => __( 'Generate a temporary image preview from post content and optional editable reference images. Consumes AI quota. Use the returned preview_id with save-post-thumbnail to set the featured image.', 'creatorstack-ai' ),
				'method'      => 'POST',
				'route'       => '/posts/{id}/thumbnail/preview',
				'permission'  => 'can_generate_post_thumbnail',
				'input'       => $this->object_schema(
					array(
						'id'                       => $positive,
						'style'                    => array( 'type' => 'string', 'enum' => $styles ),
						'secondary_style'          => array( 'type' => 'string', 'enum' => array_merge( array( '' ), $styles ) ),
						'author_attachment_id'     => array( 'type' => 'integer', 'minimum' => 0 ),
						'reference_attachment_ids' => array( 'type' => 'array', 'items' => $positive, 'maxItems' => Thumbnail_Generator::MAX_REFERENCE_IMAGES ),
					),
					array( 'id', 'style' )
				),
				'output'      => $this->object_schema( array( 'preview_id' => $string ), array( 'preview_id' ), true ),
			),
			'save-post-thumbnail' => array(
				'label'       => __( 'Save a post thumbnail', 'creatorstack-ai' ),
				'description' => __( 'Save a generated preview to the Media Library and replace the featured image of an editable post. The preview must belong to the current user and post and must not have expired.', 'creatorstack-ai' ),
				'method'      => 'POST',
				'route'       => '/posts/{id}/thumbnail',
				'permission'  => 'can_generate_post_thumbnail',
				'destructive' => true,
				'input'       => $this->object_schema( array( 'id' => $positive, 'preview_id' => $string + array( 'minLength' => 1 ) ), array( 'id', 'preview_id' ) ),
				'output'      => $this->object_schema( array( 'attachment_id' => $positive, 'post_id' => $positive, 'image_url' => $string ), array( 'attachment_id', 'post_id', 'image_url' ), true ),
			),
		);
	}
}
