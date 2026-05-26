=== CreatorStack AI ===
Contributors: fellyph
Tags: ai, content, youtube, audio, posts
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn YouTube videos and audio recordings into WordPress draft posts, then generate narrated audio from posts.

== Description ==

CreatorStack AI helps creator teams turn source material into WordPress content without leaving wp-admin.

The plugin can browse YouTube channel videos, fetch transcripts, generate draft posts with the WordPress AI Client, turn uploaded or recorded audio into drafts, and optionally generate narrated audio from existing posts.

AI provider credentials are managed by WordPress through the Connectors screen. CreatorStack AI checks provider capabilities before showing generation actions and does not store AI provider API keys.

= Features =

* YouTube to Post: browse channel videos, extract transcripts, and generate WordPress drafts.
* Audio to Post: record audio in wp-admin or select existing audio and generate drafts from spoken content.
* Post to Audio: generate narrated audio from existing posts when the configured provider supports text-to-speech.
* Feature controls: enable or disable each workflow from the settings screen.
* Language controls: generate posts in the source language or translate into a selected target language.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/creatorstack-ai` directory, or install the plugin through the WordPress plugins screen.
2. Activate CreatorStack AI through the Plugins screen in WordPress.
3. Open Settings > CreatorStack AI.
4. Configure your YouTube API key and channel ID.
5. Configure an AI provider through Settings > Connectors.

== Frequently Asked Questions ==

= Does CreatorStack AI store AI provider API keys? =

No. AI provider credentials are managed by WordPress Connectors and provider plugins.

= Which WordPress version is required? =

CreatorStack AI requires WordPress 7.0 or newer because it uses the WordPress AI Client and Connectors APIs.

= Which users can generate posts? =

Generation routes require the `edit_posts` capability. Settings require the `manage_options` capability.

== Changelog ==

= 1.0.0 =

* Initial release.
