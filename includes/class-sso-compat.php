<?php
/**
 * Compatibility checks: other SEO plugins and themes without title-tag support.
 *
 * @package Beplus_Metadata_AI_Analyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSO_Compat
 */
class SSO_Compat {

	/**
	 * Singleton instance.
	 *
	 * @var SSO_Compat|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return SSO_Compat
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
	}

	/**
	 * Name of another active SEO plugin that prints the same head tags, or ''.
	 *
	 * @return string
	 */
	public static function active_seo_plugin() {
		$checks = array(
			'WPSEO_VERSION'     => 'Yoast SEO',
			'RANK_MATH_VERSION' => 'Rank Math SEO',
			'AIOSEO_VERSION'    => 'All in One SEO',
			'SEOPRESS_VERSION'  => 'SEOPress',
		);
		foreach ( $checks as $constant => $name ) {
			if ( defined( $constant ) ) {
				return $name;
			}
		}
		return '';
	}

	/**
	 * Whether our front-end head output (canonical, description, Open Graph, schema)
	 * should stand down so another SEO plugin's tags are not duplicated.
	 *
	 * @return bool
	 */
	public static function should_defer() {
		/**
		 * Filter whether to stand down when another SEO plugin is active.
		 * Return false to output our tags alongside it.
		 *
		 * @param bool $defer Default true when another SEO plugin is detected.
		 */
		return (bool) apply_filters( 'sso_defer_to_other_seo_plugin', '' !== self::active_seo_plugin() );
	}

	/**
	 * Admin notices on the plugin's own settings page.
	 */
	public function render_notices() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'sso-settings' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$other = self::active_seo_plugin();
		if ( $other && self::should_defer() ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: name of another SEO plugin. */
						__( '%s is active. To avoid duplicate canonical, description, Open Graph and schema tags, Beplus Metadata AI Analyzer is not printing them on the front end. Deactivate one of the two plugins, or return false from the sso_defer_to_other_seo_plugin filter to output both.', 'beplus-metadata-ai-analyzer' ),
						$other
					)
				)
			);
		}

		if ( ! current_theme_supports( 'title-tag' ) ) {
			printf(
				'<div class="notice notice-info"><p>%s</p></div>',
				esc_html__( 'Your theme does not declare title-tag support. Custom SEO titles are applied through the wp_title() fallback, which only works if the theme calls wp_title() in its header.', 'beplus-metadata-ai-analyzer' )
			);
		}
	}
}
