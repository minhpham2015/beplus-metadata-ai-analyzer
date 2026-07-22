<?php
/**
 * Open Graph and Twitter Card meta tag output.
 *
 * @package Beplus_Smart_SEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSO_OpenGraph
 */
class SSO_OpenGraph {

	/**
	 * Singleton instance.
	 *
	 * @var SSO_OpenGraph|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return SSO_OpenGraph
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
		add_action( 'wp_head', array( $this, 'output' ), 5 );
	}

	/**
	 * Resolve the image to use: OG override -> featured image -> site default -> site logo.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_image_url( $post_id ) {
		$og_image_id = get_post_meta( $post_id, '_sso_og_image', true );
		if ( $og_image_id ) {
			$src = wp_get_attachment_image_url( (int) $og_image_id, 'large' );
			if ( $src ) {
				return $src;
			}
		}

		if ( has_post_thumbnail( $post_id ) ) {
			$src = get_the_post_thumbnail_url( $post_id, 'large' );
			if ( $src ) {
				return $src;
			}
		}

		return $this->get_default_image_url();
	}

	/**
	 * Site-wide fallback image: settings default OG image, then the custom logo.
	 *
	 * @return string
	 */
	private function get_default_image_url() {
		$default_id = SSO_Settings::get( 'social', 'default_og_image', 0 );
		if ( $default_id ) {
			$src = wp_get_attachment_image_url( (int) $default_id, 'large' );
			if ( $src ) {
				return $src;
			}
		}

		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$src = wp_get_attachment_image_url( (int) $logo_id, 'large' );
			if ( $src ) {
				return $src;
			}
		}

		return '';
	}

	/**
	 * Output the Open Graph and Twitter Card meta tags.
	 */
	public function output() {
		if ( is_admin() ) {
			return;
		}

		if ( is_singular() ) {
			$post_id = get_queried_object_id();

			$title = get_post_meta( $post_id, '_sso_og_title', true );
			if ( ! $title ) {
				$title = get_post_meta( $post_id, '_sso_meta_title', true );
			}
			if ( ! $title ) {
				$title = get_the_title( $post_id );
			}

			$description = get_post_meta( $post_id, '_sso_og_description', true );
			if ( ! $description ) {
				$description = get_post_meta( $post_id, '_sso_meta_description', true );
			}
			if ( ! $description ) {
				$description = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post_id ) ), 30, '' );
			}

			$url   = get_permalink( $post_id );
			$type  = 'post' === get_post_type( $post_id ) ? 'article' : 'website';
			$image = $this->get_image_url( $post_id );
		} else {
			$title       = get_bloginfo( 'name' );
			$description = get_bloginfo( 'description' );
			$url         = home_url( '/' );
			$type        = 'website';
			$image       = $this->get_default_image_url();
		}

		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
		echo '<meta property="og:type" content="' . esc_attr( $type ) . '" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";

		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
		}

		$twitter_username = SSO_Settings::get( 'social', 'twitter_username', '' );

		echo '<meta name="twitter:card" content="' . esc_attr( $image ? 'summary_large_image' : 'summary' ) . '" />' . "\n";
		if ( $twitter_username ) {
			echo '<meta name="twitter:site" content="@' . esc_attr( ltrim( $twitter_username, '@' ) ) . '" />' . "\n";
		}
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '" />' . "\n";
		if ( $image ) {
			echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
		}
	}
}
