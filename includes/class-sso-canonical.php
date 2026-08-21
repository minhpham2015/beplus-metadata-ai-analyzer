<?php
/**
 * Canonical URL output.
 *
 * @package Beplus_Metadata_AI_Analyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSO_Canonical
 */
class SSO_Canonical {

	/**
	 * Singleton instance.
	 *
	 * @var SSO_Canonical|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return SSO_Canonical
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
		// Run before WordPress core's own rel_canonical() and replace it entirely.
		remove_action( 'wp_head', 'rel_canonical' );
		add_action( 'wp_head', array( $this, 'output' ), 2 );
	}

	/**
	 * Resolve the canonical URL for the current request.
	 *
	 * @return string
	 */
	private function get_canonical_url() {
		if ( is_singular() ) {
			$post_id  = get_queried_object_id();
			$override = get_post_meta( $post_id, '_sso_canonical_url', true );
			if ( $override ) {
				return $override;
			}
			$canonical = wp_get_canonical_url( $post_id );
			return $canonical ? $canonical : get_permalink( $post_id );
		}

		if ( is_front_page() ) {
			return home_url( '/' );
		}

		if ( is_home() ) {
			$page_for_posts = (int) get_option( 'page_for_posts' );
			$link           = $page_for_posts ? get_permalink( $page_for_posts ) : home_url( '/' );
			return $this->add_pagination( $link );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$link = get_term_link( get_queried_object() );
			return is_wp_error( $link ) ? '' : $this->add_pagination( $link );
		}

		if ( is_post_type_archive() ) {
			$link = get_post_type_archive_link( get_query_var( 'post_type' ) );
			return $link ? $this->add_pagination( $link ) : '';
		}

		if ( is_author() ) {
			return $this->add_pagination( get_author_posts_url( get_queried_object_id() ) );
		}

		if ( is_date() ) {
			// $wp->request already reflects the matched "page/N" segment for paged date archives.
			global $wp;
			return home_url( trailingslashit( $wp->request ) );
		}

		return '';
	}

	/**
	 * Append the current "page/N" pagination segment (or ?paged=N for plain permalinks)
	 * to an archive's page-1 URL, so paginated archive pages don't all canonicalize to page 1.
	 *
	 * @param string $url Unpaginated (page 1) archive URL.
	 * @return string
	 */
	private function add_pagination( $url ) {
		$paged = max( 1, (int) get_query_var( 'paged' ) );
		if ( $paged < 2 ) {
			return $url;
		}

		global $wp_rewrite;
		if ( $wp_rewrite->using_permalinks() ) {
			return user_trailingslashit( trailingslashit( $url ) . $wp_rewrite->pagination_base . '/' . $paged );
		}

		return add_query_arg( 'paged', $paged, $url );
	}

	/**
	 * Output the <link rel="canonical"> tag.
	 */
	public function output() {
		if ( is_admin() ) {
			return;
		}

		$canonical = $this->get_canonical_url();

		if ( $canonical ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
		}
	}
}
