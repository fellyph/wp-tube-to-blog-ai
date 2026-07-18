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

/**
 * Delete CreatorStack AI data from the current site.
 */
function wttba_delete_site_data(): void {
	$option_names = array(
		'wttba_youtube_api_key',
		'connectors_content_source_youtube_api_key',
		'wttba_youtube_channel_id',
		'wttba_youtube_oauth_client_id',
		'wttba_youtube_oauth_client_secret',
		'wttba_youtube_oauth_access_token',
		'wttba_youtube_oauth_refresh_token',
		'wttba_youtube_oauth_expires_at',
		'wttba_youtube_oauth_verified_redirect_uri',
		'wttba_default_language',
		'wttba_default_persona',
		'wttba_post_length',
		'wttba_ai_model',
		'wttba_feature_youtube_to_post',
		'wttba_feature_audio_to_post',
		'wttba_feature_post_to_audio',
		'wttba_feature_thumbnail_generator',
		'wttba_generation_log',
	);

	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}

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
	delete_post_meta_by_key( '_wttba_generated_thumbnail_attachment_id' );
	delete_post_meta_by_key( '_wttba_ai_generation_meta' );
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		try {
			wttba_delete_site_data();
		} finally {
			restore_current_blog();
		}
	}
} else {
	wttba_delete_site_data();
}
