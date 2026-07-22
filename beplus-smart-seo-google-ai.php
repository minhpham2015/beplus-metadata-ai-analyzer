<?php
/**
 * Plugin Name:       BePlus Smart SEO Google & AI
 * Plugin URI:        https://beplusthemes.com/plugins/beplus-smart-seo-google-ai/
 * Description:       Complete SEO toolkit for WordPress: meta tags, XML sitemap, Open Graph & Twitter Cards, focus keyword analysis, breadcrumbs, canonical URLs, robots control, and Schema.org structured data (JSON-LD).
 * Version:           1.0.0
 * Author:            BePlus
 * Author URI:        https://beplusthemes.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       beplus-smart-seo-google-ai
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Requires PHP:      7.4
 *
 * @package Beplus_Smart_SEO_Optimizer
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'SSO_VERSION', '1.0.0' );
define( 'SSO_PLUGIN_FILE', __FILE__ );
define( 'SSO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SSO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin bootstrap class.
 *
 * Loads every module class and wires them up once WordPress core is ready.
 */
final class SSO_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var SSO_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return SSO_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Kept private to enforce singleton usage.
	 */
	private function __construct() {
		$this->includes();

		add_action( 'plugins_loaded', array( $this, 'init_modules' ) );
	}

	/**
	 * Require every module file.
	 */
	private function includes() {
		require_once SSO_PLUGIN_DIR . 'includes/class-sso-settings.php';
		require_once SSO_PLUGIN_DIR . 'includes/class-sso-analyzer.php';
		require_once SSO_PLUGIN_DIR . 'includes/class-sso-meta-box.php';
		require_once SSO_PLUGIN_DIR . 'includes/class-sso-sitemap.php';
		require_once SSO_PLUGIN_DIR . 'includes/class-sso-opengraph.php';
		require_once SSO_PLUGIN_DIR . 'includes/class-sso-breadcrumbs.php';
		require_once SSO_PLUGIN_DIR . 'includes/class-sso-canonical.php';
		require_once SSO_PLUGIN_DIR . 'includes/class-sso-robots.php';
		require_once SSO_PLUGIN_DIR . 'includes/class-sso-schema.php';
	}

	/**
	 * Instantiate every module. Each module wires its own hooks in its constructor.
	 */
	public function init_modules() {
		$this->register_head_comment_hooks();

		SSO_Settings::instance();
		SSO_Analyzer::instance();
		SSO_Meta_Box::instance();
		SSO_Sitemap::instance();
		SSO_OpenGraph::instance();
		SSO_Breadcrumbs::instance();
		SSO_Canonical::instance();
		SSO_Robots::instance();
		SSO_Schema::instance();
	}

	/**
	 * Output HTML comment wrappers around all plugin meta tags in wp_head.
	 *
	 * Priority 0 opens the block — fires before our earliest hook (SSO_Robots at 1).
	 * Priority 99 closes the block — fires after our latest hook (SSO_Schema at 20).
	 * Both callbacks are skipped in the admin context.
	 */
	private function register_head_comment_hooks() {
		if ( is_admin() ) {
			return;
		}

		add_action(
			'wp_head',
			function () {
				echo "\n<!-- BePlus Smart SEO & Google AI Optimizer v" . esc_html( SSO_VERSION ) . " - https://beplus.vn -->\n";
			},
			0
		);

		add_action(
			'wp_head',
			function () {
				echo "\n<!-- / BePlus Smart SEO & Google AI Optimizer -->\n";
			},
			99
		);
	}
}

/**
 * Boot the plugin and return the main instance.
 *
 * @return SSO_Plugin
 */
function sso_plugin() {
	return SSO_Plugin::instance();
}
sso_plugin();

/**
 * Activation callback: register the sitemap rewrite rule, flush, and store plugin version.
 */
function sso_activate_plugin() {
	require_once SSO_PLUGIN_DIR . 'includes/class-sso-settings.php';
	require_once SSO_PLUGIN_DIR . 'includes/class-sso-sitemap.php';
	SSO_Sitemap::instance()->add_rewrite_rules();
	flush_rewrite_rules();

	if ( ! get_option( 'sso_version' ) ) {
		add_option( 'sso_version', SSO_VERSION );
	}
}
register_activation_hook( __FILE__, 'sso_activate_plugin' );

/**
 * Deactivation callback: clean up rewrite rules.
 */
function sso_deactivate_plugin() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'sso_deactivate_plugin' );

/**
 * Track version changes so future upgrade routines can gate on stored vs. current version.
 */
add_action(
	'plugins_loaded',
	function () {
		$stored = get_option( 'sso_version', '0' );
		if ( version_compare( $stored, SSO_VERSION, '<' ) ) {
			// Placeholder for future upgrade routines.
			update_option( 'sso_version', SSO_VERSION );
		}
	}
);
