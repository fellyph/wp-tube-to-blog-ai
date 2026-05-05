<?php
/**
 * Uninstall WP Tube-to-Blog AI.
 *
 * Removes all plugin options and transients on uninstall.
 *
 * @package WP_Tube_To_Blog_AI
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
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_wttba_' ) . '%'
	)
);
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_timeout_wttba_' ) . '%'
	)
);

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ( %s, %s, %s, %s, %s )",
		'_wttba_source_video_id',
		'_wttba_source_type',
		'_wttba_source_attachment_id',
		'_wttba_generated_audio_attachment_id',
		'_wttba_ai_generation_meta'
	)
);
