# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WP Tube-to-Blog AI is a WordPress plugin that converts YouTube videos into blog post drafts using AI. It fetches video transcripts, sends them to an AI provider (Gemini, Claude, or Ollama) via the `wordpress/wp-ai-client` SDK, and creates WordPress draft posts with structured HTML content.

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
- `REST_Controller` — registers routes under `wttba/v1`: `GET /videos`, `GET /videos/{id}`, `POST /generate`. All routes require `edit_posts` capability
- `Post_Generator` — orchestrates the generation pipeline: fetch video → fetch transcript → call AI → create draft. Uses `wp_ai_client_prompt()` with JSON schema for structured output. Includes per-user rate limiting via transients
- `YouTube_API` — wraps YouTube Data API v3 calls
- `Transcript_Fetcher` — retrieves video transcripts
- `Dashboard_Widget` — adds the widget to wp-admin dashboard
- `Admin_Videos_Page` — full admin page for browsing all channel videos

**JavaScript (Frontend) — `src/`**

- `src/dashboard-widget/` — React app for the dashboard widget
- `src/admin-videos/` — React app for the full videos admin page
- `src/shared/` — shared modules: `api.js` (REST client), `language-modal.js` (language picker), `languages.js` (language list)

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

- **PHP:** `wordpress/wp-ai-client` ^0.4 — the AI abstraction layer. The plugin calls `wp_ai_client_prompt()` to generate content. Must be installed as a separate WP plugin or loaded via Composer.
- **JS:** `@wordpress/scripts` ^30 — build toolchain (webpack, Babel, ESLint, stylelint)
