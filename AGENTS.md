# AGENTS.md

This file provides guidance to Codex (Codex.ai/code) when working with code in this repository.

## Project Overview

WP Tube-to-Blog AI is a WordPress plugin that converts YouTube videos into blog post drafts using AI. It fetches video transcripts, sends them to an AI provider (Gemini, Codex, or Ollama) via the `wordpress/wp-ai-client` SDK, and creates WordPress draft posts with structured HTML content.

## Build & Development Commands

```bash
npm install          # Install JS dependencies
npm run build        # Build JS/CSS assets (webpack via @wordpress/scripts)
npm run start        # Watch mode for development
npm run lint:js      # Lint JavaScript
npm run lint:css     # Lint stylesheets
composer install     # Install PHP dependencies (wp-ai-client SDK)
```

Build output goes to `build/` with two entry points: `dashboard-widget` and `admin-videos`.

## Architecture

**PHP (Backend) — `includes/`**

- `Plugin` (singleton) — bootstraps all components via WordPress hooks
- `Settings` — WP Settings API page under Settings > Tube-to-Blog AI; stores YouTube API key, channel ID, default language. Houses the `LANGUAGES` constant (whitelist of supported languages)
- `REST_Controller` — registers routes under `wttba/v1`: `GET /videos`, `GET /videos/{id}`, `POST /preview`, `POST /save-draft`. All routes require `edit_posts` capability. Enriches `WP_Error` responses with HTTP status codes and `error_category` for frontend error handling
- `Post_Generator` — orchestrates the generation pipeline via two public methods: `preview()` (fetch video → fetch transcript → call AI → return title + content) and `save_draft()` (receive title + content → create WordPress draft). Uses `wp_ai_client_prompt()` with JSON schema for structured output. Includes per-user rate limiting via transients
- `YouTube_API` — wraps YouTube Data API v3 calls
- `Transcript_Fetcher` — retrieves video transcripts
- `Dashboard_Widget` — adds the widget to wp-admin dashboard
- `Admin_Videos_Page` — full admin page for browsing all channel videos

**JavaScript (Frontend) — `src/`**

- `src/dashboard-widget/` — React app for the dashboard widget
- `src/admin-videos/` — React app for the full videos admin page
- `src/shared/` — shared modules: `api.js` (REST client with `previewPost`, `saveDraft`, `parseError`), `language-modal.js` (language picker), `preview-modal.js` (draft preview with Save/Regenerate), `error-notice.js` (dismissible error notices with categories), `warning-notice.js` (non-blocking warning notices), `languages.js` (language list)

Built with `@wordpress/scripts` (webpack). Config in `webpack.config.js`.

## Key Conventions

- **Namespace:** All PHP classes are in the `WTTBA` namespace
- **Constants prefix:** `WTTBA_` (e.g., `WTTBA_VERSION`, `WTTBA_PLUGIN_DIR`)
- **Options prefix:** `wttba_` (e.g., `wttba_youtube_api_key`, `wttba_default_language`)
- **REST namespace:** `wttba/v1`
- **Text domain:** `wp-tube-to-blog-ai`
- **Post meta:** `_wttba_source_video_id` stores the source YouTube video ID on generated posts

## Fetching WordPress.org Documentation

When fetching pages from `make.wordpress.org` (or any WordPress.org site), append `?output_format=md` to the URL to get the page content in Markdown format. This drastically reduces token usage and provides clean, structured content.

## Dependencies

- **PHP (WordPress 7.0+):** The AI Client is built into Core. No extra plugin or Composer package needed. Provider plugins (Anthropic, Google, OpenAI) are configured via Settings > Connectors.
- **PHP (WordPress < 7.0):** `wordpress/wp-ai-client` ^0.4 — the AI abstraction layer loaded via Composer. The plugin detects the WP version and conditionally initializes the SDK.
- **JS:** `@wordpress/scripts` ^30 — build toolchain (webpack, Babel, ESLint, stylelint)

The plugin calls `wp_ai_client_prompt()` with fluent API methods: `using_system_instruction()`, `using_temperature()`, `using_max_tokens()`, `using_model_preference()`, `as_json_response()`, and `generate_text()`. Feature detection via `is_supported_for_text_generation()` checks provider availability before making API calls.
