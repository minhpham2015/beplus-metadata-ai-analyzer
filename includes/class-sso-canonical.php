<?php
/**
 * Canonical URL output.
 *
 * @package Beplus_Smart_SEO_Optimizer
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
			return $page_for_posts ? get_permalink( $page_for_posts ) : home_url( '/' );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$link = get_term_link( get_queried_object() );
			return is_wp_error( $link ) ? '' : $link;
		}

		if ( is_post_type_archive() ) {
			$link = get_post_type_archive_link( get_query_var( 'post_type' ) );
			return $link ? $link : '';
		}

		if ( is_author() ) {
			return get_author_posts_url( get_queried_object_id() );
		}

		if ( is_date() ) {
			global $wp;
			return home_url( trailingslashit( $wp->request ) );
		}

		return '';
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
