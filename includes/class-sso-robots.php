<?php
/**
 * Robots meta tag (noindex/nofollow) control.
 *
 * @package Beplus_Metadata_AI_Analyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSO_Robots
 */
class SSO_Robots {

	/**
	 * Singleton instance.
	 *
	 * @var SSO_Robots|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return SSO_Robots
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
		add_filter( 'wp_robots', array( $this, 'filter_robots' ) );
	}

	/**
	 * Determine noindex/nofollow flags for the current request.
	 *
	 * @return array{noindex:bool,nofollow:bool}
	 */
	private function resolve_flags() {
		$noindex  = false;
		$nofollow = false;

		if ( is_singular() ) {
			$post_id       = get_queried_object_id();
			$post_type     = get_post_type( $post_id );
			$meta_noindex  = get_post_meta( $post_id, '_sso_noindex', true );
			$meta_nofollow = get_post_meta( $post_id, '_sso_nofollow', true );

			if ( '' !== $meta_noindex ) {
				$noindex = (bool) $meta_noindex;
			} else {
				$defaults = SSO_Settings::get( 'general', 'noindex_post_types', array() );
				$noindex  = ! empty( $defaults[ $post_type ] );
			}

			$nofollow = (bool) $meta_nofollow;
		} elseif ( is_home() && ! is_front_page() && (int) get_option( 'page_for_posts' ) ) {
			$posts_page    = (int) get_option( 'page_for_posts' );
			$meta_noindex  = get_post_meta( $posts_page, '_sso_noindex', true );
			$meta_nofollow = get_post_meta( $posts_page, '_sso_nofollow', true );
			$noindex       = ( '' !== $meta_noindex ) ? (bool) $meta_noindex : false;
			$nofollow      = (bool) $meta_nofollow;
		} elseif ( is_search() ) {
			$noindex = (bool) SSO_Settings::get( 'general', 'noindex_search', 1 );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$archives = SSO_Settings::get( 'general', 'noindex_archives', array() );
			$term     = get_queried_object();
			$taxonomy = ( $term && isset( $term->taxonomy ) ) ? $term->taxonomy : '';
			$noindex  = ! empty( $archives[ $taxonomy ] );
		} elseif ( is_author() ) {
			$archives = SSO_Settings::get( 'general', 'noindex_archives', array() );
			$noindex  = ! empty( $archives['author'] );
		} elseif ( is_date() ) {
			$archives = SSO_Settings::get( 'general', 'noindex_archives', array() );
			$noindex  = ! empty( $archives['date'] );
		}

		return array(
			'noindex'  => $noindex,
			'nofollow' => $nofollow,
		);
	}

	/**
	 * Merge our noindex/nofollow directives into the single robots meta tag
	 * WordPress core prints (wp_robots_render), instead of printing a second,
	 * possibly conflicting tag. Core's own directives (e.g. noindex when
	 * "Discourage search engines" is on, max-image-preview) are preserved.
	 *
	 * @param array $robots Directives => value/bool.
	 * @return array
	 */
	public function filter_robots( $robots ) {
		if ( is_admin() ) {
			return $robots;
		}

		$flags = $this->resolve_flags();
		$flags = apply_filters( 'sso_robots_flags', $flags );

		if ( ! empty( $flags['noindex'] ) ) {
			$robots['noindex'] = true;
			unset( $robots['index'] );
		} else {
			// Rich-snippet hints; merged here so only one robots tag is ever printed.
			$robots['max-snippet']       = -1;
			$robots['max-video-preview'] = -1;
		}
		if ( ! empty( $flags['nofollow'] ) ) {
			$robots['nofollow'] = true;
			unset( $robots['follow'] );
		}

		return $robots;
	}
}
