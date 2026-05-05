# AI Client Integration

This document explains how WP Tube-to-Blog AI integrates with the WordPress AI Client to generate blog posts from YouTube video transcripts.

## Overview

The plugin uses the WordPress AI Client's fluent API (`wp_ai_client_prompt()`) to send video transcripts to an AI provider and receive structured blog post content (title + HTML body) in return. The entire AI interaction is isolated in `Post_Generator::call_ai()`.

## WordPress 7.0+ (Recommended)

WordPress 7.0 ships with the AI Client and Connectors API built into Core. No Composer package or additional plugin is needed beyond an AI provider plugin.

### Setup

1. Install and activate an AI provider plugin:
   - **AI Provider for Anthropic** (Claude models)
   - **AI Provider for Google** (Gemini models)
   - **AI Provider for OpenAI** (GPT models)
2. Configure your API key in **Settings > Connectors**. WordPress checks connector credentials in this order: environment variable, PHP constant, then database setting.
3. The plugin automatically detects WordPress 7.0+ and skips manual SDK initialization.
4. The plugin does not store AI provider credentials. It only checks whether a text-generation provider is available before showing generation actions.

### Feature Detection

Before making any AI call, the plugin checks that text generation is supported by a configured provider. The same status is localized to the admin JavaScript so generation controls can be disabled until the site owner configures a provider.

```php
if ( ! wp_ai_client_prompt( 'test' )->is_supported_for_text_generation() ) {
    return new \WP_Error(
        'wttba_ai_not_supported',
        'No AI provider is configured for text generation.'
    );
}
```

This check is deterministic (no API call) and returns `false` if no provider supports text generation or if the `wp_ai_client_prevent_prompt` filter blocks it.

AI configuration URLs and unavailable-state messages are centralized in `AI_Provider_Status`, which points WordPress 7.0+ sites to **Settings > Connectors** and pre-7.0 sites to the legacy WP AI Client settings page.

## Pre-WordPress 7.0

For WordPress versions before 7.0, the plugin depends on the `wordpress/wp-ai-client` ^0.4 Composer package:

```bash
composer install
```

The plugin conditionally initializes the SDK only on pre-7.0:

```php
// In class-plugin.php
if ( version_compare( wp_get_wp_version(), '7.0', '<' ) ) {
    \WordPress\AI_Client\AI_Client::init();
}
```

The `wordpress/wp-ai-client` package also auto-detects WordPress 7.0 and disables its own SDK infrastructure to avoid conflicts.

## How Generation Works

The AI generation pipeline in `Post_Generator::call_ai()`:

### 1. Prompt Construction

A detailed prompt is built with:
- Target language name
- Original video title
- Optional writing persona/style instructions
- The full video transcript

### 2. JSON Schema

A schema is defined requiring the AI to return a structured JSON object:

```php
$schema = array(
    'type'       => 'object',
    'properties' => array(
        'title'   => array( 'type' => 'string', 'description' => 'The blog post title.' ),
        'content' => array( 'type' => 'string', 'description' => 'Blog post content in HTML format.' ),
    ),
    'required' => array( 'title', 'content' ),
);
```

### 3. Fluent API Call

```php
$result = wp_ai_client_prompt( $prompt )
    ->using_system_instruction( 'You are a professional blog writer...' )
    ->using_temperature( 0.7 )
    ->using_max_tokens( 8000 )
    ->using_model_preference( 'claude-sonnet-4-6', 'gemini-2.5-flash', 'gpt-4o-mini' )
    ->as_json_response( $schema )
    ->generate_text();
```

| Method | Purpose |
|--------|---------|
| `using_system_instruction()` | Sets the AI's role separately from the user prompt |
| `using_temperature( 0.7 )` | Controls creativity (0 = deterministic, 1 = creative) |
| `using_max_tokens( 8000 )` | Limits response length |
| `using_model_preference()` | Preferred models; falls back to whatever is configured |
| `as_json_response( $schema )` | Enforces structured JSON output |
| `generate_text()` | Executes the prompt and returns a string or `WP_Error` |

### 4. Response Parsing

The JSON string is decoded and validated:

```php
$parsed = json_decode( $result, true );
if ( ! is_array( $parsed ) || empty( $parsed['title'] ) || empty( $parsed['content'] ) ) {
    return new \WP_Error( 'wttba_ai_parse_error', '...' );
}
```

## Customization

### Controlling Access with `wp_ai_client_prevent_prompt`

WordPress 7.0 provides a filter to block AI prompts at a granular level:

```php
add_filter( 'wp_ai_client_prevent_prompt', function ( bool $prevent, $builder ): bool {
    // Example: only allow AI generation for administrators
    if ( ! current_user_can( 'manage_options' ) ) {
        return true;
    }
    return $prevent;
}, 10, 2 );
```

When prevented, `is_supported_for_text_generation()` returns `false` and `generate_text()` returns a `WP_Error`.

### Model Preferences

The `using_model_preference()` method specifies a preferred order of models. The AI Client uses the first available model from the list, falling back to whatever the site administrator has configured if none match. This is a preference, not a requirement.

## Error Handling

The plugin defines these AI-related error codes:

| Error Code | HTTP Status | Category | Description |
|------------|-------------|----------|-------------|
| `wttba_ai_client_missing` | 422 | `configuration` | `wp_ai_client_prompt()` function not available |
| `wttba_ai_not_supported` | 422 | `configuration` | No AI provider configured for text generation |
| `wttba_ai_parse_error` | 502 | `upstream` | AI response could not be parsed as valid JSON |

All errors are returned as `WP_Error` objects and enriched with HTTP status codes and error categories by the REST Controller before reaching the frontend. AI configuration errors also include a configuration URL and button label so the frontend can send users to the appropriate provider setup screen.

## Reference

- [Introducing the AI Client in WordPress 7.0](https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/)
- [Introducing the Connectors API in WordPress 7.0](https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/)
- [Trac ticket #64591](https://core.trac.wordpress.org/ticket/64591)
