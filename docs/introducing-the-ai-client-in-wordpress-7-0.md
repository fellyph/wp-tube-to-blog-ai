Title: Introducing the AI Client in WordPress 7.0
Author: Felix Arntz
Published: March 24, 2026
Source: https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/

---

# Introducing the AI Client in WordPress 7.0

WordPress 7.0 includes a built-in AI Client — a provider-agnostic PHP API that lets plugins send prompts to AI models and receive results through a consistent interface. Your plugin describes _what_ it needs and _how_ it needs it. WordPress handles routing the request to a suitable model from a provider the site owner has configured.

This post explains the API surface, walks through code examples, and covers what plugin developers need to know.

## The entry point: `wp_ai_client_prompt()`

Every interaction starts with:

```php
$builder = wp_ai_client_prompt();
```

This returns a `WP_AI_Client_Prompt_Builder` object, a fluent builder that offers a myriad of ways to customize your prompt. You chain configuration methods and then call a generation method to receive a result:

```php
$text = wp_ai_client_prompt( 'Summarize the benefits of caching in WordPress.' )
    ->using_temperature( 0.7 )
    ->generate_text();
```

You can pass the prompt text directly as a parameter to `wp_ai_client_prompt()` for convenience, though alternatively the `with_text()` method is available for building the prompt incrementally.

## Text generation

Here's a basic text generation example:

```php
$text = wp_ai_client_prompt( 'Write a haiku about WordPress.' )
    ->generate_text();

if ( is_wp_error( $text ) ) {
    // Handle error.
    return;
}

echo wp_kses_post( $text );
```

You can pass a JSON schema so that the model returns structured data as a JSON string:

```php
$schema = array(
    'type'  => 'array',
    'items' => array(
        'type'       => 'object',
        'properties' => array(
            'plugin_name' => array( 'type' => 'string' ),
            'category'    => array( 'type' => 'string' ),
        ),
        'required' => array( 'plugin_name', 'category' ),
    ),
);

$json = wp_ai_client_prompt( 'List 5 popular WordPress plugins with their primary category.' )
    ->as_json_response( $schema )
    ->generate_text();

if ( is_wp_error( $json ) ) {
    // Handle error.
    return;
}

$data = json_decode( $json, true );
```

You can request multiple response candidates as variations for the same prompt:

```php
$texts = wp_ai_client_prompt( 'Write a tagline for a photography blog.' )
    ->generate_texts( 4 );
```

## Image generation

Here's a basic image generation example:

```php
use WordPress\AiClient\Files\DTO\File;

$image_file = wp_ai_client_prompt( 'A futuristic WordPress logo in neon style' )
    ->generate_image();

if ( is_wp_error( $image_file ) ) {
    // Handle error.
    return;
}

echo '<img src="' . esc_url( $image_file->getDataUri() ) . '" alt="">';
```

`generate_image()` returns a `File` DTO with access to the image data via `getDataUri()`.

Similar to text generation, you can request multiple variations of the same image:

```php
$images = wp_ai_client_prompt( 'Aerial shot of snowy plains, cinematic.' )
    ->generate_images( 4 );

if ( is_wp_error( $images ) ) {
    // Handle error.
    return;
}

foreach ( $images as $image_file ) {
    echo '<img src="' . esc_url( $image_file->getDataUri() ) . '">';
}
```

## Getting the full result object

For richer metadata, e.g. covering provider and model information, use `generate_*_result()` instead. For example, for image generation:

```php
$result = wp_ai_client_prompt( 'A serene mountain landscape.' )
    ->generate_image_result();
```

This returns a `GenerativeAiResult` object that provides several pieces of additional information, including token usage and which provider and which model responded to the prompt. The most relevant methods for this additional metadata are:

* `getTokenUsage()`: Returns the token usage, broken down by input, output, and optionally thinking.
* `getProviderMetadata()`: Returns metadata about the provider that handled the request.
* `getModelMetadata()`: Returns metadata about the model that handled the request (through the provider).

The `GenerativeAiResult` object is serializable and can be passed directly to `rest_ensure_response()`, making it straightforward to expose AI features through the REST API.

Available `generate_*_result()` methods:

* `generate_text_result()`
* `generate_image_result()`
* `convert_text_to_speech_result()`
* `generate_speech_result()`
* `generate_video_result()`

Use the appropriate method for the modality you are working with. Each returns a `GenerativeAiResult` object with rich metadata.

### Model preferences

The models available on each WordPress site depends on which AI providers the administrators of that site have configured in the **Settings > Connectors** screen.

Since your plugin doesn't control which providers are available on each site, use `using_model_preference()` to indicate which models would be ideal. The AI Client will use the first model from that list that is available, falling back to any compatible model if none are available:

```php
$text_result = wp_ai_client_prompt( 'Summarize the history of the printing press.' )
    ->using_temperature( 0.1 )
    ->using_model_preference(
        'claude-sonnet-4-6',
        'gemini-3.1-pro-preview',
        'gpt-5.4'
    )
    ->generate_text_result();
```

This is a preference, not a requirement. Your plugin should function without it. Keep in mind that you can test or verify which model was used by looking at the full result object, under the `providerMetadata` and `modelMetadata` properties.

If you don't specify a model preference, the first model encountered across the configured providers that is suitable will be used. It is up to the individual provider implementations to sort the provider's models in a reasonable manner, e.g. so that more recent models appear before older models of the same model family.

## Feature detection

Not every WordPress site will have an AI provider configured, and not every provider supports every capability and every option. Before showing AI-powered UI, check whether the feature can work:

```php
$builder = wp_ai_client_prompt( 'test' )
    ->using_temperature( 0.7 );

if ( $builder->is_supported_for_text_generation() ) {
    // Safe to show text generation UI.
}
```

These checks do not make API calls. They use deterministic logic to match the builder's configuration against the capabilities of available models. As such, they are fast to run and there is no cost incurred by calling them.

Available support check methods:

* `is_supported_for_text_generation()`
* `is_supported_for_image_generation()`
* `is_supported_for_text_to_speech_conversion()`
* `is_supported_for_speech_generation()`
* `is_supported_for_video_generation()`

Use these to conditionally load your UI, show a helpful notice when the feature is unavailable, or skip registering UI altogether. Never assume that AI features will be available just because WordPress 7.0 is installed.

## Advanced configuration

### System instructions

```php
$text = wp_ai_client_prompt( 'Explain caching.' )
    ->using_system_instruction( 'You are a WordPress developer writing documentation.' )
    ->generate_text();
```

### Max tokens

```php
$text = wp_ai_client_prompt( 'Explain quantum computing in complicated terms.' )
    ->using_max_tokens( 8000 )
    ->generate_text();
```

### Output file type and orientation for images

```php
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;

$result = wp_ai_client_prompt()
    ->with_text( 'A vibrant sunset over the ocean.' )
    ->as_output_file_type( FileTypeEnum::inline() )
    ->as_output_media_orientation( MediaOrientationEnum::from( 'landscape' ) )
    ->generate_image_result();
```

### Multimodal output

```php
use WordPress\AiClient\Messages\Enums\ModalityEnum;

$result = wp_ai_client_prompt( 'Create a recipe for a chocolate cake and include photos for the steps.' )
    ->as_output_modalities( ModalityEnum::text(), ModalityEnum::image() )
    ->generate_result();

if ( is_wp_error( $result ) ) {
    // Handle error.
    return;
}

foreach ( $result->toMessage()->getParts() as $part ) {
    if ( $part->isText() ) {
        echo wp_kses_post( $part->getText() );
    } elseif ( $part->isFile() && $part->getFile()->isImage() ) {
        echo '<img src="' . esc_url( $part->getFile()->getDataUri() ) . '">';
    }
}
```

### Additional builder methods

The full list of configuration methods is available via the `WP_AI_Client_Prompt_Builder` class. Key methods include:

| **Configuration** | **Method** |
|---|---|
| Prompt text | `with_text()` |
| File input | `with_file()` |
| Conversation history (relevant for multi-turn / chats) | `with_history()` |
| System instruction | `using_system_instruction()` |
| Temperature | `using_temperature()` |
| Max tokens | `using_max_tokens()` |
| Top-p / Top-k | `using_top_p(), using_top_k()` |
| Stop sequences | `using_stop_sequences()` |
| Model preference | `using_model_preference()` |
| Output modalities | `as_output_modalities()` |
| Output file type | `as_output_file_type()` |
| JSON response | `as_json_response()` |

## Error handling

`wp_ai_client_prompt()` generator methods return `WP_Error` on failure, following WordPress conventions:

```php
$text = wp_ai_client_prompt( 'Hello' )
    ->generate_text();

if ( is_wp_error( $text ) ) {
    // Handle the error.
}
```

When used in a REST API callback, both `GenerativeAiResult` and `WP_Error` can be passed to `rest_ensure_response()` directly:

```php
function my_rest_callback( WP_REST_Request $request ) {
    $result = wp_ai_client_prompt( $request->get_param( 'prompt' ) )
        ->generate_text_result();

    return rest_ensure_response( $result );
}
```

If an error occurs, it will automatically have a semantically meaningful HTTP response code attached to it.

## Controlling AI availability

For granular control, the `wp_ai_client_prevent_prompt` filter allows preventing specific prompts from executing:

```php
add_filter(
    'wp_ai_client_prevent_prompt',
    function ( bool $prevent, WP_AI_Client_Prompt_Builder $builder ): bool {
        // Example: Block all prompts for non-admin users.
        if ( ! current_user_can( 'manage_options' ) ) {
            return true;
        }
        return $prevent;
    },
    10,
    2
);
```

When a prompt is prevented:

* No AI call is attempted.
* `is_supported_*()` methods return `false`, allowing plugins to gracefully hide their UI.
* `generate_*()` methods return a `WP_Error`.

## Architecture

The AI Client in WordPress 7.0 consists of two layers:

1. **PHP AI Client** ([wordpress/php-ai-client](https://github.com/WordPress/php-ai-client)) — A provider-agnostic PHP SDK bundled in Core as an external library. This is the engine that handles provider communication, model selection, and response normalization. Since it is technically a WordPress agnostic PHP SDK which other PHP projects can use too, it uses camelCase method naming and makes use of exceptions.

2. **WordPress wrapper** — Core's `WP_AI_Client_Prompt_Builder` class wraps the PHP AI Client with WordPress conventions: snake_case methods, `WP_Error` returns, and integration with WordPress HTTP transport, the Abilities API, the Connectors/Settings infrastructure, and the WordPress hooks system.

The `wp_ai_client_prompt()` function is the recommended entry point. It returns a `WP_AI_Client_Prompt_Builder` instance that catches exceptions from the underlying SDK and converts them to `WP_Error` objects.

### Credential management

API keys are managed through the [Connectors API](https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/). AI provider plugins that register with the PHP AI Client's provider registry get automatic connector integration — including the **Settings > Connectors** admin UI for API key management. Plugin developers using the AI Client to build features do not need to handle credentials at all.

### Official provider plugins

WordPress Core does not bundle any AI providers directly. Instead, they are developed and maintained as plugins, which allows for more flexible and rapid iteration speed, in accordance with how fast AI evolves. The AI Client in WordPress Core provides the stable foundation, and as an abstraction layer is sufficiently detached from provider specific requirements that may change overnight.

While anyone is able to implement new provider plugins, the WordPress project itself has developed three initial flagship implementations, to integrate with the most popular AI providers. These plugins are:

* [AI Provider for Anthropic](https://wordpress.org/plugins/ai-provider-for-anthropic/)
* [AI Provider for Google](https://wordpress.org/plugins/ai-provider-for-google/)
* [AI Provider for OpenAI](https://wordpress.org/plugins/ai-provider-for-openai/)

### Separately available: Client-side JavaScript API

A JavaScript API with a similar fluent prompt builder is available via the [wp-ai-client](https://github.com/WordPress/wp-ai-client) package. It uses REST endpoints under the hood to connect to the server-side infrastructure. This API is not part of Core, and it is still being evaluated whether this approach is scalable for general use. Because the API allows arbitrary prompt execution from the client-side, it requires a high-privilege capability check, which by default is only granted to administrators. This restriction is necessary to prevent untrusted users from sending any prompt to any configured AI provider. As such, using this approach in a distributed plugin is not recommended.

For now, the recommended approach is to implement individual REST API endpoints for each specific AI feature your plugin provides, and have your JavaScript functionality call those endpoints. This allows you to enforce granular permission checks and limit the scope of what can be executed from the client-side. It also keeps the actual AI prompt handling and configuration fully scoped to be server-side only.

## Migration from php-ai-client and wp-ai-client

If you have been using these packages in your plugin(s) before, here's what to know.

### Recommended: require WordPress 7.0

The simplest path is to update your plugin's Requires at least header to 7.0 and remove the Composer dependencies on `wordpress/php-ai-client` and its transitive dependencies.

Replace any `AI_Client::prompt()` calls with `wp_ai_client_prompt()`.

For the `wordpress/wp-ai-client` package, if you are not using the package's REST API endpoints or JavaScript API, you can simply remove it as a dependency, since everything else it does is now part of WordPress Core.

### If you must support WordPress < 7.0

#### PHP AI Client (`wordpress/php-ai-client`)

If your plugin still needs to run on WordPress versions before 7.0 while also bundling wordpress/php-ai-client, you will need a conditional autoloader workaround. The PHP AI Client and its dependencies are now loaded by Core on 7.0+, so loading them again via Composer will cause conflicts (duplicate class definitions).

The solution: only register your Composer autoloader for these dependencies when running on WordPress versions before 7.0:

```php
if ( ! function_exists( 'wp_get_wp_version' ) || version_compare( wp_get_wp_version(), '7.0', '<' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}
```

Due to how Composer's autoloader works — loading all dependencies at once rather than selectively — a more granular approach was not feasible. This means the conditional check needs to wrap the entire autoloader. Alternatively, break your PHP dependencies apart in two separate Composer setups, one that can always be autoloaded, and another one for the `wordpress/php-ai-client` package and its dependencies only, which would be conditionally autoloaded.

#### WP AI Client (`wordpress/wp-ai-client`)

The `wordpress/wp-ai-client` package handles the WordPress 7.0 transition automatically. On 7.0+, it disables its own PHP SDK infrastructure (since Core handles it natively) but keeps the REST API endpoints and JavaScript API active, as those aren't in Core yet.

You can continue loading this package unconditionally. It detects the WordPress version and only activates the parts that aren't already provided by Core. No conditional loading needed. However, make sure to stay up to date on this package, because it will likely be discontinued soon, in favor of moving the REST API endpoints and JavaScript API into Gutenberg. There are ongoing discussions on whether these should be merged into Core too, see [#64872](https://core.trac.wordpress.org/ticket/64872) and [#64873](https://core.trac.wordpress.org/ticket/64873).

See the [WP AI Client upgrade guide](https://github.com/WordPress/wp-ai-client/blob/trunk/UPGRADE.md) for additional migration details.

## Additional resources

* Trac ticket: [#64591](https://core.trac.wordpress.org/ticket/64591)
* [PHP AI Client](https://github.com/WordPress/php-ai-client) (bundled library)
* [WP AI Client](https://github.com/WordPress/wp-ai-client) (original package, now mostly merged into Core)
* [Original merge proposal](https://make.wordpress.org/core/2026/02/03/proposal-for-merging-wp-ai-client-into-wordpress-7-0/)
