<?php
/**
 * Breadcrumb trail: template tag sso_breadcrumbs() and the [sso_breadcrumb] shortcode.
 *
 * @package Beplus_Metadata_AI_Analyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSO_Breadcrumbs
 */
class SSO_Breadcrumbs {

	/**
	 * Singleton instance.
	 *
	 * @var SSO_Breadcrumbs|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return SSO_Breadcrumbs
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
		add_shortcode( 'sso_breadcrumb', array( $this, 'shortcode' ) );
	}

	/**
	 * Build the breadcrumb trail for the current request.
	 *
	 * @return array[] List of ['text' => string, 'url' => string] items, in order.
	 */
	public function get_trail() {
		$settings      = SSO_Settings::get( 'breadcrumbs' );
		$home_text     = ! empty( $settings['homepage_text'] ) ? $settings['homepage_text'] : __( 'Home', 'beplus-metadata-ai-analyzer' );
		$show_category = ! empty( $settings['show_category'] );

		$trail   = array();
		$trail[] = array(
			'text' => $home_text,
			'url'  => home_url( '/' ),
		);

		if ( is_singular() ) {
			global $post;
			$post_type = get_post_type( $post );

			if ( $show_category && 'post' === $post_type ) {
				// Standard post: Home > [Parent Cat >] Category > Post Title.
				$categories = get_the_category( $post->ID );
				if ( ! empty( $categories ) ) {
					$cat       = $categories[0];
					$ancestors = array_reverse( get_ancestors( $cat->term_id, 'category' ) );
					foreach ( $ancestors as $ancestor_id ) {
						$term = get_term( $ancestor_id, 'category' );
						if ( $term && ! is_wp_error( $term ) ) {
							$trail[] = array(
								'text' => $term->name,
								'url'  => get_term_link( $term ),
							);
						}
					}
					$trail[] = array(
						'text' => $cat->name,
						'url'  => get_term_link( $cat ),
					);
				}
			} elseif ( 'page' === $post_type && $post->post_parent ) {
				// Child page: Home > [Grandparent >] Parent > Page.
				$ancestors = array_reverse( get_post_ancestors( $post ) );
				foreach ( $ancestors as $ancestor_id ) {
					$trail[] = array(
						'text' => get_the_title( $ancestor_id ),
						'url'  => get_permalink( $ancestor_id ),
					);
				}
			} elseif ( 'post' !== $post_type && 'page' !== $post_type ) {
				// Custom post type: Home > CPT Archive > Post Title.
				$post_type_obj = get_post_type_object( $post_type );
				if ( $post_type_obj && $post_type_obj->has_archive ) {
					$archive_link = get_post_type_archive_link( $post_type );
					if ( $archive_link ) {
						$trail[] = array(
							'text' => $post_type_obj->labels->name,
							'url'  => $archive_link,
						);
					}
				}
				// CPT taxonomy terms.
				$taxonomies = get_object_taxonomies( $post_type, 'objects' );
				foreach ( $taxonomies as $taxonomy ) {
					if ( ! $taxonomy->hierarchical ) {
						continue;
					}
					$terms = get_the_terms( $post->ID, $taxonomy->name );
					if ( ! $terms || is_wp_error( $terms ) ) {
						continue;
					}
					$term      = $terms[0];
					$ancestors = array_reverse( get_ancestors( $term->term_id, $taxonomy->name ) );
					foreach ( $ancestors as $ancestor_id ) {
						$ancestor_term = get_term( $ancestor_id, $taxonomy->name );
						if ( $ancestor_term && ! is_wp_error( $ancestor_term ) ) {
							$trail[] = array(
								'text' => $ancestor_term->name,
								'url'  => get_term_link( $ancestor_term ),
							);
						}
					}
					$trail[] = array(
						'text' => $term->name,
						'url'  => get_term_link( $term ),
					);
					break; // Use first hierarchical taxonomy only.
				}
			}

			$trail[] = array(
				'text' => get_the_title( $post ),
				'url'  => '',
			);
		} elseif ( is_category() ) {
			// Category archive: Home > [Parent Cat >] Category.
			$cat = get_queried_object();
			if ( $cat && $cat->parent ) {
				$ancestors = array_reverse( get_ancestors( $cat->term_id, 'category' ) );
				foreach ( $ancestors as $ancestor_id ) {
					$term = get_term( $ancestor_id, 'category' );
					if ( $term && ! is_wp_error( $term ) ) {
						$trail[] = array(
							'text' => $term->name,
							'url'  => get_term_link( $term ),
						);
					}
				}
			}
			$trail[] = array(
				'text' => single_term_title( '', false ),
				'url'  => '',
			);
		} elseif ( is_tag() ) {
			$trail[] = array(
				'text' => single_term_title( '', false ),
				'url'  => '',
			);
		} elseif ( is_tax() ) {
			// Custom taxonomy: Home > [Parent Term >] Term.
			$term = get_queried_object();
			if ( $term && $term->parent ) {
				$ancestors = array_reverse( get_ancestors( $term->term_id, $term->taxonomy ) );
				foreach ( $ancestors as $ancestor_id ) {
					$ancestor_term = get_term( $ancestor_id, $term->taxonomy );
					if ( $ancestor_term && ! is_wp_error( $ancestor_term ) ) {
						$trail[] = array(
							'text' => $ancestor_term->name,
							'url'  => get_term_link( $ancestor_term ),
						);
					}
				}
			}
			$trail[] = array(
				'text' => single_term_title( '', false ),
				'url'  => '',
			);
		} elseif ( is_post_type_archive() ) {
			// CPT archive: Home > CPT Archive.
			$trail[] = array(
				'text' => post_type_archive_title( '', false ),
				'url'  => '',
			);
		} elseif ( is_search() ) {
			$trail[] = array(
				/* translators: %s: search query. */
				'text' => sprintf( __( 'Search results for: %s', 'beplus-metadata-ai-analyzer' ), get_search_query() ),
				'url'  => '',
			);
		} elseif ( is_404() ) {
			$trail[] = array(
				'text' => __( '404 Not Found', 'beplus-metadata-ai-analyzer' ),
				'url'  => '',
			);
		} elseif ( is_author() ) {
			$trail[] = array(
				'text' => get_the_author_meta( 'display_name', get_queried_object_id() ),
				'url'  => '',
			);
		} elseif ( is_home() && ! is_front_page() ) {
			$page_for_posts = (int) get_option( 'page_for_posts' );
			if ( $page_for_posts ) {
				$trail[] = array(
					'text' => get_the_title( $page_for_posts ),
					'url'  => '',
				);
			}
		}

		return apply_filters( 'sso_breadcrumbs_trail', $trail );
	}

	/**
	 * Render the breadcrumb trail as an accessible HTML list.
	 *
	 * @return string
	 */
	public function render() {
		if ( is_front_page() ) {
			return '';
		}

		$trail     = $this->get_trail();
		$settings  = SSO_Settings::get( 'breadcrumbs' );
		$separator = ! empty( $settings['separator'] ) ? $settings['separator'] : '&raquo;';
		$count     = count( $trail );

		$output = '<nav class="sso-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'beplus-metadata-ai-analyzer' ) . '"><ol>';

		foreach ( $trail as $i => $item ) {
			$output .= '<li class="sso-breadcrumb-item">';
			if ( ! empty( $item['url'] ) && $i < $count - 1 ) {
				$output .= '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['text'] ) . '</a>';
			} else {
				$output .= '<span aria-current="page">' . esc_html( $item['text'] ) . '</span>';
			}
			$output .= '</li>';

			if ( $i < $count - 1 ) {
				// Allow only safe inline elements in the separator (e.g. <span>, <i>, <svg>).
				$allowed_tags = array(
					'span' => array( 'class' => array() ),
					'i'    => array( 'class' => array() ),
					'svg'  => array(
						'class'   => array(),
						'xmlns'   => array(),
						'viewbox' => array(),
						'width'   => array(),
						'height'  => array(),
					),
				);
				$output      .= '<li class="sso-breadcrumb-sep">' . wp_kses( $separator, $allowed_tags ) . '</li>';
			}
		}

		$output .= '</ol></nav>';

		return $output;
	}

	/**
	 * Shortcode callback for [sso_breadcrumb].
	 *
	 * @return string
	 */
	public function shortcode() {
		return $this->render();
	}
}

if ( ! function_exists( 'sso_breadcrumbs' ) ) {
	/**
	 * Template tag: echo the breadcrumb trail. Call from theme templates.
	 */
	function sso_breadcrumbs() { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- public template tag intentionally lives beside its class; moving it to a separate file is a public-API/file-layout change, not a style fix.
		echo SSO_Breadcrumbs::instance()->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() escapes every dynamic piece internally.
	}
}
