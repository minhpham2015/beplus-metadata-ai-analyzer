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
	 * Query var holding the sub-sitemap page number (0 = index / single sitemap).
	 */
	const PAGE_QUERY_VAR = 'sso_sitemap_page';

	/**
	 * Maximum URLs per sitemap file. Above this total, /sitemap.xml becomes a
	 * sitemap index pointing at /sitemap-1.xml, /sitemap-2.xml, … chunks.
	 * 2000 mirrors WordPress core's per-sitemap default and keeps each file
	 * small enough to build in memory without strain.
	 */
	const MAX_URLS_PER_PAGE = 2000;

	/**
	 * Upper bound of chunk transients cleared on cache invalidation. At
	 * MAX_URLS_PER_PAGE=2000 this covers up to 1,000,000 URLs — far beyond any
	 * realistic WordPress site, while keeping clear_sitemap_cache() a cheap,
	 * bounded loop.
	 */
	const MAX_CACHED_PAGES = 500;

	/**
	 * Transient key for the cached sitemap XML (12-hour TTL).
	 */
	const CACHE_KEY = 'sso_sitemap_xml';

	/**
	 * Transient key for the cached full URL list (12-hour TTL). Shared by the
	 * index and every chunk so the (potentially expensive) URL gather runs
	 * once per cache window rather than once per requested sub-sitemap.
	 */
	const URLS_CACHE_KEY = 'sso_sitemap_urls';

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

		// Point robots.txt at our sitemap instead of WordPress core's wp-sitemap.xml.
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 10, 1 );
	}

	/**
	 * Replace (or append) the Sitemap: line in robots.txt so it points at
	 * our /sitemap.xml instead of WordPress core's default wp-sitemap.xml.
	 *
	 * @param string $output Existing robots.txt content.
	 * @return string
	 */
	public function filter_robots_txt( $output ) {
		if ( ! (bool) SSO_Settings::get( 'sitemap', 'enabled', 1 ) ) {
			return $output;
		}

		$our_sitemap = home_url( '/sitemap.xml' );

		// Strip any existing "Sitemap:" line(s) — including WP core's wp-sitemap.xml — to avoid duplicates/mismatches.
		$output = preg_replace( '/^Sitemap:.*$/mi', '', $output );
		$output = trim( $output );

		return $output . "\n\nSitemap: " . esc_url_raw( $our_sitemap ) . "\n";
	}

	/**
	 * Register the /sitemap.xml + /sitemap-N.xml virtual rewrite rules.
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule( '^sitemap\.xml$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
		add_rewrite_rule(
			'^sitemap-([0-9]+)\.xml$',
			'index.php?' . self::QUERY_VAR . '=1&' . self::PAGE_QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Whitelist our query vars.
	 *
	 * @param string[] $vars Public query vars.
	 * @return string[]
	 */
	public function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::PAGE_QUERY_VAR;
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

		$page = (int) get_query_var( self::PAGE_QUERY_VAR );

		$xml = $this->get_sitemap_xml( $page );

		// A request for /sitemap-N.xml with an out-of-range N (no URLs) is a 404.
		if ( '' === $xml ) {
			status_header( 404 );
			nocache_headers();
			return;
		}

		header( 'Content-Type: application/xml; charset=UTF-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML is pre-built with esc_url/esc_html applied per field.
		echo $xml;
		exit;
	}

	/**
	 * Return the sitemap XML for the requested page, serving from a 12-hour
	 * transient cache when available.
	 *
	 * - $page === 0: if the total URL count is within one page, returns a
	 *   normal <urlset>. If it exceeds MAX_URLS_PER_PAGE, returns a
	 *   <sitemapindex> pointing at /sitemap-1.xml … /sitemap-N.xml instead.
	 * - $page >= 1: returns the <urlset> for that chunk, or '' if out of range.
	 *
	 * Cache is invalidated on every post save/delete and whenever the plugin
	 * settings change.
	 *
	 * @param int $page Sub-sitemap page number (0 = index or single sitemap).
	 * @return string Full <?xml …> sitemap string, or '' for an out-of-range page.
	 */
	public function get_sitemap_xml( $page = 0 ) {
		$page      = max( 0, (int) $page );
		$cache_key = self::CACHE_KEY . '_' . $page;

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$urls  = $this->get_cached_urls();
		$total = count( $urls );

		// Root request on a large site → emit a sitemap index.
		if ( 0 === $page && $total > self::MAX_URLS_PER_PAGE ) {
			$xml = $this->build_index_xml( $total );
			set_transient( $cache_key, $xml, 12 * HOUR_IN_SECONDS );
			return $xml;
		}

		// Root request on a small site → single urlset over all URLs (back-compat).
		if ( 0 === $page ) {
			$slice = $urls;
		} else {
			// Chunk request → the Nth slice; empty slice means out-of-range (404).
			$offset = ( $page - 1 ) * self::MAX_URLS_PER_PAGE;
			if ( $offset >= $total ) {
				return '';
			}
			$slice = array_slice( $urls, $offset, self::MAX_URLS_PER_PAGE );
		}

		$xml = $this->build_urlset_xml( $slice );
		set_transient( $cache_key, $xml, 12 * HOUR_IN_SECONDS );

		return $xml;
	}

	/**
	 * Build a <urlset> document from a list of URL items.
	 *
	 * @param array[] $items List of ['loc' => string, 'lastmod' => string].
	 * @return string
	 */
	private function build_urlset_xml( $items ) {
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $items as $item ) {
			$xml .= "	<url>\n";
			$xml .= "		<loc>" . esc_url( $item['loc'] ) . "</loc>\n";
			if ( ! empty( $item['lastmod'] ) ) {
				$xml .= "		<lastmod>" . esc_html( $item['lastmod'] ) . "</lastmod>\n";
			}
			$xml .= "	</url>\n";
		}

		$xml .= '</urlset>';

		return $xml;
	}

	/**
	 * Build a <sitemapindex> document listing one child sitemap per chunk.
	 *
	 * @param int $total Total number of URLs across all chunks.
	 * @return string
	 */
	private function build_index_xml( $total ) {
		$pages = (int) ceil( $total / self::MAX_URLS_PER_PAGE );
		$now   = gmdate( 'c' );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		for ( $i = 1; $i <= $pages; $i++ ) {
			$xml .= "	<sitemap>\n";
			$xml .= "		<loc>" . esc_url( home_url( '/sitemap-' . $i . '.xml' ) ) . "</loc>\n";
			$xml .= "		<lastmod>" . esc_html( $now ) . "</lastmod>\n";
			$xml .= "	</sitemap>\n";
		}

		$xml .= '</sitemapindex>';

		return $xml;
	}

	/**
	 * Return the full URL list, cached in a 12-hour transient shared by the
	 * index and every chunk so the gather runs once per cache window.
	 *
	 * @return array[]
	 */
	private function get_cached_urls() {
		$cached = get_transient( self::URLS_CACHE_KEY );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$urls = $this->get_urls();
		set_transient( self::URLS_CACHE_KEY, $urls, 12 * HOUR_IN_SECONDS );

		return $urls;
	}

	/**
	 * Delete all cached sitemap XML (index, every chunk, and the shared URL
	 * list) so everything is regenerated on the next request.
	 *
	 * Chunk transients are keyed by page number; we delete page 0 (index /
	 * single sitemap) plus every chunk up to the current URL count, and clear
	 * a generous fixed range as a safety net in case the count shrank.
	 */
	public function clear_sitemap_cache() {
		delete_transient( self::URLS_CACHE_KEY );
		delete_transient( self::CACHE_KEY . '_0' );

		// Clear per-chunk caches. Range is bounded and cheap; covers shrinking
		// URL counts (e.g. bulk-deleting posts) without leaving stale chunks.
		for ( $i = 1; $i <= self::MAX_CACHED_PAGES; $i++ ) {
			delete_transient( self::CACHE_KEY . '_' . $i );
		}
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
		$noindex_post_types = SSO_Settings::get( 'general', 'noindex_post_types', array() );

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );

		foreach ( $post_types as $post_type ) {
			if ( ! empty( $exclude_post_types[ $post_type ] ) ) {
				continue;
			}

			// Mirror SSO_Robots::resolve_flags(): a per-post _sso_noindex value always wins;
			// otherwise noindex state falls back to this post type's site-wide default.
			if ( ! empty( $noindex_post_types[ $post_type ] ) ) {
				$noindex_meta_query = array(
					'key'     => '_sso_noindex',
					'value'   => '0',
					'compare' => '=',
				);
			} else {
				$noindex_meta_query = array(
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
				);
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
						$noindex_meta_query,
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
