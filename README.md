# CreatorStack AI

Turn creator source material into WordPress content with AI.

[![License: GPL v2 or later](https://img.shields.io/badge/License-GPL%20v2%20or%20later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## 📖 Overview

The **CreatorStack AI** plugin is a WordPress content workflow suite for creators. It connects YouTube content, recorded audio, uploaded audio, and post narration workflows to WordPress AI providers so teams can generate draft posts and audio versions without leaving wp-admin.

## ✨ Features

*   **YouTube to Post:** Browse channel videos, extract transcripts, and generate WordPress drafts.
*   **Audio to Post:** Record audio in wp-admin or the post editor, select existing audio, and generate drafts from spoken content.
*   **Post to Audio:** Optionally generate narrated audio from existing posts.
*   **Feature Controls:** Enable or disable YouTube to Post, Audio to Post, and Post to Audio from settings.
*   **AI Flexibility:** Leverage the **WordPress AI Client** and **Connectors API** to use whichever compatible provider the site owner configures.
*   **Global Reach:** Generate posts in the source language or translate them into a selected target language.

## 🛠️ Requirements

*   **WordPress:** 6.7 or higher (7.0+ recommended for built-in AI Client support).
*   **PHP:** 8.1 or higher.
*   **YouTube connector:** The included **CreatorStack AI YouTube Connector** plugin activated, a YouTube Data API key managed through WordPress Connectors, and a YouTube Channel ID for video workflows.
*   **AI Provider:** A compatible provider configured through the WordPress AI Client and Connectors API. Audio workflows require provider support for audio input or text-to-speech.
*   **WordPress AI Client:**
    *   **WordPress 7.0+:** The AI Client and Connectors API are built into Core. Configure provider plugins and credentials via **Settings > Connectors**.
    *   **WordPress < 7.0:** Requires the `wordpress/wp-ai-client` Composer package (installed automatically via `composer install`).

## 🚀 Installation

### Development Setup

1.  Clone the repository into your WordPress plugins directory:
    ```bash
    cd wp-content/plugins
    git clone https://github.com/fellyph/creatorstack-ai.git
    ```

2.  Install PHP dependencies using Composer:
    ```bash
    composer install
    ```

3.  Install JavaScript dependencies and build the assets:
    ```bash
    npm install
    npm run build
    ```

4.  Activate the plugin through the 'Plugins' menu in WordPress.

## 📦 Releases

GitHub Actions builds release zips automatically when a version tag is pushed:

```bash
git tag v1.0.0
git push origin v1.0.0
```

The release workflow uses the tag version as the source of truth. For `v1.0.0`, it syncs the plugin header, `WTTBA_VERSION`, and `package.json` to `1.0.0`, validates PHP and JavaScript, builds production assets, packages `creatorstack-ai.zip`, uploads it as a workflow artifact, and attaches it to the GitHub Release.

Release notes are generated automatically from merged pull requests. GitHub uses `.github/release.yml` to group changes by labels such as `feature`, `bug`, `documentation`, `ci`, `tests`, and `dependencies`.

To update the version locally before tagging, run:

```bash
npm run version:set -- 1.0.0
```

## 🧪 Testing

Run the lightweight Node tests:

```bash
npm run test:node
```

Run the full test suite:

```bash
npm test
```

## 🧪 WordPress Playground

Launch a disposable WordPress Playground instance with CreatorStack AI mounted and activated:

```bash
npm run playground:start
```

The command builds the assets, starts WordPress nightly on PHP 8.3, logs in as admin, and sets the landing page to `/wp-admin/admin.php?page=wttba-videos`.

Run the same Blueprint as a headless smoke check:

```bash
npm run playground:check
```

Start with Xdebug enabled:

```bash
npm run playground:debug
```

Generate a browser Playground URL that installs the latest GitHub release zip:

```bash
npm run playground:url
```

Generate a browser Playground URL that installs directly from the GitHub source branch:

```bash
npm run playground:url:source
```

The release URL is recommended for demos because it installs the built plugin zip with compiled assets. The source URL is useful for PHP smoke testing, but GitHub source installs do not run `npm run build` inside Playground.

## ⚙️ Configuration

1.  Activate **CreatorStack AI YouTube Connector** from the Plugins screen.
2.  Navigate to **Settings > Connectors** in your WordPress dashboard and configure the **YouTube** connector with a YouTube Data API key. You can also provide the key with the `YOUTUBE_DATA_API_KEY` environment variable or PHP constant.
3.  Navigate to **Settings > CreatorStack AI** and enter your **YouTube Channel ID**.
4.  Configure your preferred AI Provider:
    *   **WordPress 7.0+:** Go to **Settings > Connectors** to install, activate, and configure an AI provider connector.
    *   **WordPress < 7.0:** Configure the provider within the WordPress AI Client plugin settings.
    *   Connector API keys can be supplied by environment variable, PHP constant, or the database; WordPress checks them in that order.
5.  If you want official caption downloads, add the Google OAuth Web application credentials in **Settings > CreatorStack AI** and connect YouTube.
6.  Select your default output language and optional writing persona.
7.  Save the settings.

## 📝 Usage

1.  Go to the **WordPress Dashboard** (`wp-admin/index.php`).
2.  Use the **CreatorStack: YouTube Content** widget or the **CreatorStack** admin menu to generate drafts from videos.
3.  Open **Audio to Post** to record or select audio and generate a draft.
4.  Use the post editor panel to generate a draft from audio while editing, or enable **Post to Audio** to generate narrated audio from post content.

## User Documentation

User guides are available in:

*   [English](docs/user-guide-en.md)
*   [Portuguese](docs/user-guide-pt.md)
*   [Spanish](docs/user-guide-es.md)

Implementation article:

*   [Implementing CreatorStack AI With WordPress AI Client And Connectors](docs/implementing-creatorstack-ai-with-ai-connectors.md)

## 🔒 Security

*   **Backend Prompting:** All AI prompts and system instructions are handled securely on the backend (PHP).
*   **Access Control:** Settings are restricted to users with `manage_options` capability (Admins), and generation is restricted to `edit_posts` (Authors/Editors/Admins).
*   **Provider Credentials:** AI provider keys are managed by WordPress Connectors, not by this plugin.
*   **Data Protection:** YouTube settings are stored with the WordPress Options API. Connector API keys stored in the database are masked by WordPress, while environment variables or PHP constants can be used to avoid database storage.

## 📄 License

This project is licensed under the GPL-2.0-or-later License.
