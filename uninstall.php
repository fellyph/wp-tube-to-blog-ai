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
delete_option( 'wttba_default_language' );
delete_option( 'wttba_default_persona' );

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
