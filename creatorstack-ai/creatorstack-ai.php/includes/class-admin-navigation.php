<?php
/**
 * Shared admin navigation.
 *
 * @package CreatorStack_AI
 */

namespace WTTBA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders top-level navigation between plugin admin screens.
 */
class Admin_Navigation {

	/**
	 * Render the admin navigation tabs.
	 *
	 * @param string $active Active tab key.
	 */
	public static function render( string $active ): void {
		$items = self::get_items();

		if ( empty( $items ) ) {
			return;
		}
		?>
		<nav class="nav-tab-wrapper wttba-admin-tabs" aria-label="<?php esc_attr_e( 'CreatorStack AI sections', 'creatorstack-ai' ); ?>">
			<?php foreach ( $items as $key => $item ) : ?>
				<?php
				$classes = array( 'nav-tab' );

				if ( $active === $key ) {
					$classes[] = 'nav-tab-active';
				}
				?>
				<a
					href="<?php echo esc_url( $item['url'] ); ?>"
					class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
					<?php if ( $active === $key ) : ?>
						aria-current="page"
					<?php endif; ?>
				>
					<?php echo esc_html( $item['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Get navigation items available to the current user.
	 *
	 * @return array<string, array{label: string, url: string}>
	 */
	private static function get_items(): array {
		$items = array();

		if ( Settings::is_youtube_to_post_enabled() ) {
			$items['youtube'] = array(
				'label' => __( 'YouTube Content', 'creatorstack-ai' ),
				'url'   => admin_url( 'admin.php?page=wttba-videos' ),
			);
		}

		if ( Settings::is_audio_to_post_enabled() ) {
			$items['audio'] = array(
				'label' => __( 'Audio to Post', 'creatorstack-ai' ),
				'url'   => admin_url( 'admin.php?page=wttba-audio-to-post' ),
			);
		}

		if ( current_user_can( 'manage_options' ) ) {
			$items['settings'] = array(
				'label' => __( 'Settings', 'creatorstack-ai' ),
				'url'   => admin_url( 'options-general.php?page=wttba-settings' ),
			);
		}

		return $items;
	}
}
