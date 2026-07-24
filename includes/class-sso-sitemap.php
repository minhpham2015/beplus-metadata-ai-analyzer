<?php
/**
 * Virtual XML sitemap served at /sitemap.xml (no file is ever written to disk)
 * plus a lightweight Google ping on publish/update.
 *
 * @package Beplus_Metadata_AI_Analyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSO_Sitemap
 */
class SSO_Sitemap {

	/**
	 * Singleton instance.
	 *
	 * @var SSO_Sitemap|null
	 */
	private static $instance = null;

	/**
	 * Query var flag used to detect a sitemap request.
	 */
	const QUERY_VAR = 'sso_sitemap';

	/**
	 * Transient key for the cached sitemap XML (12-hour TTL).
	 */
	const CACHE_KEY = 'sso_sitemap_xml';

	/**
	 * Transient key used to rate-limit Google pings (5-minute cooldown).
	 */
	const PING_COOLDOWN_KEY = 'sso_google_ping_last';

	/**
	 * Get the singleton instance.
	 *
	 * @return SSO_Sitemap
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
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_sitemap' ) );

		// Invalidate cached sitemap whenever content changes.
		add_action( 'save_post', array( $this, 'clear_sitemap_cache' ), 10 );
		add_action( 'delete_post', array( $this, 'clear_sitemap_cache' ) );

		// Ping search engines AFTER the cache is cleared (higher priority = later).
		add_action( 'save_post', array( $this, 'ping_search_engines' ), 20, 3 );

		// Invalidate cache when plugin settings change.
		add_action( 'updated_option', array( $this, 'on_option_updated' ), 10, 1 );
	}

	/**
	 * Register the /sitemap.xml virtual rewrite rule.
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule( '^sitemap\.xml$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Whitelist our query var.
	 *
	 * @param string[] $vars Public query vars.
	 * @return string[]
	 */
	public function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Output the sitemap XML and stop WordPress from rendering a template, when requested.
	 */
	public function maybe_render_sitemap() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		if ( ! (bool) SSO_Settings::get( 'sitemap', 'enabled', 1 ) ) {
			status_header( 404 );
			nocache_headers();
			return;
		}

		header( 'Content-Type: application/xml; charset=UTF-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML is pre-built with esc_url/esc_html applied per field in get_sitemap_xml().
		echo $this->get_sitemap_xml();
		exit;
	}

	/**
	 * Return the sitemap XML string, serving from a 12-hour transient cache when available.
	 *
	 * Cache is invalidated on every post save/delete and whenever the plugin settings change.
	 *
	 * @return string Full <?xml …> sitemap string.
	 */
	public function get_sitemap_xml() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $this->get_urls() as $item ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_url( $item['loc'] ) . "</loc>\n";
			if ( ! empty( $item['lastmod'] ) ) {
				$xml .= "\t\t<lastmod>" . esc_html( $item['lastmod'] ) . "</lastmod>\n";
			}
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';

		set_transient( self::CACHE_KEY, $xml, 12 * HOUR_IN_SECONDS );

		return $xml;
	}

	/**
	 * Delete the cached sitemap XML so it is regenerated on the next request.
	 */
	public function clear_sitemap_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Clear sitemap cache whenever the plugin settings option is saved.
	 *
	 * @param string $option Updated option name.
	 */
	public function on_option_updated( $option ) {
		if ( SSO_Settings::OPTION_KEY === $option ) {
			$this->clear_sitemap_cache();
		}
	}

	/**
	 * Build the list of URLs to include: public post types + public taxonomy terms.
	 *
	 * Posts are filtered entirely in SQL (no per-row get_post_meta calls) by combining
	 * the _sso_sitemap_exclude and _sso_noindex checks into the WP_Query meta_query.
	 * Term/meta caches are disabled because we only need the IDs + permalinks.
	 *
	 * @return array[] List of ['loc' => string, 'lastmod' => string].
	 */
	public function get_urls() {
		$urls               = array();
		$exclude_post_types = SSO_Settings::get( 'sitemap', 'exclude_post_types', array() );

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );

		foreach ( $post_types as $post_type ) {
			if ( ! empty( $exclude_post_types[ $post_type ] ) ) {
				continue;
			}

			$query = new WP_Query(
				array(
					'post_type'              => $post_type,
					'post_status'            => 'publish',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,    // Skip SQL_CALC_FOUND_ROWS — pagination not needed.
					'update_post_term_cache' => false,   // Term cache not needed for sitemap IDs.
					'update_post_meta_cache' => false,   // Meta cache not needed; noindex filtered in SQL below.
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- sitemap runs on-demand; 12-hour cache means this fires rarely.
					'meta_query'             => array(
						'relation' => 'AND',
						// Exclude posts explicitly removed from the sitemap.
						array(
							'key'     => '_sso_sitemap_exclude',
							'compare' => 'NOT EXISTS',
						),
						// Exclude posts marked noindex (either key absent or value is not '1').
						array(
							'relation' => 'OR',
							array(
								'key'     => '_sso_noindex',
								'compare' => 'NOT EXISTS',
							),
							array(
								'key'     => '_sso_noindex',
								'value'   => '1',
								'compare' => '!=',
							),
						),
					),
				)
			);

			foreach ( $query->posts as $post_id ) {
				$urls[] = array(
					'loc'     => get_permalink( $post_id ),
					'lastmod' => get_the_modified_date( 'c', $post_id ),
				);
			}
		}

		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
				)
			);
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				$urls[] = array(
					'loc'     => $link,
					'lastmod' => '',
				);
			}
		}

		return apply_filters( 'sso_sitemap_urls', $urls );
	}

	/**
	 * Ping Google with the sitemap URL whenever a post is published or updated.
	 *
	 * Rate-limited to at most one ping per 5 minutes so a bulk import cannot
	 * flood Google's ping endpoint.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 */
	public function ping_search_engines( $post_id, $post, $update ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! (bool) SSO_Settings::get( 'sitemap', 'enabled', 1 ) ) {
			return;
		}

		// Rate limit: skip if a ping was sent within the last 5 minutes.
		if ( false !== get_transient( self::PING_COOLDOWN_KEY ) ) {
			return;
		}

		$sitemap_url = home_url( '/sitemap.xml' );

		wp_remote_get(
			'https://www.google.com/ping?sitemap=' . rawurlencode( $sitemap_url ),
			array(
				'timeout'  => 3,
				'blocking' => false,
			)
		);

		set_transient( self::PING_COOLDOWN_KEY, time(), 5 * MINUTE_IN_SECONDS );
	}
}
