<?php
/**
 * Open Graph and Twitter Card meta tag output.
 *
 * @package Beplus_Metadata_AI_Analyzer
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
	 * Resolve the image attachment ID: OG override -> featured image -> site default -> site logo.
	 *
	 * @param int $post_id Post ID.
	 * @return int 0 when none.
	 */
	private function get_image_id( $post_id ) {
		$og_image_id = (int) get_post_meta( $post_id, '_sso_og_image', true );
		if ( $og_image_id && wp_get_attachment_image_url( $og_image_id, 'large' ) ) {
			return $og_image_id;
		}

		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		if ( $thumb_id && wp_get_attachment_image_url( $thumb_id, 'large' ) ) {
			return $thumb_id;
		}

		return $this->get_default_image_id();
	}

	/**
	 * Site-wide fallback image attachment ID: settings default OG image, then the custom logo.
	 *
	 * @return int 0 when none.
	 */
	private function get_default_image_id() {
		$default_id = (int) SSO_Settings::get( 'social', 'default_og_image', 0 );
		if ( $default_id && wp_get_attachment_image_url( $default_id, 'large' ) ) {
			return $default_id;
		}

		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id && wp_get_attachment_image_url( $logo_id, 'large' ) ) {
			return $logo_id;
		}

		return 0;
	}

	/**
	 * Output the Open Graph and Twitter Card meta tags.
	 */
	public function output() {
		if ( is_admin() ) {
			return;
		}

		$social     = SSO_Settings::get( 'social' );
		$image_id   = 0;
		$post_id    = 0;
		$post_type  = '';
		$date_pub   = '';
		$date_mod   = '';
		$author     = '';
		$author_url = '';
		$author_id  = 0;

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
				$description = SSO_Meta_Box::get_description( $post_id );
			}

			$url = SSO_Canonical::instance()->get_url();
			if ( ! $url ) {
				$url = get_permalink( $post_id );
			}

			if ( 'page' === $post_type ) {
				$type = 'website';
			} elseif ( 'product' === $post_type ) {
				$type = 'product';
			} else {
				$type = 'article';
			}
			$type = (string) apply_filters( 'sso_og_type', $type, $post_id );

			$image_id   = $this->get_image_id( $post_id );
			$date_pub   = get_the_date( 'c', $post_id );
			$date_mod   = get_the_modified_date( 'c', $post_id );
			$author_id  = (int) get_post_field( 'post_author', $post_id );
			$author     = get_the_author_meta( 'display_name', $author_id );
			$author_url = get_author_posts_url( $author_id );
		} else {
			$title       = get_bloginfo( 'name' );
			$description = get_bloginfo( 'description' );
			$url         = SSO_Canonical::instance()->get_url();
			$type        = 'website';
			$image_id    = $this->get_default_image_id();
			$term        = ( is_category() || is_tag() || is_tax() ) ? get_queried_object() : null;
			if ( is_author() ) {
				$title = get_the_author_meta( 'display_name', get_queried_object_id() ) . ' – ' . $title;
			} elseif ( is_post_type_archive() ) {
				$title = post_type_archive_title( '', false ) . ' – ' . $title;
			}
			if ( $term && ! empty( $term->name ) ) {
				$title = $term->name . ' – ' . $title;
				$desc  = trim( wp_strip_all_tags( term_description() ) );
				if ( $desc ) {
					$description = $desc;
				}
			}
			if ( ! $url ) {
				$url = home_url( '/' );
			}

			// Blog index served by a static "Posts page": use that page's own SEO fields.
			$posts_page_id = SSO_Meta_Box::get_context_post_id();
			if ( $posts_page_id ) {
				$custom_title = get_post_meta( $posts_page_id, '_sso_og_title', true );
				if ( ! $custom_title ) {
					$custom_title = get_post_meta( $posts_page_id, '_sso_meta_title', true );
				}
				$title = $custom_title ? $custom_title : get_the_title( $posts_page_id );

				$custom_desc = get_post_meta( $posts_page_id, '_sso_og_description', true );
				if ( ! $custom_desc ) {
					$custom_desc = get_post_meta( $posts_page_id, '_sso_meta_description', true );
				}
				if ( $custom_desc ) {
					$description = $custom_desc;
				}

				$posts_img_id = $this->get_image_id( $posts_page_id );
				if ( $posts_img_id ) {
					$image_id = $posts_img_id;
				}
			}
		}

		// Paged archives/posts must not share an identical OG title with page 1.
		$page_num = SSO_Meta_Box::get_current_page_number();
		if ( $page_num >= 2 ) {
			$title .= ' – ' . SSO_Meta_Box::page_label( $page_num );
		}

		$image = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';

		// -- Open Graph --
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
		echo '<meta property="og:type" content="' . esc_attr( $type ) . '" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
		echo '<meta property="og:locale" content="' . esc_attr( str_replace( '-', '_', get_bloginfo( 'language' ) ) ) . '" />' . "\n";

		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
			if ( $image_id ) {
				$meta = wp_get_attachment_metadata( $image_id );
				$size = isset( $meta['sizes']['large'] ) ? $meta['sizes']['large'] : $meta;
				if ( ! empty( $size['width'] ) && ! empty( $size['height'] ) ) {
					echo '<meta property="og:image:width" content="' . esc_attr( (int) $size['width'] ) . '" />' . "\n";
					echo '<meta property="og:image:height" content="' . esc_attr( (int) $size['height'] ) . '" />' . "\n";
				}
				$alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
				if ( $alt ) {
					echo '<meta property="og:image:alt" content="' . esc_attr( $alt ) . '" />' . "\n";
				}
			}
		}

		// Article-specific OG properties.
		if ( 'article' === $type && $post_id ) {
			if ( $date_pub ) {
				echo '<meta property="article:published_time" content="' . esc_attr( $date_pub ) . '" />' . "\n";
			}
			if ( $date_mod ) {
				echo '<meta property="article:modified_time" content="' . esc_attr( $date_mod ) . '" />' . "\n";
			}
			if ( $author_url ) {
				// Facebook expects a profile/author URL here, not a display name.
				echo '<meta property="article:author" content="' . esc_url( $author_url ) . '" />' . "\n";
			}
		}

		// -- Twitter Card --
		$twitter_username = ! empty( $social['twitter_username'] ) ? $social['twitter_username'] : '';
		echo '<meta name="twitter:card" content="' . esc_attr( $image ? 'summary_large_image' : 'summary' ) . '" />' . "\n";
		if ( $twitter_username ) {
			echo '<meta name="twitter:site" content="@' . esc_attr( ltrim( $twitter_username, '@' ) ) . '" />' . "\n";
		}
		if ( 'article' === $type && $author_id ) {
			$creator = ltrim( (string) get_the_author_meta( 'twitter', $author_id ), '@' );
			if ( $creator ) {
				echo '<meta name="twitter:creator" content="@' . esc_attr( $creator ) . '" />' . "\n";
			}
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
		// Scholarly metadata only on opted-in post types (default: posts), never on archives.
		$citation_types = (array) apply_filters( 'sso_citation_post_types', array( 'post' ) );
		if ( ! empty( $social['enable_citation'] ) && $post_id && in_array( $post_type, $citation_types, true ) ) {
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
			$llm_summary = $description;
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
