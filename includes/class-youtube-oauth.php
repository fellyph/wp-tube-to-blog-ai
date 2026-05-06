<?php
/**
 * YouTube OAuth integration.
 *
 * @package WP_Tube_To_Blog_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles YouTube OAuth and official caption downloads.
 */
class YouTube_OAuth {

	private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	private const API_BASE  = 'https://www.googleapis.com/youtube/v3';
	private const SCOPE     = 'https://www.googleapis.com/auth/youtube.force-ssl';

	private const CLIENT_ID_OPTION     = 'wttba_youtube_oauth_client_id';
	private const CLIENT_SECRET_OPTION = 'wttba_youtube_oauth_client_secret';
	private const ACCESS_TOKEN_OPTION  = 'wttba_youtube_oauth_access_token';
	private const REFRESH_TOKEN_OPTION = 'wttba_youtube_oauth_refresh_token';
	private const EXPIRES_AT_OPTION    = 'wttba_youtube_oauth_expires_at';
	private const REDIRECT_URI_OPTION  = 'wttba_youtube_oauth_verified_redirect_uri';
	private const STATE_PREFIX         = 'wttba_youtube_oauth_state_';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_post_wttba_youtube_oauth_connect', array( $this, 'handle_connect' ) );
		add_action( 'admin_post_wttba_youtube_oauth_callback', array( $this, 'handle_callback' ) );
		add_action( 'admin_post_wttba_youtube_oauth_disconnect', array( $this, 'handle_disconnect' ) );
	}

	/**
	 * Get the OAuth redirect URI configured in Google Cloud.
	 *
	 * @return string Redirect URI.
	 */
	public static function get_redirect_uri(): string {
		return admin_url( 'admin-post.php?action=wttba_youtube_oauth_callback' );
	}

	/**
	 * Whether OAuth client credentials are configured.
	 *
	 * @return bool
	 */
	public static function has_credentials(): bool {
		return self::is_valid_client_id( self::get_client_id() ) && self::is_valid_client_secret( self::get_client_secret() );
	}

	/**
	 * Whether a value looks like a Google OAuth client ID.
	 *
	 * @param string $client_id OAuth client ID.
	 * @return bool
	 */
	public static function is_valid_client_id( string $client_id ): bool {
		return 1 === preg_match( '/^[0-9]+-[0-9A-Za-z_-]+\.apps\.googleusercontent\.com$/', trim( $client_id ) );
	}

	/**
	 * Whether a value looks like a Google OAuth client secret.
	 *
	 * @param string $client_secret OAuth client secret.
	 * @return bool
	 */
	public static function is_valid_client_secret( string $client_secret ): bool {
		return 1 === preg_match( '/^[0-9A-Za-z_-]{8,}$/', trim( $client_secret ) );
	}

	/**
	 * Whether a refresh token is stored.
	 *
	 * @return bool
	 */
	public static function is_connected(): bool {
		return '' !== self::get_refresh_token();
	}

	/**
	 * Whether the current redirect URI has completed a successful OAuth callback.
	 *
	 * @return bool
	 */
	public static function is_redirect_uri_verified(): bool {
		return self::is_connected() && self::get_redirect_uri() === (string) get_option( self::REDIRECT_URI_OPTION, '' );
	}

	/**
	 * Fetch transcript text through the official YouTube Captions API.
	 *
	 * @param string $video_id YouTube video ID.
	 * @param string $lang     Preferred output language.
	 * @return string|\WP_Error Transcript text or error.
	 */
	public static function fetch_transcript( string $video_id, string $lang = 'en' ): string|\WP_Error {
		if ( ! self::has_credentials() ) {
			return new \WP_Error(
				'wttba_youtube_oauth_missing_credentials',
				__( 'YouTube OAuth client credentials are not configured.', 'wp-tube-to-blog-ai' )
			);
		}

		if ( ! self::is_connected() ) {
			return new \WP_Error(
				'wttba_youtube_oauth_not_connected',
				__( 'Connect YouTube with OAuth to download captions through the official API.', 'wp-tube-to-blog-ai' )
			);
		}

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$tracks = self::list_caption_tracks( $video_id, $token );
		if ( is_wp_error( $tracks ) ) {
			return $tracks;
		}

		$track = self::select_caption_track( $tracks, $lang );
		if ( null === $track ) {
			return new \WP_Error(
				'wttba_youtube_caption_not_found',
				__( 'No caption tracks were available through the YouTube Captions API.', 'wp-tube-to-blog-ai' )
			);
		}

		$caption = self::download_caption_track( $track, $lang, $token );
		if ( is_wp_error( $caption ) ) {
			return $caption;
		}

		return self::parse_caption_file( $caption );
	}

	/**
	 * Handle OAuth connect action.
	 */
	public function handle_connect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to connect YouTube.', 'wp-tube-to-blog-ai' ) );
		}

		check_admin_referer( 'wttba_youtube_oauth_connect' );

		if ( ! self::has_credentials() ) {
			$this->redirect_with_status( 'missing_credentials' );
		}

		$state = wp_generate_password( 32, false, false );
		set_transient( self::STATE_PREFIX . get_current_user_id(), $state, 10 * MINUTE_IN_SECONDS );

		$url = self::AUTH_URL . '?' . http_build_query(
			array(
				'client_id'              => self::get_client_id(),
				'redirect_uri'           => self::get_redirect_uri(),
				'response_type'          => 'code',
				'scope'                  => self::SCOPE,
				'access_type'            => 'offline',
				'include_granted_scopes' => 'true',
				'prompt'                 => 'consent',
				'state'                  => $state,
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);

		wp_redirect( esc_url_raw( $url ) );
		exit;
	}

	/**
	 * Handle OAuth callback from Google.
	 */
	public function handle_callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to connect YouTube.', 'wp-tube-to-blog-ai' ) );
		}

		$state        = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$stored_state = (string) get_transient( self::STATE_PREFIX . get_current_user_id() );
		delete_transient( self::STATE_PREFIX . get_current_user_id() );

		if ( '' === $state || '' === $stored_state || ! hash_equals( $stored_state, $state ) ) {
			$this->redirect_with_status( 'invalid_state' );
		}

		if ( ! empty( $_GET['error'] ) ) {
			$this->redirect_with_status( sanitize_key( wp_unslash( $_GET['error'] ) ) );
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( '' === $code ) {
			$this->redirect_with_status( 'missing_code' );
		}

		$result = $this->exchange_code( $code );
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_status( $result->get_error_code() );
		}

		self::update_private_option( self::REDIRECT_URI_OPTION, esc_url_raw( self::get_redirect_uri() ) );
		$this->redirect_with_status( 'connected' );
	}

	/**
	 * Handle OAuth disconnect action.
	 */
	public function handle_disconnect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to disconnect YouTube.', 'wp-tube-to-blog-ai' ) );
		}

		check_admin_referer( 'wttba_youtube_oauth_disconnect' );

		self::delete_token_options();
		$this->redirect_with_status( 'disconnected' );
	}

	/**
	 * Get a valid access token.
	 *
	 * @return string|\WP_Error Access token or error.
	 */
	private static function get_access_token(): string|\WP_Error {
		$access_token = (string) get_option( self::ACCESS_TOKEN_OPTION, '' );
		$expires_at   = (int) get_option( self::EXPIRES_AT_OPTION, 0 );

		if ( '' !== $access_token && $expires_at > time() + MINUTE_IN_SECONDS ) {
			return $access_token;
		}

		$refresh_token = self::get_refresh_token();
		if ( '' === $refresh_token ) {
			return new \WP_Error(
				'wttba_youtube_oauth_not_connected',
				__( 'Connect YouTube with OAuth to download captions through the official API.', 'wp-tube-to-blog-ai' )
			);
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'body'    => array(
					'client_id'     => self::get_client_id(),
					'client_secret' => self::get_client_secret(),
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		return self::handle_token_response( $response, false );
	}

	/**
	 * Exchange an authorization code for tokens.
	 *
	 * @param string $code Authorization code.
	 * @return true|\WP_Error True on success.
	 */
	private function exchange_code( string $code ): true|\WP_Error {
		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'body'    => array(
					'code'          => $code,
					'client_id'     => self::get_client_id(),
					'client_secret' => self::get_client_secret(),
					'redirect_uri'  => self::get_redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		$result = self::handle_token_response( $response, true );

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Handle an OAuth token endpoint response.
	 *
	 * @param array|\WP_Error $response              HTTP response.
	 * @param bool            $require_refresh_token Whether a refresh token must be present.
	 * @return string|\WP_Error Access token or error.
	 */
	private static function handle_token_response( array|\WP_Error $response, bool $require_refresh_token ): string|\WP_Error {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $data ) || empty( $data['access_token'] ) ) {
			return new \WP_Error(
				'wttba_youtube_oauth_failed',
				__( 'YouTube OAuth token exchange failed. Check the OAuth client configuration and try reconnecting.', 'wp-tube-to-blog-ai' )
			);
		}

		if ( $require_refresh_token && empty( $data['refresh_token'] ) && '' === self::get_refresh_token() ) {
			return new \WP_Error(
				'wttba_youtube_oauth_failed',
				__( 'YouTube did not return a refresh token. Reconnect YouTube and approve offline access.', 'wp-tube-to-blog-ai' )
			);
		}

		self::update_private_option( self::ACCESS_TOKEN_OPTION, sanitize_text_field( (string) $data['access_token'] ) );
		self::update_private_option( self::EXPIRES_AT_OPTION, (string) ( time() + absint( $data['expires_in'] ?? HOUR_IN_SECONDS ) ) );

		if ( ! empty( $data['refresh_token'] ) ) {
			self::update_private_option( self::REFRESH_TOKEN_OPTION, sanitize_text_field( (string) $data['refresh_token'] ) );
		}

		return sanitize_text_field( (string) $data['access_token'] );
	}

	/**
	 * List caption tracks for a video.
	 *
	 * @param string $video_id YouTube video ID.
	 * @param string $token    OAuth access token.
	 * @return array<int, array<string, mixed>>|\WP_Error Caption track items.
	 */
	private static function list_caption_tracks( string $video_id, string $token ): array|\WP_Error {
		$response = wp_remote_get(
			add_query_arg(
				array(
					'part'    => 'snippet',
					'videoId' => $video_id,
				),
				self::API_BASE . '/captions'
			),
			self::get_authorized_request_args( $token )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 403 === $code ) {
			return new \WP_Error(
				'wttba_youtube_oauth_forbidden',
				__( 'The connected YouTube account is not allowed to read captions for this video.', 'wp-tube-to-blog-ai' ),
				array( 'status' => 403 )
			);
		}

		if ( 200 !== $code || ! is_array( $data ) ) {
			return new \WP_Error(
				'wttba_youtube_caption_download_failed',
				__( 'Could not load caption tracks from the YouTube Captions API.', 'wp-tube-to-blog-ai' ),
				array( 'status' => 502 )
			);
		}

		return is_array( $data['items'] ?? null ) ? $data['items'] : array();
	}

	/**
	 * Download one caption track.
	 *
	 * @param array<string, mixed> $track Caption track.
	 * @param string               $lang  Preferred output language.
	 * @param string               $token OAuth access token.
	 * @return string|\WP_Error Raw caption file.
	 */
	private static function download_caption_track( array $track, string $lang, string $token ): string|\WP_Error {
		$caption_id = (string) ( $track['id'] ?? '' );
		if ( '' === $caption_id ) {
			return new \WP_Error(
				'wttba_youtube_caption_not_found',
				__( 'The selected YouTube caption track is missing its ID.', 'wp-tube-to-blog-ai' )
			);
		}

		$args = array( 'tfmt' => 'srt' );
		if ( ! self::track_matches_language( $track, $lang ) ) {
			$args['tlang'] = self::normalize_caption_language( $lang );
		}

		$response = wp_remote_get(
			add_query_arg( $args, self::API_BASE . '/captions/' . rawurlencode( $caption_id ) ),
			self::get_authorized_request_args( $token )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code || '' === trim( $body ) ) {
			return new \WP_Error(
				'wttba_youtube_caption_download_failed',
				__( 'Could not download captions from the YouTube Captions API.', 'wp-tube-to-blog-ai' ),
				array( 'status' => 502 )
			);
		}

		return $body;
	}

	/**
	 * Select the best caption track for a requested language.
	 *
	 * @param array<int, array<string, mixed>> $tracks Caption tracks.
	 * @param string                           $lang   Preferred language.
	 * @return array<string, mixed>|null Selected track.
	 */
	private static function select_caption_track( array $tracks, string $lang ): ?array {
		$serving_tracks = array_values(
			array_filter(
				$tracks,
				static function ( $track ): bool {
					return is_array( $track )
						&& ! empty( $track['id'] )
						&& 'serving' === (string) ( $track['snippet']['status'] ?? 'serving' )
						&& empty( $track['snippet']['isDraft'] );
				}
			)
		);

		if ( empty( $serving_tracks ) ) {
			return null;
		}

		foreach ( $serving_tracks as $track ) {
			if ( self::track_matches_language( $track, $lang ) ) {
				return $track;
			}
		}

		return $serving_tracks[0];
	}

	/**
	 * Parse an SRT/VTT caption file into plain text.
	 *
	 * @param string $caption Caption file content.
	 * @return string|\WP_Error Plain transcript text or error.
	 */
	private static function parse_caption_file( string $caption ): string|\WP_Error {
		$caption = str_replace( array( "\r\n", "\r" ), "\n", $caption );
		$lines   = preg_split( '/\n/', $caption ) ?: array();
		$text    = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if (
				'' === $line
				|| preg_match( '/^\d+$/', $line )
				|| str_contains( $line, '-->' )
				|| str_starts_with( $line, 'WEBVTT' )
				|| str_starts_with( $line, 'NOTE' )
			) {
				continue;
			}

			$line = trim( wp_strip_all_tags( html_entity_decode( $line, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
			$line = preg_replace( '/\s+/', ' ', $line ) ?? $line;

			if ( '' !== $line ) {
				$text[] = $line;
			}
		}

		if ( empty( $text ) ) {
			return new \WP_Error(
				'wttba_empty_transcript',
				__( 'The downloaded YouTube caption file did not contain readable text.', 'wp-tube-to-blog-ai' )
			);
		}

		return implode( ' ', $text );
	}

	/**
	 * Get request args with bearer auth.
	 *
	 * @param string $token OAuth access token.
	 * @return array<string, mixed>
	 */
	private static function get_authorized_request_args( string $token ): array {
		return array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json, text/plain, */*',
			),
		);
	}

	/**
	 * Check whether a caption track matches a requested language.
	 *
	 * @param array<string, mixed> $track Caption track.
	 * @param string               $lang  Requested language.
	 * @return bool
	 */
	private static function track_matches_language( array $track, string $lang ): bool {
		$track_lang = strtolower( (string) ( $track['snippet']['language'] ?? '' ) );
		$lang       = strtolower( $lang );

		return $track_lang === $lang || self::normalize_caption_language( $track_lang ) === self::normalize_caption_language( $lang );
	}

	/**
	 * Normalize a language code for YouTube caption translation.
	 *
	 * @param string $lang Language code.
	 * @return string Normalized language code.
	 */
	private static function normalize_caption_language( string $lang ): string {
		$lang = strtolower( trim( $lang ) );

		return str_contains( $lang, '-' ) ? explode( '-', $lang )[0] : $lang;
	}

	/**
	 * Get OAuth client ID.
	 *
	 * @return string
	 */
	private static function get_client_id(): string {
		return trim( (string) get_option( self::CLIENT_ID_OPTION, '' ) );
	}

	/**
	 * Get OAuth client secret.
	 *
	 * @return string
	 */
	private static function get_client_secret(): string {
		return trim( (string) get_option( self::CLIENT_SECRET_OPTION, '' ) );
	}

	/**
	 * Get OAuth refresh token.
	 *
	 * @return string
	 */
	private static function get_refresh_token(): string {
		return (string) get_option( self::REFRESH_TOKEN_OPTION, '' );
	}

	/**
	 * Update a private token option without autoloading.
	 *
	 * @param string $option Option name.
	 * @param string $value  Option value.
	 */
	private static function update_private_option( string $option, string $value ): void {
		update_option( $option, $value, false );
	}

	/**
	 * Delete stored OAuth tokens.
	 */
	public static function delete_token_options(): void {
		delete_option( self::ACCESS_TOKEN_OPTION );
		delete_option( self::REFRESH_TOKEN_OPTION );
		delete_option( self::EXPIRES_AT_OPTION );
		delete_option( self::REDIRECT_URI_OPTION );
	}

	/**
	 * Redirect to settings with an OAuth status.
	 *
	 * @param string $status Status code.
	 */
	private function redirect_with_status( string $status ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                => 'wttba-settings',
					'wttba_youtube_oauth' => sanitize_key( $status ),
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
