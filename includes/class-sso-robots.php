<?php
/**
 * Robots meta tag (noindex/nofollow) control.
 *
 * @package Beplus_Smart_SEO_Optimizer
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
		add_action( 'wp_head', array( $this, 'output' ), 1 );
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
	 * Output the <meta name="robots"> tag.
	 */
	public function output() {
		if ( is_admin() ) {
			return;
		}

		$flags = $this->resolve_flags();
		$flags = apply_filters( 'sso_robots_flags', $flags );

		$parts   = array();
		$parts[] = ! empty( $flags['noindex'] ) ? 'noindex' : 'index';
		$parts[] = ! empty( $flags['nofollow'] ) ? 'nofollow' : 'follow';

		echo '<meta name="robots" content="' . esc_attr( implode( ',', $parts ) ) . '" />' . "\n";
	}
}
