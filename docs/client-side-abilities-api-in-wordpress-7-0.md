Title: Client-Side Abilities API in WordPress 7.0
Author: Jorge Costa
Published: March 24, 2026
Source: https://make.wordpress.org/core/2026/03/24/client-side-abilities-api-in-wordpress-7-0/

---

# Client-Side Abilities API in WordPress 7.0

WordPress 6.9 introduced the [**Abilities API**](https://make.wordpress.org/core/2025/11/10/abilities-api-in-wordpress-6-9/). The API provides a common interface that AI agents, workflow automation tools, and plugins can use to interact with WordPress. In WordPress 7.0 we continued that work and now provide a counterpart JavaScript API that can be used to implement client-side abilities like navigating, or inserting blocks. This work is fundamental to integrate with browser agents/extensions and [WebMCP](https://github.com/WordPress/ai/pull/224).

## Two packages

The client-side Abilities API is split into two packages:

* **`@wordpress/abilities`**: A pure state management package with no WordPress server dependencies. It provides the store, registration functions, querying, and execution logic. Use this when you only need the abilities store without loading server-registered abilities. This package could also be used in non-WordPress projects.
* **`@wordpress/core-abilities`**: The WordPress integration layer. When loaded, it automatically fetches all abilities and categories registered on the server via the REST API (`/wp-abilities/v1/`) and registers them in the `@wordpress/abilities` store with appropriate callbacks.

## Getting started

To use the Abilities API in your plugin, you need to enqueue the appropriate script module.

### When your plugin needs server-registered abilities

If your plugin needs access to abilities registered on the server (e.g., core abilities), enqueue `@wordpress/core-abilities`. This is the most common case:

```php
add_action( 'admin_enqueue_scripts', 'my_plugin_enqueue_abilities' );
function my_plugin_enqueue_abilities() {
    wp_enqueue_script_module( '@wordpress/core-abilities' );
}
```

This will load both `@wordpress/core-abilities` and its dependency `@wordpress/abilities`, and automatically fetch and register all server-side abilities.

### When your plugin only registers client-side abilities

If your plugin only needs to register and work with its own client-side abilities on a specific page, without needing server-registered abilities, you can enqueue just `@wordpress/abilities`:

```php
add_action( 'admin_enqueue_scripts', 'my_plugin_enqueue_abilities' );
function my_plugin_enqueue_abilities( $hook_suffix ) {
    if ( 'my-plugin-page' !== $hook_suffix ) {
        return;
    }
    wp_enqueue_script_module( '@wordpress/abilities' );
}
```

### Importing in JavaScript

Abilities API should be imported as a dynamic import, for example:

```js
const {
    registerAbility,
    registerAbilityCategory,
    getAbilities,
    executeAbility,
} = await import( '@wordpress/abilities' );
```

If your client code is also a script module relying on `@wordpress/scripts`, you can just use the following code like any other import:

```js
import {
    registerAbility,
    registerAbilityCategory,
    getAbilities,
    executeAbility,
} from '@wordpress/abilities';
```

## Registering abilities

### Register a category first

Abilities are organized into categories. Before registering an ability, its category must exist. Server-side categories are loaded automatically when `@wordpress/core-abilities` is enqueued. To register a client-side category:

```js
const { registerAbilityCategory } = await import( '@wordpress/abilities' );

registerAbilityCategory( 'my-plugin-actions', {
    label: 'My Plugin Actions',
    description: 'Actions provided by My Plugin',
} );
```

Category slugs must be lowercase alphanumeric with dashes only (e.g., `data-retrieval`, `user-management`).

### Register an ability

```js
const { registerAbility } = await import( '@wordpress/abilities' );

registerAbility( {
    name: 'my-plugin/navigate-to-settings',
    label: 'Navigate to Settings',
    description: 'Navigates to the plugin settings page',
    category: 'my-plugin-actions',
    callback: async () => {
        window.location.href = '/wp-admin/options-general.php?page=my-plugin';
        return { success: true };
    },
} );
```

#### Input and output schemas

Abilities should define JSON Schema (draft-04) for input validation and output validation:

```js
registerAbility( {
    name: 'my-plugin/create-item',
    label: 'Create Item',
    description: 'Creates a new item with the given title and content',
    category: 'my-plugin-actions',
    input_schema: {
        type: 'object',
        properties: {
            title: { type: 'string', description: 'The title of the item', minLength: 1 },
            content: { type: 'string', description: 'The content of the item' },
            status: { type: 'string', description: 'The publish status of the item', enum: [ 'draft', 'publish' ] },
        },
        required: [ 'title' ],
    },
    output_schema: {
        type: 'object',
        properties: {
            id: { type: 'number', description: 'The unique identifier of the created item' },
            title: { type: 'string', description: 'The title of the created item' },
        },
        required: [ 'id' ],
    },
    callback: async ( { title, content, status = 'draft' } ) => {
        // Create the item...
        return { id: 123, title };
    },
} );
```

When `executeAbility` is called, the input is validated against `input_schema` before execution and the output is validated against `output_schema` after execution. If validation fails, an error is thrown with the code `ability_invalid_input` or `ability_invalid_output`.

#### Permission callbacks

Abilities can include a `permissionCallback` that is checked before execution:

```js
registerAbility( {
    name: 'my-plugin/admin-action',
    label: 'Admin Action',
    description: 'An action only available to administrators',
    category: 'my-plugin-actions',
    permissionCallback: () => {
        return currentUserCan( 'manage_options' );
    },
    callback: async () => {
        // Only runs if permissionCallback returns true
        return { success: true };
    },
} );
```

If the permission callback returns `false`, an error with code `ability_permission_denied` is thrown.

## Querying abilities

### Direct function calls

```js
const {
    getAbilities,
    getAbility,
    getAbilityCategories,
    getAbilityCategory,
} = await import( '@wordpress/abilities' );

// Get all registered abilities
const abilities = getAbilities();

// Filter abilities by category
const dataAbilities = getAbilities( { category: 'data-retrieval' } );

// Get a specific ability by name
const ability = getAbility( 'my-plugin/create-item' );

// Get all categories
const categories = getAbilityCategories();

// Get a specific category
const category = getAbilityCategory( 'data-retrieval' );
```

### Using with React and `@wordpress/data`

The abilities store (`core/abilities`) integrates with `@wordpress/data`, so you can use `useSelect` for reactive queries in React components:

```js
import { useSelect } from '@wordpress/data';
import { store as abilitiesStore } from '@wordpress/abilities';

function AbilitiesList() {
    // Get all abilities reactively
    const abilities = useSelect(
        ( select ) => select( abilitiesStore ).getAbilities(),
        []
    );

    // Filter by category
    const dataAbilities = useSelect(
        ( select ) =>
            select( abilitiesStore ).getAbilities( {
                category: 'data-retrieval',
            } ),
        []
    );

    // abilities and dataAbilities update automatically when the store changes
}
```

## Executing abilities

Use `executeAbility` to run any registered ability, whether client-side or server-side:

```js
import { executeAbility } from '@wordpress/abilities';

try {
    const result = await executeAbility( 'my-plugin/create-item', {
        title: 'New Item',
        content: 'Item content',
        status: 'draft',
    } );
    console.log( 'Created item:', result.id );
} catch ( error ) {
    switch ( error.code ) {
        case 'ability_permission_denied':
            console.error( 'You do not have permission to run this ability.' );
            break;
        case 'ability_invalid_input':
            console.error( 'Invalid input:', error.message );
            break;
        case 'ability_invalid_output':
            console.error( 'Unexpected output:', error.message );
            break;
        default:
            console.error( 'Execution failed:', error.message );
    }
}
```

For server-side abilities (those registered via PHP and loaded by `@wordpress/core-abilities`), execution is handled automatically via the REST API. The HTTP method used depends on the ability's annotations:

* **`readonly: true`**: uses `GET`
* **`destructive: true` + `idempotent: true`**: uses `DELETE`
* **All other cases**: uses `POST`

## Annotations

Abilities support metadata annotations that describe their behavior:

```js
registerAbility( {
    name: 'my-plugin/get-stats',
    label: 'Get Stats',
    description: 'Returns plugin statistics',
    category: 'my-plugin-actions',
    callback: async () => {
        return { views: 100 };
    },
    meta: {
        annotations: {
            readonly: true,
        },
    },
} );
```

Available annotations:

| Annotation | Type | Description |
|---|---|---|
| `readonly` | `boolean` | The ability only reads data, does not modify state |
| `destructive` | `boolean` | The ability performs destructive operations |
| `idempotent` | `boolean` | The ability can be called multiple times with the same result |

## Unregistering

Client-registered abilities and categories can be removed:

```js
const { unregisterAbility, unregisterAbilityCategory } = await import( '@wordpress/abilities' );

unregisterAbility( 'my-plugin/navigate-to-settings' );
unregisterAbilityCategory( 'my-plugin-actions' );
```

## Server-side abilities

Abilities registered on the server via the PHP API (`wp_register_ability()`, `wp_register_ability_category()`) are automatically made available on the client when `@wordpress/core-abilities` is loaded. WordPress core enqueues `@wordpress/core-abilities` on all admin pages, so server abilities are available by default in the admin.

Plugins that register server-side abilities do not need any additional client-side setup. The abilities will be fetched from the REST API and registered in the client store automatically.
