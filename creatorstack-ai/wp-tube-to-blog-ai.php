<?php
/**
 * Backward-compatible loader for installs activated under the old file name.
 *
 * @package CreatorStack_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/creatorstack-ai.php';
