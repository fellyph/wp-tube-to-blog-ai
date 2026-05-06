# CreatorStack AI User Guide

English | [Português](user-guide-pt.md) | [Español](user-guide-es.md)

CreatorStack AI helps you turn YouTube videos and uploaded audio files into WordPress draft posts. When your AI provider supports text-to-speech, it can also create an audio version of a post.

## Before You Start

You need:

- A WordPress site running a build with the WordPress AI Client APIs available. This plugin currently expects WordPress 7.0 beta or newer.
- An AI provider configured in WordPress. Use **Settings > Connectors** when the Connectors screen is available.
- A YouTube Data API v3 key and YouTube Channel ID for YouTube video workflows.
- A Google OAuth Web application client if you want CreatorStack AI to read captions through the official YouTube Captions API.
- A WordPress user account with the right permissions:
  - Administrators can configure plugin settings.
  - Authors, Editors, and Administrators can generate and edit draft posts when they have `edit_posts`.

For audio workflows, the configured AI provider must support the required capability:

- **YouTube to Post** requires text generation.
- **Audio to Post** requires audio input and text generation.
- **Post to Audio** requires text-to-speech.

## Configure The AI Provider

1. In WordPress admin, open **Settings > Connectors**.
2. Install or enable an AI provider connector.
3. Add the provider credentials required by that connector.
4. Return to **Settings > AI Content Suite**.
5. In the **AI Provider** section, click **Test AI Connection**.
6. Confirm that the test succeeds before generating content.

If WordPress does not show **Settings > Connectors**, use the AI Client settings screen linked from the **AI Provider** section.

## Configure YouTube

1. Open **Settings > AI Content Suite**.
2. In **YouTube Integration**, follow the setup wizard.
3. Enable **YouTube Data API v3** in Google Cloud.
4. Create a YouTube Data API key and paste it into **YouTube API Key**.
5. Find your YouTube Channel ID and paste it into **YouTube Channel ID**.
6. Create a Google OAuth client with **Web application** as the application type.
7. Copy the **Authorized redirect URI** shown by WordPress and add it to the OAuth client in Google Cloud.
8. Paste the `client_secret.json` contents into the wizard, click **Fill OAuth fields**, then click **Save Changes**.
9. After the page reloads, click **Connect YouTube** and complete the Google consent flow.

OAuth is used for official caption downloads. The connected YouTube account must be able to edit the videos whose captions you want to use.

## Set Content Defaults

In **Settings > AI Content Suite > Content Settings**, choose:

- **Default Output Language**: the language used unless you override it during generation.
- **Post Length**:
  - Short: about 600 to 900 words.
  - Medium: about 1,000 to 1,500 words.
  - Long: about 1,800 to 2,500 words.
- **Writing Persona**: optional guidance for tone, audience, structure, or style.

In **AI Provider**, you can also choose a **Preferred AI Model**. Leave it on **Automatic (recommended)** unless you need a specific model. If the preferred model is unavailable, the AI Client can use another compatible configured model.

## Create A Draft From A YouTube Video

You can start from the dashboard widget or the full video page.

From the dashboard:

1. Open **Dashboard**.
2. Find the **YouTube to Blog** widget.
3. Click **Generate Post** on a recent video.

From the full video page:

1. Open **Tube-to-Blog > YouTube Content**.
2. Browse your channel videos.
3. Click **Generate Post** on the video you want to use.
4. Use **Load More Videos** if you need older videos.

When the generation modal opens:

1. Choose the output language.
2. Adjust the writing persona if needed.
3. If YouTube captions are missing or unreliable, enable **Use a custom transcript instead of fetching captions** and paste at least 50 characters of transcript text.
4. Click **Generate**.
5. Review the **Draft Preview**.
6. Click **Regenerate** if the result needs a fresh version.
7. Click **Save as Draft** when the preview is ready.
8. Open **Edit Draft** to review, edit, and publish the post.

Saved YouTube drafts include the generated article, a YouTube embed, source metadata, and the YouTube thumbnail as the featured image when WordPress can download it.

## Create A Draft From An Audio File

1. Open **Tube-to-Blog > Audio to Post**.
2. Click **Create Draft From Audio**. This opens a new post draft.
3. In the editor sidebar, open the **AI Content Suite** panel.
4. In **Audio to Post**, click **Select Audio**.
5. Choose an audio file from the Media Library or upload one.
6. Select the output language.
7. Adjust the writing persona if needed.
8. Click **Generate Draft**.

CreatorStack AI updates the current draft with a generated title and article content, then saves the draft.

Supported audio extensions are `mp3`, `m4a`, `wav`, `ogg`, `webm`, `flac`, and `aac`. The maximum file size is 25 MB or the site upload limit, whichever is lower.

## Generate Audio From A Post

1. Open an existing post or draft.
2. In the editor sidebar, open the **AI Content Suite** panel.
3. In **Post to Audio**, enter a voice name if your provider supports voice selection.
4. Click **Generate Audio**.

If the post has unsaved changes, CreatorStack AI saves them first. It then creates an audio attachment, inserts an Audio block at the top of the post, replaces any previous CreatorStack AI audio block, stores the generated audio attachment ID in post meta, and saves the post again.

## Review AI Usage

Administrators can open **Settings > AI Content Suite > AI Usage** to see recent generations. The table shows date, source, status, provider, model, and token usage when the provider reports it.

## Troubleshooting

- **AI unavailable**: configure an AI provider and run **Test AI Connection**.
- **No captions found**: connect YouTube OAuth, choose a video with captions, or use a manual transcript.
- **Manual transcript too short**: paste at least 50 characters.
- **A post is already being generated**: wait for the current generation to finish. The plugin prevents concurrent generation per user.
- **Featured image warning**: the draft was created, but WordPress could not download the YouTube thumbnail.
- **Audio file rejected**: check the file extension, MIME type, and file size.
- **Localhost issues**: localhost is supported for development, but WordPress still needs outbound HTTPS access to YouTube and the configured AI provider.
