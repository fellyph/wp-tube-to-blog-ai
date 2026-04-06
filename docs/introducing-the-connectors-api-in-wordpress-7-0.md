Title: Introducing the Connectors API in WordPress 7.0
Author: Greg Ziółkowski
Published: March 18, 2026
Last modified: March 31, 2026
Source: https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/

---

# Introducing the Connectors API in WordPress 7.0

WordPress 7.0 introduces the **Connectors API** — a new framework for registering and managing connections to external services. The initial focus is on AI providers, giving WordPress a standardized way to handle API key management, provider discovery, and admin UI for configuring AI services.

This post walks through what the Connectors API does, how it works under the hood, and what plugin developers need to know.

## Table of Contents

* [What is a connector?](#what-is-a-connector)
* [How AI providers are auto-discovered](#how-ai-providers-are-auto-discovered)
* [The Settings > Connectors admin screen](#the-settings--connectors-admin-screen)
* [Authentication and API key management](#authentication-and-api-key-management)
* [Public API functions](#public-api-functions)
* [Overriding connector metadata](#overriding-connector-metadata)
* [The initialization lifecycle](#the-initialization-lifecycle)
* [Looking ahead](#looking-ahead)

## What is a connector?

A connector represents a connection to an external service. Each connector carries standardized metadata — a display name, description, logo, authentication configuration, and an optional association with a WordPress.org plugin. The system currently focuses on providers that authenticate with an API key, but the architecture is designed to support additional connector types in future releases.

WordPress 7.0 comes with three featured connectors—Anthropic, Google, and OpenAI—accessible from the new **Settings > Connectors** screen, making installation seamless.

Each connector is stored as an associative array with the following shape:

```php
array(
    'name'           => 'Anthropic',
    'description'    => 'Text generation with Claude.',
    'logo_url'       => 'https://example.com/anthropic-logo.svg',
    'type'           => 'ai_provider',
    'authentication' => array(
        'method'          => 'api_key',
        'credentials_url' => 'https://platform.claude.com/settings/keys',
        'setting_name'    => 'connectors_ai_anthropic_api_key',
    ),
    'plugin'         => array(
        'slug' => 'ai-provider-for-anthropic',
    ),
)
```

## How AI providers are auto-discovered

If you're building an AI provider plugin that integrates with the WP AI Client, you don't need to register a connector manually. The Connectors API automatically discovers providers from the WP AI Client's default registry and creates connectors with the correct metadata.

Here's what happens during initialization:

1. Built-in connectors (Anthropic, Google, OpenAI) are registered with hardcoded defaults.
2. The system queries the `AiClient::defaultRegistry()` for all registered providers.
3. For each provider, metadata (name, description, logo, authentication method) is merged on top of the defaults, with provider registry values taking precedence.
4. The `wp_connectors_init` action fires so plugins can override metadata or register additional connectors.

**In short:** if your AI provider plugin registers with the WP AI Client, the connector is created for you. No additional code is needed.

## The Settings > Connectors admin screen

Registered connectors appear on a new **Settings > Connectors** admin screen. The screen renders each connector as a card, and the registry data drives what's displayed:

* **`name`**, **`description`**, and **`logo_url`** are shown on the card.
* **`plugin.slug`** enables install/activate controls — the screen checks whether the associated plugin is installed and active, and shows the appropriate action button.
* **`authentication.credentials_url`** is rendered as a link directing users to the provider's site to obtain API credentials.
* For **`api_key`** connectors, the screen shows the current key source (environment variable, PHP constant, or database) and connection status.

Connectors with other authentication methods are stored in the PHP registry and exposed via the script module data, but currently require a client-side JavaScript registration for custom frontend UI.

## Authentication and API key management

Connectors support two authentication methods:

* **`api_key`** — Requires an API key, which can be provided via environment variable, PHP constant, or the database (checked in that order).
* **`none`** — No authentication required.

The authentication method (`api_key` or `none`) is determined by the authentication metadata registered with the connector. For providers using `api_key`, a database setting name is automatically generated using the pattern `connectors_{$provider_type}_{$provider_id}_api_key`. It's also possible to set a custom name using `setting_name` property. API keys stored in the database are not encrypted but are masked in the user interface. Encryption is being explored in a follow-up ticket: [#64789](https://core.trac.wordpress.org/ticket/64789).

For AI providers, there is a specific naming convention in place for environment variables and PHP constants: `{PROVIDER_ID}_API_KEY` (e.g., the `anthropic` provider maps to `ANTHROPIC_API_KEY`). For other types of providers, an environment variable (`env_var_name`) and a PHP constant (`constant_name`) can be optionally set to any value.

### API key source priority

For `api_key` connectors, the system looks for a setting value in this order:

1. **Environment variable** — e.g., `ANTHROPIC_API_KEY`
2. **PHP constant** — e.g., `define( 'ANTHROPIC_API_KEY', 'sk-...' );`
3. **Database** — stored through the admin screen, e.g. `connectors_ai_anthropic_api_key` setting

## Public API functions

The Connectors API provides three public functions for querying the registry. These are available after `init`.

### `wp_is_connector_registered()`

Checks if a connector is registered:

```php
if ( wp_is_connector_registered( 'anthropic' ) ) {
    // The Anthropic connector is available.
}
```

### `wp_get_connector()`

Retrieves a single connector's data:

```php
$connector = wp_get_connector( 'anthropic' );
if ( $connector ) {
    echo $connector['name']; // 'Anthropic'
}
```

Returns an associative array with keys: `name`, `description`, `type`, `authentication`, and optionally `logo_url` and `plugin`. Returns `null` if the connector is not registered.

### `wp_get_connectors()`

Retrieves all registered connectors, keyed by connector ID:

```php
$connectors = wp_get_connectors();
foreach ( $connectors as $id => $connector ) {
    printf( '%s: %s', $connector['name'], $connector['description'] );
}
```

## Overriding connector metadata

The `wp_connectors_init` action fires after all built-in and auto-discovered connectors have been registered. Plugins can use this hook to override metadata on existing connectors.

Since the registry rejects duplicate IDs, overriding requires an unregister, modify, register sequence:

```php
add_action( 'wp_connectors_init', function ( WP_Connector_Registry $registry ) {
    if ( $registry->is_registered( 'anthropic' ) ) {
        $connector = $registry->unregister( 'anthropic' );
        $connector['description'] = __( 'Custom description for Anthropic.', 'my-plugin' );
        $registry->register( 'anthropic', $connector );
    }
} );
```

Key points about the override pattern:

* Always check `is_registered()` before calling `unregister()` — calling `unregister()` on a non-existent connector triggers a `_doing_it_wrong()` notice.
* `unregister()` returns the connector data, which you can modify and pass back to `register()`.
* Connector IDs must match the pattern `/^[a-z0-9_-]+$/` (lowercase alphanumeric, underscores, and hyphens only).

### Registry methods

Within the `wp_connectors_init` callback, the `WP_Connector_Registry` instance provides these methods:

| Method | Description |
|---|---|
| `register( $id, $args )` | Register a new connector. Returns the connector data or `null` on failure. |
| `unregister( $id )` | Remove a connector and return its data. Returns `null` if not found. |
| `is_registered( $id )` | Check if a connector exists. |
| `get_registered( $id )` | Retrieve a single connector's data. |
| `get_all_registered()` | Retrieve all registered connectors. |

Outside of the `wp_connectors_init` callback, use the public API functions (`wp_get_connector()`, `wp_get_connectors()`, `wp_is_connector_registered()`) instead of accessing the registry directly.

## The initialization lifecycle

Understanding the initialization sequence helps when deciding where to hook in:

During the `init` action, `_wp_connectors_init()` runs and:

* Creates the `WP_Connector_Registry` singleton.
* Registers built-in connectors (Anthropic, Google, OpenAI) with hardcoded defaults.
* Auto-discovers providers from the WP AI Client registry and merges their metadata on top of defaults.
* Fires the **`wp_connectors_init`** action — this is where plugins override metadata or register additional connectors.

The `wp_connectors_init` action is the only supported entry point for modifying the registry. Attempting to set the registry instance outside of `init` triggers a `_doing_it_wrong()` notice.

## Looking ahead

The Connectors API in WordPress 7.0 was optimized for AI providers, but the underlying architecture is designed to grow. Currently, only connectors with `api_key` authentication receive the full admin UI treatment. The PHP registry already accepts any connector type — what's missing is the frontend integration for connectors with different authentication mechanisms.

Future releases are expected to:

* Expand support for additional authentication methods beyond `api_key` and `none`.
* Offer more built-in UI integrations beyond `api_key`.
* Provide a client-side JavaScript registration API for custom connector UI.
