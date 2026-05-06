# Implementing CreatorStack AI With WordPress AI Client And Connectors

CreatorStack AI is a WordPress plugin for creator workflows: it turns YouTube videos and uploaded audio into draft posts, and it can generate narrated audio from existing post content when the configured AI provider supports text-to-speech.

The implementation is intentionally provider-agnostic. The plugin does not store OpenAI, Anthropic, Google, or other AI provider credentials. Instead, it uses the WordPress AI Client as the prompt interface and the WordPress Connectors API as the provider configuration layer.

That split is the key design decision:

- **Connectors** own provider discovery, API key storage, and the admin configuration UI.
- **The AI Client** owns model selection, provider routing, capability checks, and normalized generation results.
- **CreatorStack AI** owns product-specific workflows, permissions, prompts, REST endpoints, source validation, post creation, and usage logging.

## The Integration Shape

At a high level, the plugin flows like this:

1. The site owner configures an AI provider in **Settings > Connectors**.
2. CreatorStack AI checks which AI capabilities are available.
3. The admin UI only enables workflows that the configured provider can support.
4. User actions call plugin-specific REST endpoints.
5. The server builds a constrained prompt with a source transcript, audio file, or post body.
6. The AI Client routes the request to a suitable configured model.
7. The plugin validates the response, saves WordPress content, and records provider/model metadata.

This keeps arbitrary prompting out of the browser and keeps provider secrets out of the plugin.

The most important files are:

- [`includes/class-ai-provider-status.php`](../includes/class-ai-provider-status.php)
- [`includes/class-content-generator.php`](../includes/class-content-generator.php)
- [`includes/class-post-audio-generator.php`](../includes/class-post-audio-generator.php)
- [`includes/class-rest-controller.php`](../includes/class-rest-controller.php)
- [`includes/class-generation-logger.php`](../includes/class-generation-logger.php)

## 1. Detect The AI Client And Connectors API

The plugin centralizes AI availability checks in `AI_Provider_Status`. That class first checks whether AI is enabled for the site, then whether `wp_ai_client_prompt()` exists.

```php
public static function is_ai_client_available(): bool {
    return self::is_ai_supported_by_site() && function_exists( 'wp_ai_client_prompt' );
}
```

For Connectors, the plugin does not need to register its own connector. It only needs to know whether the Connectors API is present so it can send administrators to the right configuration screen.

```php
public static function is_connectors_api_available(): bool {
    return function_exists( 'wp_get_connectors' )
        || function_exists( 'wp_get_connector' )
        || function_exists( 'wp_is_connector_registered' );
}
```

If Connectors are available, the configuration URL points to the Core Connectors screen:

```php
public static function get_configuration_url(): string {
    if ( self::should_use_connectors_screen() ) {
        return admin_url( 'options-connectors.php' );
    }

    return admin_url( 'options-general.php?page=wp-ai-client' );
}
```

This means the plugin can work with the modern WordPress 7.0 Connectors screen while still retaining a fallback URL for older AI Client installations.

## 2. Let Connectors Own Credentials

The Connectors API gives WordPress a standard place to configure AI providers. AI provider plugins that register with the AI Client provider registry are auto-discovered as connectors. The initial provider plugin pattern covers Anthropic, Google, and OpenAI.

For `api_key` connectors, WordPress checks credentials in this order:

1. Environment variable, such as `OPENAI_API_KEY`.
2. PHP constant, such as `define( 'OPENAI_API_KEY', '...' );`.
3. Database setting managed from **Settings > Connectors**.

CreatorStack AI does not need to know which source was used. Its settings screen only explains that provider credentials are handled by WordPress Connectors, then links users to the right place.

That is the first major implementation lesson: feature plugins should avoid duplicating provider credential screens. Let provider plugins and Connectors handle that responsibility.

## 3. Check Capabilities Before Showing UI

The AI Client supports deterministic capability checks. These checks do not make a billable API call. They inspect the configured providers and models to see whether a prompt can be fulfilled.

CreatorStack AI checks three capabilities:

```php
public static function is_text_generation_supported(): bool {
    return self::is_supported_for( 'is_supported_for_text_generation' );
}

public static function is_text_to_speech_supported(): bool {
    return self::is_supported_for( 'is_supported_for_text_to_speech_conversion' );
}
```

For audio input, the plugin builds a prompt with a tiny silent audio file and asks whether text generation is supported for that multimodal prompt:

```php
$prompt = wp_ai_client_prompt( 'Return a one sentence summary of this audio.' );
$prompt = $prompt->with_file( self::DUMMY_AUDIO_DATA_URI, 'audio/wav' );

return true === $prompt->is_supported_for_text_generation();
```

Those checks power the admin UI. The REST `/capabilities` endpoint exposes the same state to JavaScript:

```php
return new \WP_REST_Response(
    array(
        'textGenerationSupported' => AI_Provider_Status::is_text_generation_supported(),
        'audioInputSupported'     => AI_Provider_Status::is_audio_input_generation_supported(),
        'textToSpeechSupported'   => AI_Provider_Status::is_text_to_speech_supported(),
        'configurationUrl'        => AI_Provider_Status::get_configuration_url(),
        'providers'               => AI_Provider_Status::get_registered_ai_connectors(),
    ),
    200
);
```

This lets the UI disable actions before users hit an avoidable runtime error.

## 4. Use Product-Specific REST Endpoints

The AI Client documentation recommends implementing specific REST endpoints for plugin features instead of allowing arbitrary client-side prompts.

CreatorStack AI follows that pattern. It registers endpoints for:

- Listing YouTube videos.
- Previewing a post from a YouTube transcript.
- Saving a generated preview as a draft.
- Previewing a post from an audio attachment.
- Generating an audio version of an existing post.
- Testing the configured AI connection.

Each route has its own permission callback:

```php
public function can_edit_posts(): bool {
    return current_user_can( 'edit_posts' );
}

public function can_manage_options(): bool {
    return current_user_can( 'manage_options' );
}
```

Audio routes are scoped more tightly. The current user must be able to edit both the target post and the selected attachment.

This is a practical security boundary: the browser can ask for a specific plugin action, but the server owns the prompt, source validation, capability checks, and output handling.

## 5. Generate Structured Content With JSON Schema

The core content generation service is `Content_Generator`. For text sources, it builds a prompt from source material, target language, source title, writing persona, and selected post length.

Then it uses the AI Client fluent API:

```php
$builder = wp_ai_client_prompt( $prompt )
    ->using_system_instruction(
        __( 'You are a professional blog writer creating accurate, SEO-friendly WordPress content.', 'wp-tube-to-blog-ai' )
    )
    ->using_temperature( 0.7 )
    ->using_max_tokens( $post_length['max_tokens'] )
    ->using_model_preference( ...$this->get_text_model_preferences() )
    ->as_json_response( $this->get_article_schema() );

return $this->generate_article_from_builder( $builder, $source_type );
```

The schema requires a title and HTML content:

```php
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
```

Structured output is important here because the plugin needs to create a WordPress draft, not display a free-form chat answer. The generated response has to map cleanly to `post_title` and `post_content`.

After generation, the plugin parses and sanitizes the result:

```php
$result = $builder->generate_text_result();
$json   = $result->toText();
$parsed = json_decode( $json, true );

if ( ! is_array( $parsed ) || empty( $parsed['title'] ) || empty( $parsed['content'] ) ) {
    return new \WP_Error(
        'wttba_ai_parse_error',
        __( 'The AI returned an unexpected response format. Please try generating again.', 'wp-tube-to-blog-ai' )
    );
}

return array(
    'title'       => sanitize_text_field( (string) $parsed['title'] ),
    'content'     => wp_kses_post( (string) $parsed['content'] ),
    'ai_metadata' => Generation_Logger::metadata_from_result( $result, $source_type ),
);
```

The use of `generate_text_result()` instead of `generate_text()` matters because the full result includes provider, model, and token metadata.

## 6. Express Model Preferences Without Hard Dependencies

CreatorStack AI allows administrators to select a preferred model, but it treats that as a preference, not a requirement.

```php
private function get_text_model_preferences(): array {
    $preferred_model = (string) get_option( 'wttba_ai_model', '' );

    if ( in_array( $preferred_model, Settings::AI_MODEL_IDS, true ) && '' !== $preferred_model ) {
        return array_values(
            array_unique(
                array_merge( array( $preferred_model ), self::TEXT_MODEL_PREFERENCES )
            )
        );
    }

    return self::TEXT_MODEL_PREFERENCES;
}
```

The AI Client can use the first available compatible model from the preference list. If none of those models are available, it can still fall back to another compatible configured model.

That is the second major implementation lesson: a distributed plugin should not assume a specific provider or model is installed. It should describe what it needs and let the AI Client route the request.

## 7. Support Audio Input With `with_file()`

Audio-to-post generation uses the same article schema as transcript-based generation, but the prompt includes an audio attachment:

```php
$builder = wp_ai_client_prompt()
    ->with_text( $prompt )
    ->with_file( $audio['path'], $audio['mime_type'] )
    ->using_system_instruction(
        __( 'You are a professional editor turning spoken source material into accurate WordPress articles.', 'wp-tube-to-blog-ai' )
    )
    ->using_temperature( 0.5 )
    ->using_max_tokens( $post_length['max_tokens'] )
    ->using_model_preference( ...$this->get_text_model_preferences() )
    ->as_json_response( $this->get_article_schema() );
```

Before reaching the AI Client, the plugin validates the attachment:

- It must be a WordPress attachment.
- The file must exist and be readable.
- The MIME type must be audio.
- The extension must be one of `mp3`, `m4a`, `wav`, `ogg`, `webm`, `flac`, or `aac`.
- The file must be below the lower of 25 MB and the site upload limit.

That validation keeps the AI request scoped and prevents invalid uploads from becoming provider errors.

## 8. Support Text-To-Speech With `convert_text_to_speech_result()`

Post-to-audio generation uses a separate service, `Post_Audio_Generator`.

It prepares readable narration text from the current post, optionally applies a provider-specific voice, then calls the AI Client text-to-speech method:

```php
$builder = wp_ai_client_prompt( $narration )
    ->using_system_instruction(
        __( 'Convert the supplied WordPress article into clear, natural narration audio.', 'wp-tube-to-blog-ai' )
    );

if ( '' !== trim( $voice ) ) {
    $builder = $builder->as_output_speech_voice( sanitize_key( $voice ) );
}

$result = $builder->convert_text_to_speech_result();
```

The result is converted into an audio file DTO:

```php
$file = $result->toAudioFile();
```

The plugin then saves either inline base64 audio or remote audio into the Media Library, inserts a Core Audio block at the top of the post, and stores the attachment ID in post meta.

## 9. Record Provider, Model, And Token Metadata

Because the plugin uses full result objects, it can log useful operational metadata without coupling itself to a provider.

`Generation_Logger::metadata_from_result()` reads:

- Result ID.
- Provider ID and name.
- Model ID and name.
- Token usage when available.
- Source type and status.
- User ID and generation time.

The metadata is stored on the post and in a rolling recent usage option. Administrators can review it from the plugin settings screen.

This gives site owners observability while keeping the generation pipeline provider-neutral.

## 10. Return WordPress-Native Errors

AI calls can fail for many reasons: no configured provider, an unsupported modality, invalid audio, provider timeout, malformed JSON, or blocked prompts.

CreatorStack AI handles this with `WP_Error` values and a central REST error mapper:

```php
private const ERROR_CATEGORY_MAP = array(
    'wttba_ai_not_supported'          => 'configuration',
    'wttba_audio_input_not_supported' => 'configuration',
    'wttba_tts_not_supported'         => 'configuration',
    'wttba_ai_parse_error'            => 'upstream',
);
```

The REST controller enriches errors with HTTP status codes and `error_category` values before returning them to JavaScript. AI configuration errors also include the provider setup URL and button label.

This keeps frontend behavior predictable:

- Configuration errors send users to **Settings > Connectors**.
- Validation errors explain what input needs to change.
- Upstream errors ask users to retry or check provider availability.

## What This Architecture Gets Right

The main value of the AI Client and Connectors architecture is separation of concerns.

CreatorStack AI does not need separate OpenAI, Anthropic, or Google code paths. It does not need a provider credential form. It does not need to know which concrete model will run the prompt.

Instead, the plugin focuses on WordPress-specific product behavior:

- Fetching videos and transcripts.
- Validating media attachments.
- Defining exact REST routes and permissions.
- Building prompts from trusted server-side sources.
- Requesting structured output.
- Creating draft posts.
- Inserting audio blocks.
- Sanitizing generated HTML.
- Logging AI usage metadata.

The result is a cleaner plugin implementation and a better site-owner experience. Administrators configure providers once in WordPress. Feature plugins can then ask the AI Client for the capability they need and gracefully degrade when that capability is unavailable.

## Further Reading

- [AI Client Integration](ai-client-integration.md)
- [Introducing the AI Client in WordPress 7.0](introducing-the-ai-client-in-wordpress-7-0.md)
- [Introducing the Connectors API in WordPress 7.0](introducing-the-connectors-api-in-wordpress-7-0.md)
- [CreatorStack AI User Guide](user-guide-en.md)
