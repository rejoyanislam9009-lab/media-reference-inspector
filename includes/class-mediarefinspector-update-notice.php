<?php
/**
 * One-time What's New notice for Media Reference Inspector updates.
 *
 * @package MediaReferenceInspector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows a concise update summary until the administrator opens the plugin page.
 */
class MediaRefInspector_Update_Notice {

	/**
	 * User-meta key used to remember the last version whose update notice was seen.
	 *
	 * @var string
	 */
	const USER_META_KEY = 'mediarefinspector_whats_new_notice_version';

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_init', array( __CLASS__, 'mark_seen_on_plugin_page' ) );
	}

	/**
	 * Marks the current release notice as seen when the plugin page is opened.
	 *
	 * @return void
	 */
	public static function mark_seen_on_plugin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );
		$page = is_string( $page ) ? sanitize_key( $page ) : '';
		if ( 'media-reference-inspector' !== $page ) {
			return;
		}

		update_user_meta( get_current_user_id(), self::USER_META_KEY, MEDIAREFINSPECTOR_VERSION );
	}

	/**
	 * Renders a native WordPress notice after install/update until the plugin page is visited.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$seen_version = (string) get_user_meta( get_current_user_id(), self::USER_META_KEY, true );
		if ( MEDIAREFINSPECTOR_VERSION === $seen_version ) {
			return;
		}

		$url = add_query_arg(
			array(
				'page' => 'media-reference-inspector',
				'tab'  => 'scanner',
			),
			admin_url( 'upload.php' )
		);
		?>
		<div class="notice notice-info">
			<p><strong><?php esc_html_e( 'Media Reference Inspector 2.4.0 is ready — see what’s new.', 'media-reference-inspector' ); ?></strong></p>
			<p><?php esc_html_e( 'New in this release: Media Impact Preview, expanded metadata and SEO/builder coverage, Media Library usage status, Site Audit Summary, JSON export, and WordPress 7.1 compatibility.', 'media-reference-inspector' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Explore the new features', 'media-reference-inspector' ); ?></a></p>
		</div>
		<?php
	}
}
