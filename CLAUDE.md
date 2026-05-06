# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

CreatorStack AI is a WordPress plugin suite for creator content workflows. It converts YouTube videos and audio recordings/uploads into WordPress draft posts, and can generate narrated audio from post content when enabled. The public product name is CreatorStack AI; the internal PHP namespace, REST namespace, option names, and script handles still use the historical `WTTBA` / `wttba_` prefixes for backward compatibility.

## Build & Development Commands

```bash
npm install          # Install JS dependencies
npm run build        # Build JS/CSS assets (webpack via @wordpress/scripts)
npm run start        # Watch mode for development
npm run lint:js      # Lint JavaScript
npm run lint:css     # Lint stylesheets
composer install     # Install PHP dependencies (wp-ai-client SDK)
```

Build output goes to `build/` with entry points for the dashboard widget, admin workflows, editor panel, and settings screen.

## Architecture

**PHP (Backend) — `includes/`**

- `Plugin` (singleton) — bootstraps all components via WordPress hooks
- `Settings` — WP Settings API page under Settings > CreatorStack AI; stores YouTube credentials, default language, writing persona, post length, AI model preference, and feature toggles. Houses the `LANGUAGES` constant (whitelist of supported languages)
- `REST_Controller` — registers routes under `wttba/v1` for video listing, post preview/save, audio-to-post generation, post-to-audio generation, and AI connection tests. All generation routes require `edit_posts` capability. Enriches `WP_Error` responses with HTTP status codes and `error_category` for frontend error handling
- `Post_Generator` — orchestrates the generation pipeline via two public methods: `preview()` (fetch video → fetch transcript → call AI → return title + content) and `save_draft()` (receive title + content → create WordPress draft). Uses `wp_ai_client_prompt()` with JSON schema for structured output. Includes per-user rate limiting via transients
- `YouTube_API` — wraps YouTube Data API v3 calls
- `Transcript_Fetcher` — retrieves video transcripts
- `Dashboard_Widget` — adds the YouTube content widget to wp-admin dashboard when that feature is enabled
- `Admin_Videos_Page` — full admin page for browsing channel videos and recording/selecting audio for draft generation
- `Editor_Integration` — adds CreatorStack AI controls to the post editor

**JavaScript (Frontend) — `src/`**

- `src/dashboard-widget/` — React app for the dashboard widget
- `src/admin-videos/` — React app for YouTube and Audio to Post admin workflows
- `src/editor/` — editor sidebar panel for Audio to Post and Post to Audio
- `src/settings/` — settings-page JavaScript for connection tests and OAuth helpers
- `src/shared/` — shared modules: `api.js` (REST client with `previewPost`, `saveDraft`, `parseError`), `language-modal.js` (language picker), `preview-modal.js` (draft preview with Save/Regenerate), `error-notice.js` (dismissible error notices with categories), `warning-notice.js` (non-blocking warning notices), `languages.js` (language list)

Built with `@wordpress/scripts` (webpack). Config in `webpack.config.js`.

## Key Conventions

- **Namespace:** All PHP classes are in the `WTTBA` namespace
- **Constants prefix:** `WTTBA_` (e.g., `WTTBA_VERSION`, `WTTBA_PLUGIN_DIR`)
- **Options prefix:** `wttba_` (e.g., `wttba_youtube_api_key`, `wttba_default_language`)
- **REST namespace:** `wttba/v1`
- **Text domain:** `creatorstack-ai`
- **Post meta:** `_wttba_source_video_id` stores the source YouTube video ID on generated posts

## Fetching WordPress.org Documentation

When fetching pages from `make.wordpress.org` (or any WordPress.org site), append `?output_format=md` to the URL to get the page content in Markdown format. This drastically reduces token usage and provides clean, structured content.

## Dependencies

- **PHP (WordPress 7.0+):** The AI Client is built into Core. No extra plugin or Composer package needed. Provider plugins (Anthropic, Google, OpenAI) are configured via Settings > Connectors.
- **PHP (WordPress < 7.0):** `wordpress/wp-ai-client` ^0.4 — the AI abstraction layer loaded via Composer. The plugin detects the WP version and conditionally initializes the SDK.
- **JS:** `@wordpress/scripts` ^30 — build toolchain (webpack, Babel, ESLint, stylelint)

The plugin calls `wp_ai_client_prompt()` with fluent API methods: `using_system_instruction()`, `using_temperature()`, `using_max_tokens()`, `using_model_preference()`, `as_json_response()`, and `generate_text()`. Feature detection via `is_supported_for_text_generation()` checks provider availability before making API calls.
