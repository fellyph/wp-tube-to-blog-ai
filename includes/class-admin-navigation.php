<?php
/**
 * Shared admin navigation.
 *
 * @package WP_Tube_To_Blog_AI
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
		<nav class="nav-tab-wrapper wttba-admin-tabs" aria-label="<?php esc_attr_e( 'AI Content Suite sections', 'wp-tube-to-blog-ai' ); ?>">
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
		$items = array(
			'youtube' => array(
				'label' => __( 'YouTube Content', 'wp-tube-to-blog-ai' ),
				'url'   => admin_url( 'admin.php?page=wttba-videos' ),
			),
			'audio'   => array(
				'label' => __( 'Audio to Post', 'wp-tube-to-blog-ai' ),
				'url'   => admin_url( 'admin.php?page=wttba-audio-to-post' ),
			),
		);

		if ( current_user_can( 'manage_options' ) ) {
			$items['settings'] = array(
				'label' => __( 'Settings', 'wp-tube-to-blog-ai' ),
				'url'   => admin_url( 'options-general.php?page=wttba-settings' ),
			);
		}

		return $items;
	}
}
