<?php
/**
 * Schema.org structured data (JSON-LD): a site-wide Organization/Person + WebSite
 * graph, plus per-post-type Article / Product / FAQPage / LocalBusiness markup.
 *
 * @package Beplus_Metadata_AI_Analyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSO_Schema
 */
class SSO_Schema {

	/**
	 * Singleton instance.
	 *
	 * @var SSO_Schema|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return SSO_Schema
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
		add_action( 'wp_head', array( $this, 'output_global_schema' ), 19 );
		add_action( 'wp_head', array( $this, 'output_post_schema' ), 20 );
		add_action( 'wp_head', array( $this, 'output_breadcrumb_schema' ), 21 );
		add_filter( 'get_post_metadata', array( $this, 'redirect_schema_meta_to_entry' ), 10, 4 );
	}

	/*
	------------------------------------------------------------------ *
	 * Global schema: Organization/Person + WebSite (with SearchAction)
	 * ------------------------------------------------------------------
	 */

	/**
	 * Build the Organization or Person schema node from Settings.
	 *
	 * @return array
	 */
	public function build_organization_schema() {
		$schema = SSO_Settings::get( 'schema' );
		$type   = ( isset( $schema['entity_type'] ) && 'person' === $schema['entity_type'] ) ? 'Person' : 'Organization';

		$data = array(
			'@type' => $type,
			'@id'   => home_url( '/#' . strtolower( $type ) ),
			'name'  => ! empty( $schema['name'] ) ? $schema['name'] : get_bloginfo( 'name' ),
			'url'   => ! empty( $schema['url'] ) ? $schema['url'] : home_url( '/' ),
		);

		if ( ! empty( $schema['logo'] ) ) {
			$logo_url = wp_get_attachment_image_url( (int) $schema['logo'], 'medium' );
			if ( $logo_url ) {
				$data['logo'] = array(
					'@type' => 'ImageObject',
					'url'   => $logo_url,
				);
				if ( 'Organization' === $type ) {
					$data['image'] = $logo_url;
				}
			}
		}

		if ( ! empty( $schema['sameas'] ) && is_array( $schema['sameas'] ) ) {
			$sameas = array_values( array_filter( $schema['sameas'] ) );
			if ( $sameas ) {
				$data['sameAs'] = $sameas;
			}
		}

		return $data;
	}

	/**
	 * Build the WebSite schema node, including the sitelinks SearchAction.
	 *
	 * @return array
	 */
	public function build_website_schema() {
		return array(
			'@type'           => 'WebSite',
			'@id'             => home_url( '/#website' ),
			'name'            => get_bloginfo( 'name' ),
			'url'             => home_url( '/' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => home_url( '/?s={search_term_string}' ),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/**
	 * Output the site-wide Organization/Person + WebSite graph once per page, via wp_footer.
	 */
	public function output_global_schema() {
		if ( is_admin() ) {
			return;
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				$this->build_organization_schema(),
				$this->build_website_schema(),
			),
		);

		$this->print_ld_json( $data );
	}

	/*
	------------------------------------------------------------------ *
	 * Per post type schema: Article / Product / FAQPage / LocalBusiness
	 * ------------------------------------------------------------------
	 */

	/**
	 * Resolve the schema type to use for the current singular post.
	 *
	 * Priority (most specific wins), each falling through only when the
	 * previous tier has no explicit rule for this post:
	 *   1. Per-post override (post meta `_sso_schema_type`, set in the meta
	 *      box's Schema tab directly on the post/page itself).
	 *   2. A "Schemas" CPT entry (see SSO_Schema_CPT) assigned either to
	 *      this specific post/page, or to every post of this post type.
	 *      Replaces the old Settings-tab tiers (Schema per Post Type /
	 *      Schema by Page Template / Schema by Category-Tag).
	 *
	 * When tier 2 wins, `entry_id` is returned so output_post_schema() can
	 * redirect `_sso_schema_*` meta reads to the entry's own post ID (the
	 * builders below read schema field meta keyed by whichever post ID
	 * they're given).
	 *
	 * @param int $post_id Post ID.
	 * @return array{enabled:bool,type:string,entry_id:int}
	 */
	private function resolve_post_schema_type( $post_id ) {
		static $cache = array();
		if ( isset( $cache[ $post_id ] ) ) {
			return $cache[ $post_id ];
		}

		// Tier 1: per-post override, set directly in the post's own Schema tab.
		$override = get_post_meta( $post_id, '_sso_schema_type', true );
		if ( $override ) {
			$resolved          = ( 'none' === $override )
				? array(
					'enabled'  => false,
					'type'     => '',
					'entry_id' => 0,
				)
				: array(
					'enabled'  => true,
					'type'     => $override,
					'entry_id' => 0,
				);
			$cache[ $post_id ] = $resolved;
			return $resolved;
		}

		// Tier 2: a "Schemas" CPT entry assigned to this post or its post type.
		if ( class_exists( 'SSO_Schema_CPT' ) ) {
			$entry_id = SSO_Schema_CPT::resolve_schema_entry_id( $post_id );
			if ( $entry_id ) {
				$entry_type = get_post_meta( $entry_id, '_sso_schema_type', true );
				if ( $entry_type && 'none' !== $entry_type ) {
					$resolved          = array(
						'enabled'  => true,
						'type'     => $entry_type,
						'entry_id' => $entry_id,
					);
					$cache[ $post_id ] = $resolved;
					return $resolved;
				}
			}
		}

		$resolved          = array(
			'enabled'  => false,
			'type'     => '',
			'entry_id' => 0,
		);
		$cache[ $post_id ] = $resolved;
		return $resolved;
	}

	/**
	 * While a Schemas CPT entry is supplying the schema data for a post,
	 * redirect any `_sso_schema_*` meta read on that post to the entry's
	 * own post ID instead — lets every `build_*_schema()` method below stay
	 * unchanged (they already read `_sso_schema_*` keyed by whatever post ID
	 * they're handed).
	 *
	 * @param mixed  $value    Short-circuit value (null = no override yet).
	 * @param int    $object_id Post ID the core is fetching meta for.
	 * @param string $meta_key  Meta key being fetched.
	 * @param bool   $single    Whether a single value was requested.
	 * @return mixed
	 */
	public function redirect_schema_meta_to_entry( $value, $object_id, $meta_key, $single ) {
		if ( (int) $object_id !== (int) $this->schema_redirect_post_id ) {
			return $value;
		}
		if ( 0 !== strpos( (string) $meta_key, '_sso_schema_' ) ) {
			return $value;
		}
		remove_filter( 'get_post_metadata', array( $this, 'redirect_schema_meta_to_entry' ), 10 );
		$redirected = get_post_meta( $this->schema_redirect_entry_id, $meta_key, $single );
		add_filter( 'get_post_metadata', array( $this, 'redirect_schema_meta_to_entry' ), 10, 4 );
		return $redirected;
	}

	/**
	 * Post ID currently being redirected (set right before a build_*_schema()
	 * call that needs entry-sourced meta, cleared right after).
	 *
	 * @var int
	 */
	private $schema_redirect_post_id = 0;

	/**
	 * Schemas CPT entry ID currently supplying redirected meta.
	 *
	 * @var int
	 */
	private $schema_redirect_entry_id = 0;

	/**
	 * Output the post-specific JSON-LD schema.
	 *
	 * On singular views this resolves per the normal 3-tier priority
	 * (per-post override → specific-post/post-type Schemas entry → site-wide
	 * Schemas entry), reading fields against the REAL post ID so url/name
	 * naturally reflect that page.
	 *
	 * On non-singular views (home, archives, search) there is no post to
	 * resolve against, so only a "Whole site" Schemas entry can apply —
	 * built directly from the entry's own fields, with url/@id normalized
	 * to the site's home URL and a blank name/title falling back to the
	 * site name instead of the entry's internal post title.
	 */
	public function output_post_schema() {
		if ( is_admin() ) {
			return;
		}

		$is_site_wide_only = false;

		if ( is_singular() ) {
			$post_id  = get_queried_object_id();
			$resolved = $this->resolve_post_schema_type( $post_id );
		} else {
			$entry_id = class_exists( 'SSO_Schema_CPT' ) ? SSO_Schema_CPT::get_site_wide_entry_id() : 0;
			if ( ! $entry_id ) {
				return;
			}
			$entry_type = get_post_meta( $entry_id, '_sso_schema_type', true );
			if ( ! $entry_type || 'none' === $entry_type ) {
				return;
			}
			$post_id            = $entry_id;
			$is_site_wide_only  = true;
			$resolved           = array(
				'enabled'  => true,
				'type'     => $entry_type,
				'entry_id' => $entry_id,
			);
		}

		if ( ! $resolved['enabled'] || ! $resolved['type'] ) {
			return;
		}

		if ( ! empty( $resolved['entry_id'] ) && ! $is_site_wide_only ) {
			$this->schema_redirect_post_id  = $post_id;
			$this->schema_redirect_entry_id = $resolved['entry_id'];
		}

		$data = null;

		switch ( $resolved['type'] ) {
			case 'article':
				$data = $this->build_article_schema( $post_id );
				break;
			case 'blogposting':
				$data = $this->build_blogposting_schema( $post_id );
				break;
			case 'webpage':
				$data = $this->build_webpage_schema( $post_id );
				break;
			case 'product':
				$data = $this->build_product_schema( $post_id );
				break;
			case 'faqpage':
				$data = $this->build_faqpage_schema( $post_id );
				break;
			case 'localbusiness':
				$data = $this->build_localbusiness_schema( $post_id );
				break;
			case 'howto':
				$data = $this->build_howto_schema( $post_id );
				break;
			case 'event':
				$data = $this->build_event_schema( $post_id );
				break;
			case 'video':
				$data = $this->build_video_schema( $post_id );
				break;
			case 'recipe':
				$data = $this->build_recipe_schema( $post_id );
				break;
			case 'jobposting':
				$data = $this->build_jobposting_schema( $post_id );
				break;
			case 'course':
				$data = $this->build_course_schema( $post_id );
				break;
			case 'review':
				$data = $this->build_review_schema( $post_id );
				break;
		}

		$this->schema_redirect_post_id  = 0;
		$this->schema_redirect_entry_id = 0;

		// On non-singular "Whole site" output, the builders above read the
		// Schemas entry's OWN post ID (there is no real front-end page for
		// it — sso_schema is admin-only/non-public) — recursively replace
		// any value that equals the entry's own permalink/title (however
		// deeply nested: offers.url, sameAs, mainEntityOfPage.@id, etc.)
		// with the site's home URL / site name instead.
		if ( $is_site_wide_only && is_array( $data ) ) {
			$entry_permalink = get_permalink( $post_id );
			$entry_title     = get_the_title( $post_id );
			$data            = $this->replace_recursive(
				$data,
				array(
					$entry_permalink => home_url( '/' ),
					$entry_title     => get_bloginfo( 'name' ),
				)
			);
		}

		$data = apply_filters( 'sso_schema_post_data', $data, $post_id, $resolved['type'] );

		if ( ! $data ) {
			return;
		}

		$this->print_ld_json( array_merge( array( '@context' => 'https://schema.org' ), $data ) );
	}

	/**
	 * Recursively replace exact-match scalar values in an array (used to
	 * swap a Schemas entry's internal permalink/title for the site's real
	 * home URL/name when that entry is being output as a site-wide fallback
	 * rather than for its own — non-public — post).
	 *
	 * @param array $data         Array to walk (mutated copy returned).
	 * @param array $replacements Map of old value => new value.
	 * @return array
	 */
	private function replace_recursive( $data, $replacements ) {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = $this->replace_recursive( $value, $replacements );
			} elseif ( is_string( $value ) && '' !== $value && array_key_exists( $value, $replacements ) ) {
				$data[ $key ] = $replacements[ $value ];
			}
		}
		return $data;
	}

	/**
	 * Build an Article schema node for the given post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function build_article_schema( $post_id ) {
		$headline = get_post_meta( $post_id, '_sso_schema_headline', true );
		if ( ! $headline ) {
			$headline = get_the_title( $post_id );
		}

		$author_name = get_post_meta( $post_id, '_sso_schema_author', true );
		if ( ! $author_name ) {
			$author_name = get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) );
		}

		$data = array(
			'@type'            => 'Article',
			'headline'         => wp_strip_all_tags( $headline ),
			'datePublished'    => get_the_date( 'c', $post_id ),
			'dateModified'     => get_the_modified_date( 'c', $post_id ),
			'author'           => array(
				'@type' => 'Person',
				'name'  => $author_name,
			),
			'publisher'        => $this->build_organization_schema(),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post_id ),
			),
		);

		if ( has_post_thumbnail( $post_id ) ) {
			$data['image'] = get_the_post_thumbnail_url( $post_id, 'large' );
		}

		return $data;
	}

	/**
	 * Build a Product schema node. Pulls live data from WooCommerce when active.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function build_product_schema( $post_id ) {
		$data = array(
			'@type' => 'Product',
			'name'  => get_the_title( $post_id ),
		);

		if ( has_post_thumbnail( $post_id ) ) {
			$data['image'] = get_the_post_thumbnail_url( $post_id, 'large' );
		}

		$description = get_post_meta( $post_id, '_sso_meta_description', true );
		if ( ! $description ) {
			$description = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post_id ) ), 30, '' );
		}
		if ( $description ) {
			$data['description'] = $description;
		}

		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );

			if ( $product ) {
				$data['sku'] = $product->get_sku() ? $product->get_sku() : (string) $post_id;

				$data['offers'] = array(
					'@type'         => 'Offer',
					'price'         => $product->get_price() ? (string) $product->get_price() : '0',
					'priceCurrency' => get_woocommerce_currency(),
					'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
					'url'           => get_permalink( $post_id ),
				);

				$rating_count = $product->get_rating_count();
				if ( $rating_count > 0 ) {
					$data['aggregateRating'] = array(
						'@type'       => 'AggregateRating',
						'ratingValue' => $product->get_average_rating(),
						'reviewCount' => $rating_count,
					);
				}
			}
		}

		return $data;
	}

	/**
	 * Build a FAQPage schema node from the meta box FAQ repeater.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Null when there is nothing valid to output.
	 */
	public function build_faqpage_schema( $post_id ) {
		$faq = get_post_meta( $post_id, '_sso_schema_faq', true );
		if ( empty( $faq ) || ! is_array( $faq ) ) {
			return null;
		}

		$main_entity = array();
		foreach ( $faq as $item ) {
			if ( empty( $item['question'] ) || empty( $item['answer'] ) ) {
				continue;
			}
			$main_entity[] = array(
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( $item['question'] ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( $item['answer'] ),
				),
			);
		}

		if ( ! $main_entity ) {
			return null;
		}

		return array(
			'@type'      => 'FAQPage',
			'mainEntity' => $main_entity,
		);
	}

	/**
	 * Build a LocalBusiness schema node from the meta box fields.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function build_localbusiness_schema( $post_id ) {
		$lb = get_post_meta( $post_id, '_sso_schema_local_business', true );
		if ( ! is_array( $lb ) ) {
			$lb = array();
		}

		$data = array(
			'@type' => 'LocalBusiness',
			'name'  => ! empty( $lb['name'] ) ? $lb['name'] : get_the_title( $post_id ),
			'url'   => get_permalink( $post_id ),
		);

		$address = array_filter(
			array(
				'streetAddress'   => isset( $lb['street'] ) ? $lb['street'] : '',
				'addressLocality' => isset( $lb['city'] ) ? $lb['city'] : '',
				'addressRegion'   => isset( $lb['region'] ) ? $lb['region'] : '',
				'postalCode'      => isset( $lb['postal'] ) ? $lb['postal'] : '',
				'addressCountry'  => isset( $lb['country'] ) ? $lb['country'] : '',
			)
		);

		if ( $address ) {
			$data['address'] = array_merge( array( '@type' => 'PostalAddress' ), $address );
		}

		if ( ! empty( $lb['phone'] ) ) {
			$data['telephone'] = $lb['phone'];
		}
		if ( ! empty( $lb['price_range'] ) ) {
			$data['priceRange'] = $lb['price_range'];
		}
		if ( has_post_thumbnail( $post_id ) ) {
			$data['image'] = get_the_post_thumbnail_url( $post_id, 'large' );
		}

		return $data;
	}

	/*
	------------------------------------------------------------------ *
	 * Additional per-post type schema builders
	 * ------------------------------------------------------------------
	 */

	/**
	 * Build a BlogPosting schema node (more specific than Article, for blog posts).
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function build_blogposting_schema( $post_id ) {
		$data          = $this->build_article_schema( $post_id );
		$data['@type'] = 'BlogPosting';
		return $data;
	}

	/**
	 * Build a WebPage schema node for static pages.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function build_webpage_schema( $post_id ) {
		$description = get_post_meta( $post_id, '_sso_meta_description', true );
		if ( ! $description ) {
			$description = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post_id ) ), 30, '' );
		}

		$data = array(
			'@type'         => 'WebPage',
			'@id'           => get_permalink( $post_id ),
			'url'           => get_permalink( $post_id ),
			'name'          => get_the_title( $post_id ),
			'isPartOf'      => array( '@id' => home_url( '/#website' ) ),
			'datePublished' => get_the_date( 'c', $post_id ),
			'dateModified'  => get_the_modified_date( 'c', $post_id ),
		);

		if ( $description ) {
			$data['description'] = $description;
		}
		if ( has_post_thumbnail( $post_id ) ) {
			$data['image'] = get_the_post_thumbnail_url( $post_id, 'large' );
		}

		return $data;
	}

	/**
	 * Build a BreadcrumbList schema node by reusing SSO_Breadcrumbs::get_trail().
	 *
	 * Works for singular posts/pages AND category/tag/taxonomy/CPT archives.
	 *
	 * @return array|null Null when the trail has only one crumb (homepage).
	 */
	public function build_breadcrumblist_schema() {
		$trail = SSO_Breadcrumbs::instance()->get_trail();

		if ( count( $trail ) <= 1 ) {
			return null;
		}

		$items    = array();
		$position = 1;

		foreach ( $trail as $index => $item ) {
			$list_item = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $item['text'],
			);

			// Last crumb typically has no URL (current page) — resolve it.
			if ( ! empty( $item['url'] ) ) {
				$list_item['item'] = $item['url'];
			} elseif ( is_singular() ) {
				$list_item['item'] = get_permalink( get_queried_object_id() );
			} elseif ( is_category() || is_tag() || is_tax() ) {
				$term_link = get_term_link( get_queried_object() );
				if ( ! is_wp_error( $term_link ) ) {
					$list_item['item'] = $term_link;
				}
			} elseif ( is_post_type_archive() ) {
				$archive_link = get_post_type_archive_link( get_query_var( 'post_type' ) );
				if ( $archive_link ) {
					$list_item['item'] = $archive_link;
				}
			} elseif ( is_author() ) {
				$list_item['item'] = get_author_posts_url( get_queried_object_id() );
			} elseif ( is_home() && ! is_front_page() ) {
				$list_item['item'] = get_permalink( (int) get_option( 'page_for_posts' ) );
			}

			$items[] = $list_item;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}

	/**
	 * Output BreadcrumbList schema on singular posts/pages AND archive pages.
	 *
	 * Covers: single post, child page, CPT single, category archive,
	 * sub-category archive, custom taxonomy archive, CPT archive, author archive.
	 */
	public function output_breadcrumb_schema() {
		if ( is_admin() || is_front_page() || is_404() || is_search() ) {
			return;
		}

		// Skip non-content contexts.
		if ( ! is_singular() && ! is_category() && ! is_tag() && ! is_tax()
			&& ! is_post_type_archive() && ! is_author()
			&& ! ( is_home() && ! is_front_page() ) ) {
			return;
		}

		$data = $this->build_breadcrumblist_schema();

		if ( $data ) {
			$this->print_ld_json( array_merge( array( '@context' => 'https://schema.org' ), $data ) );
		}
	}

	/**
	 * Build a HowTo schema node.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function build_howto_schema( $post_id ) {
		$howto = get_post_meta( $post_id, '_sso_schema_howto', true );
		if ( ! is_array( $howto ) ) {
			$howto = array();
		}

		$data = array(
			'@type' => 'HowTo',
			'name'  => get_the_title( $post_id ),
		);

		$description = get_post_meta( $post_id, '_sso_meta_description', true );
		if ( $description ) {
			$data['description'] = $description;
		}
		if ( ! empty( $howto['total_time'] ) ) {
			$data['totalTime'] = sanitize_text_field( $howto['total_time'] ); // ISO 8601, e.g. PT30M.
		}
		if ( ! empty( $howto['estimated_cost'] ) ) {
			$data['estimatedCost'] = array(
				'@type'    => 'MonetaryAmount',
				'currency' => ! empty( $howto['currency'] ) ? $howto['currency'] : 'VND',
				'value'    => sanitize_text_field( $howto['estimated_cost'] ),
			);
		}
		if ( ! empty( $howto['steps'] ) && is_array( $howto['steps'] ) ) {
			$steps = array();
			foreach ( $howto['steps'] as $step ) {
				if ( empty( $step['name'] ) ) {
					continue;
				}
				$step_data = array(
					'@type' => 'HowToStep',
					'name'  => wp_strip_all_tags( $step['name'] ),
				);
				if ( ! empty( $step['text'] ) ) {
					$step_data['text'] = wp_strip_all_tags( $step['text'] );
				}
				$steps[] = $step_data;
			}
			if ( $steps ) {
				$data['step'] = $steps;
			}
		}
		if ( has_post_thumbnail( $post_id ) ) {
			$data['image'] = get_the_post_thumbnail_url( $post_id, 'large' );
		}

		return $data;
	}

	/**
	 * Build an Event schema node.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function build_event_schema( $post_id ) {
		$event = get_post_meta( $post_id, '_sso_schema_event', true );
		if ( ! is_array( $event ) ) {
			$event = array();
		}

		$data = array(
			'@type'               => 'Event',
			'name'                => ! empty( $event['name'] ) ? sanitize_text_field( $event['name'] ) : get_the_title( $post_id ),
			'url'                 => get_permalink( $post_id ),
			'eventStatus'         => 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		);

		if ( ! empty( $event['start_date'] ) ) {
			$data['startDate'] = sanitize_text_field( $event['start_date'] );
		}
		if ( ! empty( $event['end_date'] ) ) {
			$data['endDate'] = sanitize_text_field( $event['end_date'] );
		}
		if ( ! empty( $event['status'] ) ) {
			$data['eventStatus'] = 'https://schema.org/' . sanitize_text_field( $event['status'] );
		}
		if ( ! empty( $event['attendance_mode'] ) ) {
			$data['eventAttendanceMode'] = 'https://schema.org/' . sanitize_text_field( $event['attendance_mode'] );
		}
		if ( ! empty( $event['location_name'] ) ) {
			$location = array(
				'@type' => 'Place',
				'name'  => sanitize_text_field( $event['location_name'] ),
			);
			if ( ! empty( $event['location_address'] ) ) {
				$location['address'] = array(
					'@type'         => 'PostalAddress',
					'streetAddress' => sanitize_text_field( $event['location_address'] ),
				);
			}
			$data['location'] = $location;
		}
		if ( ! empty( $event['organizer'] ) ) {
			$data['organizer'] = array(
				'@type' => 'Organization',
				'name'  => sanitize_text_field( $event['organizer'] ),
			);
		}

		$description = get_post_meta( $post_id, '_sso_meta_description', true );
		if ( $description ) {
			$data['description'] = $description;
		}
		if ( has_post_thumbnail( $post_id ) ) {
			$data['image'] = get_the_post_thumbnail_url( $post_id, 'large' );
		}

		return $data;
	}

	/**
	 * Build a VideoObject schema node.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function build_video_schema( $post_id ) {
		$video = get_post_meta( $post_id, '_sso_schema_video', true );
		if ( ! is_array( $video ) ) {
			$video = array();
		}

		$data = array(
			'@type'      => 'VideoObject',
			'name'       => ! empty( $video['name'] ) ? sanitize_text_field( $video['name'] ) : get_the_title( $post_id ),
			'uploadDate' => ! empty( $video['upload_date'] ) ? sanitize_text_field( $video['upload_date'] ) : get_the_date( 'c', $post_id ),
		);

		$description = ! empty( $video['description'] ) ? sanitize_textarea_field( $video['description'] ) : get_post_meta( $post_id, '_sso_meta_description', true );
		if ( $description ) {
			$data['description'] = $description;
		}
		if ( ! empty( $video['content_url'] ) ) {
			$data['contentUrl'] = esc_url_raw( $video['content_url'] );
		}
		if ( ! empty( $video['embed_url'] ) ) {
			$data['embedUrl'] = esc_url_raw( $video['embed_url'] );
		}
		if ( ! empty( $video['duration'] ) ) {
			$data['duration'] = sanitize_text_field( $video['duration'] ); // ISO 8601, e.g. PT4M33S.
		}

		$thumb = has_post_thumbnail( $post_id ) ? get_the_post_thumbnail_url( $post_id, 'large' ) : '';
		if ( ! $thumb && ! empty( $video['thumbnail_url'] ) ) {
			$thumb = esc_url_raw( $video['thumbnail_url'] );
		}
		if ( $thumb ) {
			$data['thumbnailUrl'] = $thumb;
		}

		return $data;
	}

	/**
	 * Build a Recipe schema node.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function build_recipe_schema( $post_id ) {
		$recipe = get_post_meta( $post_id, '_sso_schema_recipe', true );
		if ( ! is_array( $recipe ) ) {
			$recipe = array();
		}

		$data = array(
			'@type'         => 'Recipe',
			'name'          => ! empty( $recipe['name'] ) ? sanitize_text_field( $recipe['name'] ) : get_the_title( $post_id ),
			'author'        => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ),
			),
			'datePublished' => get_the_date( 'c', $post_id ),
		);

		$description = get_post_meta( $post_id, '_sso_meta_description', true );
		if ( $description ) {
			$data['description'] = $description;
		}
		if ( has_post_thumbnail( $post_id ) ) {
			$data['image'] = get_the_post_thumbnail_url( $post_id, 'large' );
		}

		$time_fields = array( 'prepTime', 'cookTime', 'totalTime' );
		foreach ( $time_fields as $field ) {
			$key = strtolower( preg_replace( '/([A-Z])/', '_$1', $field ) );
			if ( ! empty( $recipe[ $key ] ) ) {
				$data[ $field ] = sanitize_text_field( $recipe[ $key ] ); // ISO 8601 duration.
			}
		}
		foreach ( array( 'recipeYield', 'recipeCategory', 'recipeCuisine' ) as $field ) {
			$key = strtolower( preg_replace( '/([A-Z])/', '_$1', $field ) );
			if ( ! empty( $recipe[ $key ] ) ) {
				$data[ $field ] = sanitize_text_field( $recipe[ $key ] );
			}
		}

		if ( ! empty( $recipe['ingredients'] ) ) {
			$data['recipeIngredient'] = array_values(
				array_filter( array_map( 'sanitize_text_field', explode( "\n", $recipe['ingredients'] ) ) )
			);
		}
		if ( ! empty( $recipe['instructions'] ) ) {
			$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $recipe['instructions'] ) ) ) );
			$steps = array();
			foreach ( $lines as $line ) {
				$steps[] = array(
					'@type' => 'HowToStep',
					'text'  => wp_strip_all_tags( $line ),
				);
			}
			if ( $steps ) {
				$data['recipeInstructions'] = $steps;
			}
		}

		return $data;
	}

	/**
	 * Build a JobPosting schema node.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function build_jobposting_schema( $post_id ) {
		$job = get_post_meta( $post_id, '_sso_schema_job', true );
		if ( ! is_array( $job ) ) {
			$job = array();
		}

		$data = array(
			'@type'      => 'JobPosting',
			'title'      => ! empty( $job['title'] ) ? sanitize_text_field( $job['title'] ) : get_the_title( $post_id ),
			'datePosted' => get_the_date( 'c', $post_id ),
		);

		$description = get_post_meta( $post_id, '_sso_meta_description', true );
		if ( $description ) {
			$data['description'] = $description;
		}
		if ( ! empty( $job['hiring_org'] ) ) {
			$data['hiringOrganization'] = array(
				'@type'  => 'Organization',
				'name'   => sanitize_text_field( $job['hiring_org'] ),
				'sameAs' => get_permalink( $post_id ),
			);
		}
		if ( ! empty( $job['location'] ) ) {
			$data['jobLocation'] = array(
				'@type'   => 'Place',
				'address' => array(
					'@type'           => 'PostalAddress',
					'addressLocality' => sanitize_text_field( $job['location'] ),
				),
			);
		}
		if ( ! empty( $job['employment_type'] ) ) {
			$data['employmentType'] = sanitize_text_field( $job['employment_type'] );
		}
		if ( ! empty( $job['valid_through'] ) ) {
			$data['validThrough'] = sanitize_text_field( $job['valid_through'] );
		}
		if ( ! empty( $job['salary'] ) ) {
			$data['baseSalary'] = array(
				'@type'    => 'MonetaryAmount',
				'currency' => ! empty( $job['salary_currency'] ) ? sanitize_text_field( $job['salary_currency'] ) : 'VND',
				'value'    => array(
					'@type'    => 'QuantitativeValue',
					'value'    => sanitize_text_field( $job['salary'] ),
					'unitText' => ! empty( $job['salary_period'] ) ? sanitize_text_field( $job['salary_period'] ) : 'MONTH',
				),
			);
		}

		return $data;
	}

	/**
	 * Build a Course schema node.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function build_course_schema( $post_id ) {
		$course = get_post_meta( $post_id, '_sso_schema_course', true );
		if ( ! is_array( $course ) ) {
			$course = array();
		}

		$data = array(
			'@type' => 'Course',
			'name'  => ! empty( $course['name'] ) ? sanitize_text_field( $course['name'] ) : get_the_title( $post_id ),
		);

		$description = get_post_meta( $post_id, '_sso_meta_description', true );
		if ( $description ) {
			$data['description'] = $description;
		}
		if ( ! empty( $course['provider'] ) ) {
			$provider = array(
				'@type' => 'Organization',
				'name'  => sanitize_text_field( $course['provider'] ),
			);
			if ( ! empty( $course['provider_url'] ) ) {
				$provider['sameAs'] = esc_url_raw( $course['provider_url'] );
			}
			$data['provider'] = $provider;
		}
		if ( ! empty( $course['url'] ) ) {
			$data['url'] = esc_url_raw( $course['url'] );
		}

		return $data;
	}

	/**
	 * Build a Review schema node.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function build_review_schema( $post_id ) {
		$review = get_post_meta( $post_id, '_sso_schema_review', true );
		if ( ! is_array( $review ) ) {
			$review = array();
		}

		$data = array(
			'@type'         => 'Review',
			'name'          => get_the_title( $post_id ),
			'url'           => get_permalink( $post_id ),
			'datePublished' => get_the_date( 'c', $post_id ),
			'author'        => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ),
			),
		);

		if ( ! empty( $review['item_reviewed'] ) ) {
			$data['itemReviewed'] = array(
				'@type' => 'Thing',
				'name'  => sanitize_text_field( $review['item_reviewed'] ),
			);
		}
		if ( ! empty( $review['rating'] ) ) {
			$data['reviewRating'] = array(
				'@type'       => 'Rating',
				'ratingValue' => (string) absint( $review['rating'] ),
				'bestRating'  => '5',
				'worstRating' => '1',
			);
		}
		if ( ! empty( $review['body'] ) ) {
			$data['reviewBody'] = sanitize_textarea_field( $review['body'] );
		}

		return $data;
	}

	/*
	------------------------------------------------------------------ *
	 * Output helper
	 * ------------------------------------------------------------------
	 */

	/**
	 * Encode and print a JSON-LD script tag, bailing out on invalid JSON so a
	 * malformed graph never reaches Google Rich Results.
	 *
	 * @param array $data Schema data.
	 */
	public function print_ld_json( $data ) {
		// Slashes are intentionally left escaped (no JSON_UNESCAPED_SLASHES) so a
		// literal "</script>" inside any string value cannot break out of the tag.
		$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE );

		if ( ! $json || JSON_ERROR_NONE !== json_last_error() ) {
			return;
		}

		echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() output validated above; safe JSON-LD payload.
	}
}
