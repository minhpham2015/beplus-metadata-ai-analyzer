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

		$social    = SSO_Settings::get( 'social' );
		$post_id   = 0;
		$post_type = '';
		$date_pub  = '';
		$date_mod  = '';
		$author    = '';

		if ( is_singular() ) {
			$post_id   = get_queried_object_id();
			$post_type = get_post_type( $post_id );

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

			$url      = get_permalink( $post_id );
			$type     = ( 'post' === $post_type || 'page' === $post_type ) ? ( 'page' === $post_type ? 'website' : 'article' ) : 'article';
			$image    = $this->get_image_url( $post_id );
			$date_pub = get_the_date( 'c', $post_id );
			$date_mod = get_the_modified_date( 'c', $post_id );
			$author   = get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );
		} else {
			$title       = get_bloginfo( 'name' );
			$description = get_bloginfo( 'description' );
			$url         = home_url( '/' );
			$type        = 'website';
			$image       = $this->get_default_image_url();
		}

		// -- Robots hint for AI / rich-snippet crawlers --
		echo '<meta name="robots" content="max-snippet:-1, max-image-preview:large, max-video-preview:-1" />' . "\n";

		// -- Open Graph --
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
		echo '<meta property="og:type" content="' . esc_attr( $type ) . '" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
		echo '<meta property="og:locale" content="' . esc_attr( str_replace( '-', '_', get_bloginfo( 'language' ) ) ) . '" />' . "\n";

		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
		}

		// Article-specific OG properties.
		if ( 'article' === $type && $post_id ) {
			if ( $date_pub ) {
				echo '<meta property="article:published_time" content="' . esc_attr( $date_pub ) . '" />' . "\n";
			}
			if ( $date_mod ) {
				echo '<meta property="article:modified_time" content="' . esc_attr( $date_mod ) . '" />' . "\n";
			}
			if ( $author ) {
				echo '<meta property="article:author" content="' . esc_attr( $author ) . '" />' . "\n";
			}
		}

		// -- Twitter Card --
		$twitter_username = ! empty( $social['twitter_username'] ) ? $social['twitter_username'] : '';
		echo '<meta name="twitter:card" content="' . esc_attr( $image ? 'summary_large_image' : 'summary' ) . '" />' . "\n";
		if ( $twitter_username ) {
			echo '<meta name="twitter:site" content="@' . esc_attr( ltrim( $twitter_username, '@' ) ) . '" />' . "\n";
		}
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '" />' . "\n";
		if ( $image ) {
			echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
		}

		// -- Dublin Core (academic / AI indexing) --
		if ( ! empty( $social['enable_dublin_core'] ) ) {
			echo '<meta name="DC.title" content="' . esc_attr( $title ) . '" />' . "\n";
			echo '<meta name="DC.description" content="' . esc_attr( $description ) . '" />' . "\n";
			if ( $author ) {
				echo '<meta name="DC.creator" content="' . esc_attr( $author ) . '" />' . "\n";
			}
			if ( $date_pub ) {
				echo '<meta name="DC.date" content="' . esc_attr( substr( $date_pub, 0, 10 ) ) . '" />' . "\n";
			}
			echo '<meta name="DC.language" content="' . esc_attr( get_bloginfo( 'language' ) ) . '" />' . "\n";
			echo '<meta name="DC.identifier" content="' . esc_url( $url ) . '" />' . "\n";
		}

		// -- Citation / Scholarly (Google Scholar, AI) --
		if ( ! empty( $social['enable_citation'] ) ) {
			echo '<meta name="citation_title" content="' . esc_attr( $title ) . '" />' . "\n";
			if ( $author ) {
				echo '<meta name="citation_author" content="' . esc_attr( $author ) . '" />' . "\n";
			}
			if ( $date_pub ) {
				echo '<meta name="citation_date" content="' . esc_attr( substr( $date_pub, 0, 10 ) ) . '" />' . "\n";
			}
			echo '<meta name="citation_journal_title" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
		}

		// -- AI / LLM-specific meta --
		if ( ! empty( $social['enable_ai_meta'] ) ) {
			// llm:summary — use meta description if AI has generated it.
			$llm_summary = $post_id ? get_post_meta( $post_id, '_sso_meta_description', true ) : $description;
			if ( $llm_summary ) {
				echo '<meta name="llm:summary" content="' . esc_attr( $llm_summary ) . '" />' . "\n";
			}

			// llm:topics — derive from focus keyword and categories.
			$topics = array();
			if ( $post_id ) {
				$focus_kw = get_post_meta( $post_id, '_sso_focus_keyword', true );
				if ( $focus_kw ) {
					$topics[] = $focus_kw;
				}
				if ( 'post' === $post_type ) {
					$cats = get_the_category( $post_id );
					foreach ( $cats as $cat ) {
						$topics[] = $cat->name;
					}
					$tags = get_the_tags( $post_id );
					if ( $tags ) {
						foreach ( array_slice( $tags, 0, 5 ) as $tag ) {
							$topics[] = $tag->name;
						}
					}
				}
			}
			if ( $topics ) {
				echo '<meta name="llm:topics" content="' . esc_attr( implode( ', ', array_unique( $topics ) ) ) . '" />' . "\n";
			}
		}
	}
}
