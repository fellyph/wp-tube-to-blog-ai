# CreatorStack AI abilities

CreatorStack AI exposes its existing workflows through the WordPress Abilities API
under the `creatorstack-ai` category and namespace. The plugin requires WordPress
7.0 or newer. Registration uses the Core category and ability initialization hooks;
on environments without those hooks the integration stays inactive.

All 11 abilities are discoverable through the authenticated `/wp-json/wp-abilities/v1/abilities`
endpoint (`category=creatorstack-ai`). Abilities remain registered when a feature is
disabled or a provider is missing so clients can discover the workflow and receive
its configuration error. Start with `creatorstack-ai/get-capabilities` to inspect
feature flags, provider support, media limits and thumbnail styles.

| Ability (prefix: `creatorstack-ai/`) | Input | Behavior / permission |
| --- | --- | --- |
| `get-capabilities` | None | Inspect configuration and limits; `edit_posts`. |
| `test-ai-connection` | None | Small AI generation and diagnostic logging; `manage_options`. |
| `list-videos` | Optional `page_token`, `max_results` (1–50, default 5) | List configured channel videos; `edit_posts`. Returns `items`, `nextPageToken`, `totalResults`. |
| `get-video` | `id` (YouTube video ID) | Retrieve video details; `edit_posts`. |
| `preview-youtube-post` | `video_id`; optional `language`, `persona`, `manual_transcript` | Generate title and HTML without creating a post; `edit_posts`. |
| `save-youtube-draft` | `video_id`, `title`, `content`; optional `ai_metadata` | Create a draft with source attribution, embed and optional thumbnail; `edit_posts`. |
| `preview-audio-post` | `post_id`, `attachment_id`; optional `language`, `persona` | Generate title and HTML; edit permission on both post and attachment. Also reparents the attachment and updates source metadata and diagnostics. |
| `create-audio-draft` | `attachment_id`; optional `language`, `persona` | Generate and save a draft, reparenting the attachment; `edit_posts` and edit permission on the attachment. |
| `generate-post-audio` | `id` (post ID); optional `voice`, `overwrite_block` (default true) | Create narration media and insert/replace an audio block; edit permission on the post and `upload_files`. |
| `preview-post-thumbnail` | `id` (post ID), `style`; optional `secondary_style`, `author_attachment_id`, `reference_attachment_ids` | Generate a temporary preview; edit permission on the post and `upload_files`. Reference attachments must be editable. |
| `save-post-thumbnail` | `id` (post ID), `preview_id` | Save media and replace the featured image; edit permission on the post and `upload_files`. Preview ownership, post association and expiry are checked. |

Only the three informational abilities are annotated `readonly` and `idempotent`.
Generation calls may incur provider charges, consume rate limits and write logs or
transients, even when named “preview”. Mutations are not idempotent: do not retry
blindly after an uncertain response. Audio reparenting, post content updates and
featured-image replacement are marked `destructive` to communicate changes to
existing data. No ability publishes a draft, but narration and thumbnail operations
can modify an existing published post.

## PHP usage

Execute after plugins have loaded, with the intended current user. Core validates
input and checks the permission callback for PHP execution as well as REST execution.

```php
$ability = wp_get_ability( 'creatorstack-ai/preview-youtube-post' );
$result = $ability->execute( array(
    'video_id' => 'dQw4w9WgXcQ',
    'language' => 'pt-br',
) );

if ( is_wp_error( $result ) ) {
    // Handle the error code and data (including status and error_category).
    return;
}
// Review $result['title'] and $result['content'] before calling save-youtube-draft.
```

Use a language key from `Settings::LANGUAGES`; omit `language` to use the saved
default. Abilities accept the same parameter names as the plugin REST routes;
unknown top-level fields are rejected. Draft output includes `post_id`, `edit_url`
and any warnings. Generation output may also include `ai_metadata`.

## REST usage

Use standard WordPress REST authentication: cookie authentication with a REST nonce
in wp-admin, or an Application Password over HTTPS for an external client. Discovery
does not grant permission to execute an ability.

Read-only abilities use GET:

```text
GET /wp-json/wp-abilities/v1/abilities/creatorstack-ai/get-capabilities/run
GET /wp-json/wp-abilities/v1/abilities/creatorstack-ai/list-videos/run?input[max_results]=5
```

All other abilities use POST, with arguments nested in the JSON `input` property:

```text
POST /wp-json/wp-abilities/v1/abilities/creatorstack-ai/save-youtube-draft/run
Content-Type: application/json

{
  "input": {
    "video_id": "dQw4w9WgXcQ",
    "title": "Reviewed draft title",
    "content": "<!-- wp:paragraph --><p>Reviewed content.</p><!-- /wp:paragraph -->"
  }
}
```

For `test-ai-connection`, send an empty JSON object. Successful responses contain
the workflow data directly. Core controls the ability endpoint's success status;
clients should use the returned IDs rather than assume the underlying plugin
route's 201 status is preserved. Workflow failures retain their `WP_Error` codes,
HTTP status and error-category data.

The adapter dispatches only fixed plugin routes using `rest_do_request()` as the
current user. It preserves REST sanitization, defaults, permissions, feature gates,
rate limits and generation behavior. The existing wp-admin UI continues to use
its REST client; no JavaScript dependency is required to expose these abilities.

## Verification

```sh
npx playwright test tests/e2e/abilities.spec.ts --reporter=line
```

The tests use a disposable WordPress Playground and cover discovery, PHP/REST
execution, schema validation, unauthorized users, ownership and upload permissions,
feature errors, expired previews, and sanitized draft creation. Draft tests use
cached fixture video data and do not call paid AI providers.
