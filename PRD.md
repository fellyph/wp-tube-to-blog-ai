# Product Requirements Document (PRD)
**Product Name:** WP Tube-to-Blog AI 
**Document Version:** 1.0
**Target Platform:** WordPress

## 1. Executive Summary
The WP Tube-to-Blog AI plugin bridges the gap between video content creation and written content distribution. It allows WordPress administrators to connect their YouTube channel directly to their WordPress dashboard. Through a dedicated dashboard widget, users can browse their recent videos, extract the video transcripts, and utilize the new WordPress AI layer/connector to automatically generate high-quality, localized blog posts as drafts. 

## 2. Goals & Objectives
*   **Automate Content Creation:** Convert YouTube videos into readable, well-formatted blog posts with one click.
*   **Centralized Workflow:** Keep the user inside the WordPress dashboard to manage their video-to-text pipeline.
*   **AI Flexibility:** Leverage the emerging WordPress AI Layer to allow users to seamlessly switch between cloud AI (Gemini, Claude) and local privacy-first models (Ollama).
*   **Global Reach:** Provide automated internationalization (i18n) by allowing users to generate blog posts in the video's native language or translate them into a selected target language.

## 3. User Stories
*   **As an Administrator**, I want a settings page to securely input my YouTube API key and my preferred AI API keys (Gemini, Claude) or local Ollama endpoint.
*   **As an Administrator**, I want to select which AI model the plugin should use from a dropdown menu.
*   **As an Author**, I want to see a widget on my WordPress dashboard showing my latest YouTube videos.
*   **As an Author**, I want to click a "See More" button to view a complete, paginated list of my channel's videos.
*   **As an Author**, I want to click "Generate Post" on a video, have the transcript downloaded, and have an AI draft a blog post for me.
*   **As an Author**, I want the option to choose the output language of the generated blog post, regardless of the original video language.

## 4. Functional Requirements

### 4.1 Settings & Configuration (Backend)
*   **YouTube Integration:** Input field for YouTube Data API Key and Channel ID.
*   **AI Provider Configuration:**
    *   Input for Google Gemini API Key.
    *   Input for Anthropic Claude API Key.
    *   Input field for local Ollama Endpoint URL (e.g., `http://localhost:11434`).
*   **Model Selection:** A dynamic dropdown that allows the user to select the specific model based on the chosen provider (e.g., `gemini-1.5-pro`, `claude-3-opus`, `llama3`).
*   **Default Language Settings:** Dropdown to select the default output language for generated posts.

### 4.2 Dashboard Widget
*   **Location:** WordPress Admin Dashboard (`wp-admin/index.php`).
*   **Display:** List the 5 most recent videos from the connected YouTube channel (Thumbnail, Title, Date).
*   **Action:** A "Generate Post" button next to each video.
*   **"See More" Link:** A link that opens a dedicated admin page (or modal) displaying all channel videos with pagination.

### 4.3 Video Processing & Transcript Extraction
*   When a user clicks "Generate Post", the plugin must fetch the transcript of the video.
*   *Note to Developer:* Use the YouTube API or a reliable PHP transcription-fetching library, as native API transcript access can be restricted based on OAuth/Captions availability.

### 4.4 AI Processing & WP AI Layer Integration
*   The plugin must utilize the **WordPress AI API connector standards** to ensure smooth switching between models.
*   **Language Selection Modal:** Before generating, prompt the user: "Generate in original language or translate to: [Dropdown of languages]".
*   The transcript and language preference will be passed to the AI model to generate a cohesive, SEO-friendly blog post (Title, Headings, Paragraphs).

### 4.5 Post Creation
*   The AI output must be automatically saved as a WordPress **Draft Post**.
*   The featured image of the post should optionally be set to the YouTube video thumbnail.
*   The original YouTube video should be embedded at the top or bottom of the draft.

## 5. Security & Architecture (Non-Functional Requirements)

### 5.1 Backend Prompt Protection
*   **Crucial:** All AI prompts (the system instructions that tell the AI *how* to format the blog post) **must reside securely on the backend (PHP)**. 
*   The frontend JavaScript should only send the Video ID and target language via AJAX/REST API. The backend must construct the final prompt. Users must never see or be able to manipulate the underlying system prompts via browser dev tools.

### 5.2 WordPress Best Practices
*   **Security:** Use WordPress Nonces for all AJAX/REST API requests to prevent CSRF attacks.
*   **Capabilities:** Restrict settings access to `manage_options` (Admins). Restrict generation capabilities to `edit_posts` (Authors/Editors/Admins).
*   **Data Storage:** Store API keys securely using the WordPress Options API. Do not log or expose API keys in frontend source code.
*   **Sanitization & Validation:** Strictly sanitize all inputs (API keys, settings) and escape all outputs when rendering the dashboard widget.

### 5.3 Internationalization (i18n)
*   **Plugin UI Translation:** All strings in the plugin UI (buttons, settings, labels) must be fully translatable using WordPress standard functions (`__(), _e()`) and a `.pot` file.
*   **Content Translation (AI):** The AI system prompt must dynamically include instructions to format the output in the user's selected language. (e.g., *"Analyze the following transcript and write a blog post in [Target Language]."*).

## 6. User Interface (UI) / User Experience (UX) Flow

1.  **Onboarding:** User installs plugin -> Goes to Settings -> Enters YouTube API Key, AI API Key, selects Model -> Saves.
2.  **Discovery:** User navigates to WP Dashboard -> Sees the new "YouTube to Blog" widget.
3.  **Initiation:** User clicks "Generate Post" on a specific video.
4.  **Configuration:** A lightweight modal appears: *"Select Output Language: [Match Transcript (Default)] or [Dropdown of languages]"*.
5.  **Processing:** User clicks "Confirm". A loading spinner indicates the transcript is downloading and AI is processing.
6.  **Completion:** A success message appears with a link: *"Post generated successfully! [Click here to edit Draft]"*.

## 7. Out of Scope (For Future Iterations)
*   Auto-publishing posts without human review (strictly drafts for now).
*   Automatic social media sharing of the generated post.
*   Bulk generation of multiple videos at once (to avoid API rate limits and PHP timeout issues in version 1.0).

---

### *Developer Notes on Audio/Transcripts:*
*Fetching YouTube transcripts via API can be complex. If the standard YouTube Data API v3 does not reliably provide auto-generated captions without OAuth, the developer should implement a scraper/parser specifically for the `timedtext` endpoints, or integrate a third-party transcript API as a fallback.*