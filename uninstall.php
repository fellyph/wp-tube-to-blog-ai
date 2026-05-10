<?php
/**
 * Uninstall CreatorStack AI.
 *
 * Removes all plugin options and transients on uninstall.
 *
 * @package CreatorStack_AI
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wttba_youtube_api_key' );
delete_option( 'wttba_youtube_channel_id' );
delete_option( 'wttba_youtube_oauth_client_id' );
delete_option( 'wttba_youtube_oauth_client_secret' );
delete_option( 'wttba_youtube_oauth_access_token' );
delete_option( 'wttba_youtube_oauth_refresh_token' );
delete_option( 'wttba_youtube_oauth_expires_at' );
delete_option( 'wttba_youtube_oauth_verified_redirect_uri' );
delete_option( 'wttba_default_language' );
delete_option( 'wttba_default_persona' );
delete_option( 'wttba_generation_log' );

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk uninstall cleanup by transient prefix has no option API equivalent.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_wttba_' ) . '%'
	)
);
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk uninstall cleanup by transient timeout prefix has no option API equivalent.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_timeout_wttba_' ) . '%'
	)
);

delete_post_meta_by_key( '_wttba_source_video_id' );
delete_post_meta_by_key( '_wttba_source_type' );
delete_post_meta_by_key( '_wttba_source_attachment_id' );
delete_post_meta_by_key( '_wttba_generated_audio_attachment_id' );
delete_post_meta_by_key( '_wttba_ai_generation_meta' );
