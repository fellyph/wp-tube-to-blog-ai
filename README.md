# WP Tube-to-Blog AI

Convert YouTube videos into high-quality WordPress blog post drafts automatically using AI.

[![License: GPL v2 or later](https://img.shields.io/badge/License-GPL%20v2%20or%20later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## 📖 Overview

The **WP Tube-to-Blog AI** plugin bridges the gap between video content creation and written content distribution. It allows WordPress administrators to connect their YouTube channel directly to their WordPress dashboard. Through a dedicated dashboard widget, users can browse their recent videos, extract the video transcripts, and utilize the WordPress AI layer to automatically generate high-quality, localized blog posts as drafts.

## ✨ Features

*   **Automate Content Creation:** Convert YouTube videos into readable, well-formatted blog posts with one click.
*   **Centralized Workflow:** Manage your video-to-text pipeline directly from the WordPress dashboard.
*   **AI Flexibility:** Leverage the **WordPress AI Client** and **Connectors API** to use whichever text-generation provider the site owner configures.
*   **Global Reach:** Automated internationalization (i18n) by generating blog posts in the video's native language or translating them into a selected target language.
*   **SEO Friendly:** Generates cohesive blog posts including Title, Headings, and Paragraphs.
*   **Visual Integration:** Automatically sets the YouTube thumbnail as the featured image and embeds the original video in the draft.

## 🛠️ Requirements

*   **WordPress:** 6.7 or higher (7.0+ recommended for built-in AI Client support).
*   **PHP:** 8.1 or higher.
*   **YouTube Data API Key:** Required to fetch video information and channel data.
*   **AI Provider:** A text-generation provider configured through the WordPress AI Client and Connectors API.
*   **WordPress AI Client:**
    *   **WordPress 7.0+:** The AI Client and Connectors API are built into Core. Configure provider plugins and credentials via **Settings > Connectors**.
    *   **WordPress < 7.0:** Requires the `wordpress/wp-ai-client` Composer package (installed automatically via `composer install`).

## 🚀 Installation

### Development Setup

1.  Clone the repository into your WordPress plugins directory:
    ```bash
    cd wp-content/plugins
    git clone https://github.com/your-repo/wp-tube-to-blog-ai.git
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

## ⚙️ Configuration

1.  Navigate to **Settings > Tube-to-Blog AI** in your WordPress dashboard.
2.  Enter your **YouTube Data API Key** and **Channel ID**.
3.  Configure your preferred AI Provider:
    *   **WordPress 7.0+:** Go to **Settings > Connectors** to install, activate, and configure an AI provider connector.
    *   **WordPress < 7.0:** Configure the provider within the WordPress AI Client plugin settings.
    *   Connector API keys can be supplied by environment variable, PHP constant, or the database; WordPress checks them in that order.
4.  Select your default output language and optional writing persona.
5.  Save the settings.

## 📝 Usage

1.  Go to the **WordPress Dashboard** (`wp-admin/index.php`).
2.  Locate the **YouTube to Blog AI** widget.
3.  Click **"Generate Post"** on any of your recent videos.
4.  (Optional) Select a target language in the appearing modal.
5.  Once processing is complete, a success message will appear with a link to edit your new **Draft Post**.

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
